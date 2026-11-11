using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Controls.Primitives;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Threading;
using Microsoft.Win32;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.Wpf;

namespace FunkenwerkBrowser
{
    public partial class MainWindow : Window
    {
        private sealed class BrowserTabSession
        {
            public Guid Id { get; } = Guid.NewGuid();
            public string Title { get; set; } = "Neuer Tab";
            public string CurrentUrl { get; set; } = "";
            public WebView2 View { get; set; } = null!;
            public string? PendingBlockedUrl { get; set; }
            public string? PendingBlockedHost { get; set; }
        }

        private sealed class FolderComboOption
        {
            public string FolderId { get; init; } = "";
            public string DisplayPath { get; init; } = "";
            public List<BookmarkNodeDto> TargetList { get; init; } = null!;
            public override string ToString() => DisplayPath;
        }

        private readonly BrowserSettings _settings;
        private BrowserVaultData _vault;
        private readonly List<BrowserTabSession> _tabs = new();
        private BrowserTabSession? _activeTab;
        private CoreWebView2Environment? _sharedEnv;
        private int _blockedTrackersCount;
        private readonly DispatcherTimer _airlockTickTimer = new() { Interval = TimeSpan.FromSeconds(1) };

        // Bookmark Folder, Hover-Switch & Drag-and-Drop State
        private readonly Stack<BookmarkNodeDto> _openFolderStack = new();
        private BookmarkNodeDto? _activeBarFolderNode;
        private UIElement? _lastFolderAnchorElement;
        private BookmarkNodeDto? _editingNode;
        private Point _bookmarkDragStartPoint;
        private BookmarkNodeDto? _draggingBookmarkNode;
        private bool _suppressNextBookmarkClick;

        // Password Vault UI State
        private bool _isNewPassRevealed;

        public MainWindow(string? startupUrl)
        {
            InitializeComponent();
            _settings = BrowserShieldAndSettings.LoadSettings();
            _vault = BrowserAutofillAndVaultEngine.LoadVault();

            ApplyTheme(_settings.DarkMode);
            RebuildBookmarksBarUi();
            LoadAutofillProfileIntoUi();
            RebuildVaultLoginsListUi();

            _airlockTickTimer.Tick += (_, _) => RefreshAirlockButtonUi();
            _airlockTickTimer.Start();

            Loaded += async (_, _) =>
            {
                string startTarget = string.IsNullOrWhiteSpace(startupUrl)
                    ? _settings.HomeUrl
                    : BrowserShieldAndSettings.NormalizeUserUrlOrSearch(startupUrl);

                await CreateAndSelectTabAsync(startTarget);
                RefreshAirlockButtonUi();
            };

            Closed += (_, _) =>
            {
                _airlockTickTimer.Stop();
                foreach (var t in _tabs)
                {
                    try { t.View.Dispose(); } catch { }
                }
            };
        }

        private void ApplyTheme(bool dark)
        {
            _settings.DarkMode = dark;
            if (dark)
            {
                Resources["BgCanvasBrush"] = Solid("#0C0F16");
                Resources["BgBarBrush"] = Solid("#111520");
                Resources["BgElevatedBrush"] = Solid("#191F2E");
                Resources["BookmarkHoverBrush"] = Solid("#22FFFFFF");
                Resources["BorderSubtleBrush"] = Solid("#38C5A059");
                Resources["TextPrimaryBrush"] = Solid("#F9FAFB");
                Resources["TextSecondaryBrush"] = Solid("#9CA3AF");
                ThemeBtn.Content = "☀";
            }
            else
            {
                Resources["BgCanvasBrush"] = Solid("#F4F5F7");
                Resources["BgBarBrush"] = Solid("#FFFFFF");
                Resources["BgElevatedBrush"] = Solid("#F8F6F1");
                Resources["BookmarkHoverBrush"] = Solid("#14000000");
                Resources["BorderSubtleBrush"] = Solid("#66C5A059");
                Resources["TextPrimaryBrush"] = Solid("#111827");
                Resources["TextSecondaryBrush"] = Solid("#4B5563");
                ThemeBtn.Content = "☾";
            }
            RebuildTabHeaders();
            RebuildBookmarksBarUi();
        }

        private void ToggleTheme_Click(object sender, RoutedEventArgs e)
        {
            ApplyTheme(!_settings.DarkMode);
            BrowserShieldAndSettings.SaveSettings(_settings);
        }

        private async Task<CoreWebView2Environment> EnsureEnvironmentAsync()
        {
            if (_sharedEnv != null) return _sharedEnv;
            var opts = new CoreWebView2EnvironmentOptions("--ignore-gpu-blocklist --enable-gpu-rasterization --autoplay-policy=no-user-gesture-required --disable-features=msSmartScreenProtection,AutofillServerCommunication");
            string? customFolder = ResolveInstalledWebView2Folder();
            _sharedEnv = await CoreWebView2Environment.CreateAsync(customFolder, BrowserShieldAndSettings.WebViewUserDataDir, opts);
            return _sharedEnv;
        }

        private static string? ResolveInstalledWebView2Folder()
        {
            try
            {
                string[] baseDirs =
                {
                    @"C:\Program Files (x86)\Microsoft\EdgeWebView\Application",
                    @"C:\Program Files\Microsoft\EdgeWebView\Application",
                    @"C:\Program Files (x86)\Microsoft\EdgeCore",
                    @"C:\Program Files (x86)\Microsoft\Edge\Application"
                };

                foreach (string baseDir in baseDirs)
                {
                    if (!System.IO.Directory.Exists(baseDir)) continue;
                    var verDirs = System.IO.Directory.GetDirectories(baseDir)
                        .OrderByDescending(d => d, StringComparer.OrdinalIgnoreCase);
                    foreach (string vd in verDirs)
                    {
                        if (System.IO.File.Exists(System.IO.Path.Combine(vd, "msedgewebview2.exe")))
                            return vd;
                    }
                }
            }
            catch { }
            return null;
        }

        private async Task CreateAndSelectTabAsync(string initialUrl)
        {
            var webView = new WebView2
            {
                DefaultBackgroundColor = System.Drawing.Color.FromArgb(255, 12, 15, 22)
            };

            webView.GotFocus += (_, _) => CloseAllFolderPopups();

            var tab = new BrowserTabSession
            {
                Title = "Laden...",
                CurrentUrl = initialUrl,
                View = webView
            };

            _tabs.Add(tab);
            WebViewsHostGrid.Children.Add(webView);
            SelectTab(tab);

            try
            {
                var env = await EnsureEnvironmentAsync();
                await webView.EnsureCoreWebView2Async(env);

                var core = webView.CoreWebView2;
                core.Settings.IsPasswordAutosaveEnabled = true;
                core.Settings.IsGeneralAutofillEnabled = true;
                core.Settings.AreDefaultScriptDialogsEnabled = true;
                core.Settings.AreDevToolsEnabled = true;

                // Network-level Ad, Tracker & Fortress Airlock Filter
                core.AddWebResourceRequestedFilter("*", CoreWebView2WebResourceContext.All);
                core.WebResourceRequested += (_, args) =>
                {
                    string reqUri = args.Request.Uri;
                    if (BrowserShieldAndSettings.IsAdOrTrackerUri(reqUri))
                    {
                        args.Response = core.Environment.CreateWebResourceResponse(null, 403, "Blocked", "");
                        Dispatcher.BeginInvoke(() =>
                        {
                            _blockedTrackersCount++;
                            BlockedCountTxt.Text = $"🛡 {_blockedTrackersCount}";
                        });
                    }
                };

                // Allow Microphone & Webcam on HTTPS sites (YouTube, WhatsApp Web, Discord, Seelenfunke)
                core.PermissionRequested += (_, args) =>
                {
                    if (args.PermissionKind is CoreWebView2PermissionKind.Microphone or CoreWebView2PermissionKind.Camera)
                    {
                        if (args.Uri.StartsWith("https://", StringComparison.OrdinalIgnoreCase) ||
                            args.Uri.StartsWith("http://127.0.0.1", StringComparison.OrdinalIgnoreCase))
                            args.State = CoreWebView2PermissionState.Allow;
                    }
                };

                // Open target="_blank" popups cleanly inside a new tab instead of an unmanaged popup window
                core.NewWindowRequested += async (_, args) =>
                {
                    args.Handled = true;
                    if (!string.IsNullOrWhiteSpace(args.Uri))
                        await CreateAndSelectTabAsync(args.Uri);
                };

                // Route all Browser Downloads directly into Funkenwerk OS 00_Eingang_Trichter (Zero Standard Downloads Clutter!)
                core.DownloadStarting += (_, args) =>
                {
                    try
                    {
                        string trichterDir = System.IO.Path.Combine(
                            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                            "FunkenwerkOS",
                            "Meine Daten",
                            "00_Eingang_Trichter");
                        System.IO.Directory.CreateDirectory(trichterDir);

                        string fileName = System.IO.Path.GetFileName(args.ResultFilePath);
                        if (string.IsNullOrWhiteSpace(fileName))
                            fileName = $"Download_{DateTime.Now:yyyy-MM-dd_HHmmss}.bin";

                        string destPath = System.IO.Path.Combine(trichterDir, fileName);
                        if (System.IO.File.Exists(destPath))
                        {
                            string stem = System.IO.Path.GetFileNameWithoutExtension(fileName);
                            string ext = System.IO.Path.GetExtension(fileName);
                            destPath = System.IO.Path.Combine(trichterDir, $"{stem}_{DateTime.Now:HHmmss}{ext}");
                        }

                        args.ResultFilePath = destPath;
                    }
                    catch { }
                };

                core.NavigationStarting += (_, args) =>
                {
                    if (!BrowserShieldAndSettings.IsNavigationAllowedByFortress(_settings, args.Uri, out string blockedHost))
                    {
                        args.Cancel = true;
                        tab.PendingBlockedUrl = args.Uri;
                        tab.PendingBlockedHost = blockedHost;
                        if (_activeTab == tab)
                            ShowBlockedGateForTab(tab);
                        return;
                    }

                    tab.PendingBlockedUrl = null;
                    tab.PendingBlockedHost = null;
                    tab.CurrentUrl = args.Uri;
                    if (_activeTab == tab)
                    {
                        AirlockBlockedOverlay.Visibility = Visibility.Collapsed;
                        UpdateAddressBarFromTab(tab);
                    }
                };

                core.SourceChanged += (_, _) =>
                {
                    tab.CurrentUrl = core.Source;
                    if (_activeTab == tab)
                        UpdateAddressBarFromTab(tab);
                };

                core.DocumentTitleChanged += (_, _) =>
                {
                    string t = core.DocumentTitle;
                    tab.Title = string.IsNullOrWhiteSpace(t) ? "Tab" : t;
                    RebuildTabHeaders();
                };

                // Live Favicon capture -> updates Bookmark Bar icons automatically
                core.FaviconChanged += async (_, _) =>
                {
                    try
                    {
                        string currentUrl = tab.CurrentUrl;
                        using var stream = await core.GetFaviconAsync(CoreWebView2FaviconImageFormat.Png);
                        if (stream != null)
                        {
                            BrowserAutofillAndVaultEngine.StoreFaviconStreamForUrl(currentUrl, stream);
                            RebuildBookmarksBarUi();
                        }
                    }
                    catch { }
                };

                // Auto-Autofill & Form Submit Credential Capture
                core.NavigationCompleted += async (_, args) =>
                {
                    if (!args.IsSuccess) return;
                    try
                    {
                        string script = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(_vault, tab.CurrentUrl, forceFillForms: false);
                        await core.ExecuteScriptAsync(script);
                    }
                    catch { }

                    if (_activeTab == tab)
                    {
                        UpdateAddressBarFromTab(tab);
                        SyncVaultQuickHeaderWithCurrentTab();
                    }
                };

                core.WebMessageReceived += (_, args) =>
                {
                    try
                    {
                        string raw = args.TryGetWebMessageAsString();
                        if (string.IsNullOrWhiteSpace(raw)) return;
                        using var doc = JsonDocument.Parse(raw);
                        if (!doc.RootElement.TryGetProperty("type", out var tp)) return;

                        string msgType = tp.GetString() ?? "";
                        if (msgType == "fwb_webview_click")
                        {
                            CloseAllFolderPopups();
                            BookmarkEditPopup.IsOpen = false;
                        }
                        else if (msgType == "fwb_save_login")
                        {
                            string origin = doc.RootElement.TryGetProperty("origin", out var op) ? (op.GetString() ?? tab.CurrentUrl) : tab.CurrentUrl;
                            string user = doc.RootElement.TryGetProperty("username", out var up) ? (up.GetString() ?? "") : "";
                            string pass = doc.RootElement.TryGetProperty("password", out var pp) ? (pp.GetString() ?? "") : "";
                            if (!string.IsNullOrWhiteSpace(user) && !string.IsNullOrWhiteSpace(pass))
                            {
                                BrowserAutofillAndVaultEngine.UpsertLoginCredential(_vault, origin, user, pass);
                                SyncVaultQuickHeaderWithCurrentTab();
                                RebuildVaultLoginsListUi();
                            }
                        }
                    }
                    catch { }
                };

                NavigateTabTo(tab, initialUrl);
            }
            catch (Exception ex)
            {
                tab.Title = "Fehler";
                AddressBox.Text = ex.Message;
                RebuildTabHeaders();
            }
        }

        private void NavigateTabTo(BrowserTabSession tab, string rawInput)
        {
            CloseAllFolderPopups();
            string url = BrowserShieldAndSettings.NormalizeUserUrlOrSearch(rawInput);
            if (!BrowserShieldAndSettings.IsNavigationAllowedByFortress(_settings, url, out string blockedHost))
            {
                tab.PendingBlockedUrl = url;
                tab.PendingBlockedHost = blockedHost;
                tab.Title = blockedHost;
                RebuildTabHeaders();
                if (_activeTab == tab)
                    ShowBlockedGateForTab(tab);
                return;
            }

            tab.PendingBlockedUrl = null;
            tab.PendingBlockedHost = null;
            AirlockBlockedOverlay.Visibility = Visibility.Collapsed;
            tab.CurrentUrl = url;
            UpdateAddressBarFromTab(tab);

            if (tab.View.CoreWebView2 != null)
                tab.View.CoreWebView2.Navigate(url);
        }

        private void ShowBlockedGateForTab(BrowserTabSession tab)
        {
            BlockedDomainTitleTxt.Text = string.IsNullOrWhiteSpace(tab.PendingBlockedHost) ? "Fremde Domain" : tab.PendingBlockedHost;
            AddressBox.Text = tab.PendingBlockedUrl ?? "";
            SslIconTxt.Text = "🛡";
            AirlockBlockedOverlay.Visibility = Visibility.Visible;
        }

        private void SelectTab(BrowserTabSession tab)
        {
            CloseAllFolderPopups();
            _activeTab = tab;
            foreach (var t in _tabs)
                t.View.Visibility = (t == tab) ? Visibility.Visible : Visibility.Collapsed;

            if (!string.IsNullOrEmpty(tab.PendingBlockedUrl))
                ShowBlockedGateForTab(tab);
            else
            {
                AirlockBlockedOverlay.Visibility = Visibility.Collapsed;
                UpdateAddressBarFromTab(tab);
            }

            SyncVaultQuickHeaderWithCurrentTab();
            RebuildTabHeaders();
        }

        private void CloseTab(BrowserTabSession tab)
        {
            if (_tabs.Count <= 1)
            {
                NavigateTabTo(tab, _settings.HomeUrl);
                return;
            }

            int idx = _tabs.IndexOf(tab);
            _tabs.Remove(tab);
            WebViewsHostGrid.Children.Remove(tab.View);
            try { tab.View.Dispose(); } catch { }

            if (_activeTab == tab)
            {
                var next = _tabs[Math.Clamp(idx - 1, 0, _tabs.Count - 1)];
                SelectTab(next);
            }
            else
            {
                RebuildTabHeaders();
            }
        }

        private void RebuildTabHeaders()
        {
            TabsHeaderStack.Children.Clear();
            foreach (var tab in _tabs)
            {
                bool active = (tab == _activeTab);
                var bd = new Border
                {
                    Background = active ? (Brush)Resources["BgBarBrush"] : Brushes.Transparent,
                    BorderBrush = active ? Solid("#C5A059") : (Brush)Resources["BorderSubtleBrush"],
                    BorderThickness = new Thickness(1, 1, 1, 0),
                    CornerRadius = new CornerRadius(9, 9, 0, 0),
                    Padding = new Thickness(11, 5, 7, 5),
                    Margin = new Thickness(0, 0, 4, 0),
                    Cursor = Cursors.Hand
                };

                var sp = new StackPanel { Orientation = Orientation.Horizontal };
                string shortTitle = tab.Title.Length > 22 ? tab.Title.Substring(0, 21) + "…" : tab.Title;
                sp.Children.Add(new TextBlock
                {
                    Text = shortTitle,
                    FontSize = 11.5,
                    FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold,
                    Foreground = active ? Solid("#C5A059") : (Brush)Resources["TextPrimaryBrush"],
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 8, 0)
                });

                var closeBtn = new Button
                {
                    Content = "✕",
                    FontSize = 10,
                    Width = 18,
                    Height = 18,
                    Padding = new Thickness(0),
                    Background = Brushes.Transparent,
                    Foreground = (Brush)Resources["TextSecondaryBrush"],
                    BorderThickness = new Thickness(0),
                    Cursor = Cursors.Hand
                };
                var captured = tab;
                closeBtn.Click += (_, ev) => { ev.Handled = true; CloseTab(captured); };
                sp.Children.Add(closeBtn);

                bd.Child = sp;
                bd.MouseLeftButtonDown += (_, _) => SelectTab(captured);
                TabsHeaderStack.Children.Add(bd);
            }
        }

        private void UpdateAddressBarFromTab(BrowserTabSession tab)
        {
            AddressBox.Text = tab.CurrentUrl;
            SslIconTxt.Text = tab.CurrentUrl.StartsWith("https://", StringComparison.OrdinalIgnoreCase) ? "🔒" : "🌐";
            UpdateBookmarkStarState(tab.CurrentUrl);
        }

        private void UpdateBookmarkStarState(string url)
        {
            var match = BrowserShieldAndSettings.FindBookmarkByUrl(_settings.BookmarkBarNodes, url);
            if (match != null)
            {
                BookmarkStarBtn.Content = "★";
                BookmarkStarBtn.Foreground = Solid("#C5A059");
            }
            else
            {
                BookmarkStarBtn.Content = "☆";
                BookmarkStarBtn.Foreground = (Brush)Resources["TextSecondaryBrush"];
            }
        }

        // =====================================================================
        // CHROME-STYLE BOOKMARK BAR (1:1 CHROME LOOK, HOVER-SWITCH & DRAG-REORDER)
        // =====================================================================
        private void RebuildBookmarksBarUi()
        {
            if (BookmarksBarStack == null) return;
            BookmarksBarStack.Children.Clear();

            foreach (var node in _settings.BookmarkBarNodes)
            {
                var btn = CreateBookmarkBarElement(node);
                BookmarksBarStack.Children.Add(btn);
            }

            UpdateActiveBarFolderHighlight();
            if (_activeTab != null)
                UpdateBookmarkStarState(_activeTab.CurrentUrl);
        }

        private void UpdateActiveBarFolderHighlight()
        {
            if (BookmarksBarStack == null) return;
            foreach (var child in BookmarksBarStack.Children.OfType<Button>())
            {
                if (child.Tag is BookmarkNodeDto node &&
                    BookmarkFolderPopup.IsOpen &&
                    _activeBarFolderNode != null &&
                    string.Equals(node.Id, _activeBarFolderNode.Id, StringComparison.OrdinalIgnoreCase))
                {
                    child.Background = Solid("#28C5A059");
                }
                else
                {
                    child.Background = Brushes.Transparent;
                }
            }
        }

        private void CloseAllFolderPopups()
        {
            BookmarkSubfolderPopup.IsOpen = false;
            BookmarkFolderPopup.IsOpen = false;
            _activeBarFolderNode = null;
            _openFolderStack.Clear();
            UpdateActiveBarFolderHighlight();
        }

        private Button CreateBookmarkBarElement(BookmarkNodeDto node)
        {
            bool hasText = node.HasVisibleName;
            var btn = new Button
            {
                Style = (Style)Resources["FlatBookmarkBtn"],
                Padding = hasText ? new Thickness(6, 4, 6, 4) : new Thickness(5, 4, 5, 4),
                Margin = new Thickness(0, 0, 2, 0),
                Tag = node,
                AllowDrop = true,
                ToolTip = node.IsFolder
                    ? (hasText ? $"{node.Name} ({node.Children.Count} Einträge)\nZiehen: Position ändern | Rechtsklick: Umbenennen" : $"Ordner ({node.Children.Count} Einträge)")
                    : (hasText ? $"{node.Name}\n{node.Url}\nZiehen: Position ändern | Rechtsklick: Name ändern (auch leer für nur Icon)" : $"{node.Url}\nZiehen: Position ändern | Rechtsklick: Name ändern")
            };

            var row = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };

            var iconImg = new Image
            {
                Width = 15,
                Height = 15,
                VerticalAlignment = VerticalAlignment.Center,
                SnapsToDevicePixels = true
            };
            RenderOptions.SetBitmapScalingMode(iconImg, BitmapScalingMode.HighQuality);

            if (node.IsFolder)
            {
                iconImg.Source = BrowserAutofillAndVaultEngine.GetFolderBadgeIcon();
            }
            else
            {
                var cached = BrowserAutofillAndVaultEngine.TryGetCachedFaviconSync(node.Url);
                if (cached != null)
                {
                    iconImg.Source = cached;
                }
                else
                {
                    iconImg.Source = BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon(node.Url);
                    string targetUrl = node.Url;
                    _ = Task.Run(async () =>
                    {
                        var fetched = await BrowserAutofillAndVaultEngine.GetOrFetchFaviconAsync(targetUrl);
                        if (fetched != null)
                        {
                            _ = Dispatcher.BeginInvoke(() => iconImg.Source = fetched);
                        }
                    });
                }
            }

            row.Children.Add(iconImg);

            if (hasText)
            {
                row.Children.Add(new TextBlock
                {
                    Text = node.Name,
                    FontSize = 11.5,
                    Foreground = (Brush)Resources["TextPrimaryBrush"],
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(5.5, 0, 0, 0),
                    MaxWidth = node.IsFolder ? 140 : 112,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
            }

            btn.Content = row;

            // HOVER-TO-SWITCH FOLDER: If any folder popup is already open, hovering over another folder opens it immediately!
            btn.MouseEnter += (_, _) =>
            {
                if (BookmarkFolderPopup.IsOpen && node.IsFolder)
                {
                    if (_activeBarFolderNode == null || !string.Equals(_activeBarFolderNode.Id, node.Id, StringComparison.OrdinalIgnoreCase))
                    {
                        OpenFolderPopup(node, btn);
                    }
                }
            };

            // Left-Click: Navigate URL or Toggle Folder Popup
            btn.Click += async (_, e) =>
            {
                e.Handled = true;
                if (_suppressNextBookmarkClick)
                {
                    _suppressNextBookmarkClick = false;
                    return;
                }

                if (node.IsFolder)
                {
                    if (BookmarkFolderPopup.IsOpen && _activeBarFolderNode != null &&
                        string.Equals(_activeBarFolderNode.Id, node.Id, StringComparison.OrdinalIgnoreCase))
                    {
                        CloseAllFolderPopups();
                    }
                    else
                    {
                        OpenFolderPopup(node, btn);
                    }
                }
                else if (!string.IsNullOrWhiteSpace(node.Url))
                {
                    CloseAllFolderPopups();
                    if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
                        await CreateAndSelectTabAsync(node.Url);
                    else if (_activeTab != null)
                        NavigateTabTo(_activeTab, node.Url);
                }
            };

            // Middle-Click: Open in new tab
            btn.MouseUp += async (_, e) =>
            {
                if (e.ChangedButton == MouseButton.Middle && !node.IsFolder && !string.IsNullOrWhiteSpace(node.Url))
                {
                    e.Handled = true;
                    CloseAllFolderPopups();
                    await CreateAndSelectTabAsync(node.Url);
                }
            };

            // Right-Click: Open Rename / Edit Popover directly with Name focused & Enter to confirm
            btn.MouseRightButtonUp += (_, e) =>
            {
                e.Handled = true;
                CloseAllFolderPopups();
                OpenBookmarkEditPopup(node, btn);
            };

            // Drag & Drop to reorder folder/bookmark positions on the bar
            btn.PreviewMouseLeftButtonDown += (_, e) =>
            {
                _bookmarkDragStartPoint = e.GetPosition(this);
                _draggingBookmarkNode = node;
                _suppressNextBookmarkClick = false;
            };

            btn.PreviewMouseMove += (_, e) =>
            {
                if (e.LeftButton == MouseButtonState.Pressed && _draggingBookmarkNode == node)
                {
                    Point pos = e.GetPosition(this);
                    if (Math.Abs(pos.X - _bookmarkDragStartPoint.X) > 5 || Math.Abs(pos.Y - _bookmarkDragStartPoint.Y) > 5)
                    {
                        _suppressNextBookmarkClick = true;
                        _draggingBookmarkNode = null;
                        CloseAllFolderPopups();
                        if (btn.IsMouseCaptured)
                            btn.ReleaseMouseCapture();

                        try
                        {
                            DragDrop.DoDragDrop(btn, node, DragDropEffects.Move);
                        }
                        finally
                        {
                            ClearBarDropIndicators();
                        }
                    }
                }
            };

            btn.DragOver += BookmarkBarContainer_DragOver;
            btn.Drop += BookmarkBarEmpty_Drop;

            return btn;
        }

        private void ClearBarDropIndicators()
        {
            if (BookmarksBarStack == null) return;
            foreach (var child in BookmarksBarStack.Children.OfType<Button>())
            {
                child.BorderBrush = Brushes.Transparent;
                child.BorderThickness = new Thickness(0);
            }
        }

        private int CalculateBarDropTargetIndex(DragEventArgs e, out Button? highlightedBtn, out bool insertAfterButton)
        {
            highlightedBtn = null;
            insertAfterButton = false;

            var buttons = BookmarksBarStack.Children.OfType<Button>().ToList();
            if (buttons.Count == 0) return 0;

            Point posInStack = e.GetPosition(BookmarksBarStack);

            for (int i = 0; i < buttons.Count; i++)
            {
                var b = buttons[i];
                Point topLeft = b.TranslatePoint(new Point(0, 0), BookmarksBarStack);
                double center = topLeft.X + b.ActualWidth * 0.5;
                double right = topLeft.X + b.ActualWidth + 2;

                if (posInStack.X <= right)
                {
                    highlightedBtn = b;
                    if (posInStack.X <= center)
                    {
                        insertAfterButton = false;
                        return i;
                    }
                    else
                    {
                        insertAfterButton = true;
                        return i + 1;
                    }
                }
            }

            highlightedBtn = buttons[^1];
            insertAfterButton = true;
            return buttons.Count;
        }

        private void BookmarkBarContainer_DragOver(object sender, DragEventArgs e)
        {
            if (!e.Data.GetDataPresent(typeof(BookmarkNodeDto)))
            {
                e.Effects = DragDropEffects.None;
                e.Handled = true;
                return;
            }

            e.Effects = DragDropEffects.Move;
            e.Handled = true;

            ClearBarDropIndicators();
            _ = CalculateBarDropTargetIndex(e, out var targetBtn, out bool after);
            if (targetBtn != null)
            {
                targetBtn.BorderBrush = Solid("#C5A059");
                targetBtn.BorderThickness = after ? new Thickness(0, 0, 2.5, 0) : new Thickness(2.5, 0, 0, 0);
            }
        }

        private void BookmarkBarContainer_DragLeave(object sender, DragEventArgs e)
        {
            ClearBarDropIndicators();
        }

        private void BookmarkBarEmpty_Drop(object sender, DragEventArgs e)
        {
            e.Handled = true;
            ClearBarDropIndicators();

            if (!e.Data.GetDataPresent(typeof(BookmarkNodeDto)) ||
                e.Data.GetData(typeof(BookmarkNodeDto)) is not BookmarkNodeDto dragged)
            {
                return;
            }

            int targetIndex = CalculateBarDropTargetIndex(e, out _, out _);
            var (foundDragged, draggedParentList, _) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, dragged.Id);
            if (foundDragged == null || draggedParentList == null) return;

            int oldIndexInBar = _settings.BookmarkBarNodes.IndexOf(foundDragged);
            draggedParentList.Remove(foundDragged);

            if (ReferenceEquals(draggedParentList, _settings.BookmarkBarNodes) && oldIndexInBar >= 0 && oldIndexInBar < targetIndex)
            {
                targetIndex--;
            }

            targetIndex = Math.Clamp(targetIndex, 0, _settings.BookmarkBarNodes.Count);
            _settings.BookmarkBarNodes.Insert(targetIndex, foundDragged);

            BrowserShieldAndSettings.SaveSettings(_settings);
            RebuildBookmarksBarUi();
        }

        private void FolderPopup_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(typeof(BookmarkNodeDto)))
            {
                e.Effects = DragDropEffects.Move;
                e.Handled = true;
            }
        }

        private void FolderPopup_Drop(object sender, DragEventArgs e)
        {
            e.Handled = true;
            if (_openFolderStack.Count == 0) return;
            if (!e.Data.GetDataPresent(typeof(BookmarkNodeDto)) ||
                e.Data.GetData(typeof(BookmarkNodeDto)) is not BookmarkNodeDto dragged)
            {
                return;
            }

            var currentFolder = _openFolderStack.Peek();
            if (string.Equals(currentFolder.Id, dragged.Id, StringComparison.OrdinalIgnoreCase)) return;

            var (foundDragged, draggedParentList, _) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, dragged.Id);
            if (foundDragged == null || draggedParentList == null) return;

            draggedParentList.Remove(foundDragged);
            currentFolder.Children ??= new List<BookmarkNodeDto>();
            currentFolder.Children.Add(foundDragged);

            BrowserShieldAndSettings.SaveSettings(_settings);
            RebuildBookmarksBarUi();
            RebuildFolderPopupItems();
        }

        private void BookmarkBarScroll_PreviewMouseWheel(object sender, MouseWheelEventArgs e)
        {
            if (sender is ScrollViewer sv)
            {
                sv.ScrollToHorizontalOffset(sv.HorizontalOffset - e.Delta * 0.45);
                e.Handled = true;
            }
        }

        private void BookmarkBarEmpty_MouseRightButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (e.OriginalSource is Border or ScrollViewer or Grid or StackPanel)
            {
                e.Handled = true;
                CloseAllFolderPopups();
                AddBookmarkForCurrentPage(openEditorImmediately: true, targetFolder: null, anchor: BookmarkBarContainer);
            }
        }

        // =====================================================================
        // FOLDER DROPDOWN POPUP & CASCADING HOVER SUBFOLDER POPUP
        // =====================================================================
        private void OpenFolderPopup(BookmarkNodeDto rootFolder, UIElement anchorButton)
        {
            BookmarkSubfolderPopup.IsOpen = false;
            _activeBarFolderNode = rootFolder;
            _openFolderStack.Clear();
            _openFolderStack.Push(rootFolder);
            _lastFolderAnchorElement = anchorButton;

            RebuildFolderPopupItems();

            // Re-anchoring an already-open Popup in WPF requires toggling IsOpen or PlacementTarget update
            BookmarkFolderPopup.IsOpen = false;
            BookmarkFolderPopup.PlacementTarget = anchorButton;
            BookmarkFolderPopup.Placement = PlacementMode.Bottom;
            BookmarkFolderPopup.IsOpen = true;

            UpdateActiveBarFolderHighlight();
        }

        private void RebuildFolderPopupItems()
        {
            if (_openFolderStack.Count == 0)
            {
                CloseAllFolderPopups();
                return;
            }

            var currentFolder = _openFolderStack.Peek();
            FolderPopupBackBtn.Visibility = _openFolderStack.Count > 1 ? Visibility.Visible : Visibility.Collapsed;
            FolderPopupTitleTxt.Text = currentFolder.HasVisibleName ? $"📁 {currentFolder.Name}" : "📁 ORDNER";

            FolderPopupItemsStack.Children.Clear();
            currentFolder.Children ??= new List<BookmarkNodeDto>();

            if (currentFolder.Children.Count == 0)
            {
                FolderPopupItemsStack.Children.Add(new TextBlock
                {
                    Text = "Dieser Ordner ist noch leer.",
                    FontSize = 11.5,
                    Foreground = (Brush)Resources["TextSecondaryBrush"],
                    Margin = new Thickness(8, 10, 8, 10)
                });
                return;
            }

            foreach (var child in currentFolder.Children)
            {
                var itemBtn = CreatePopupBookmarkRowButton(child, isSubfolderPopup: false);
                FolderPopupItemsStack.Children.Add(itemBtn);
            }
        }

        private Button CreatePopupBookmarkRowButton(BookmarkNodeDto child, bool isSubfolderPopup)
        {
            var itemBtn = new Button
            {
                Style = (Style)Resources["FlatBookmarkBtn"],
                HorizontalContentAlignment = HorizontalAlignment.Stretch,
                Padding = new Thickness(8, 5.5, 8, 5.5),
                Margin = new Thickness(0, 1, 0, 1),
                Tag = child,
                AllowDrop = true,
                ToolTip = child.IsFolder
                    ? $"Ordner: {child.Name} | Rechtsklick: Umbenennen / Bearbeiten"
                    : $"{child.Url}\nRechtsklick: Name ändern / Verschieben / Löschen"
            };

            var grid = new Grid { Width = isSubfolderPopup ? 280 : 310 };
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var iconImg = new Image
            {
                Width = 16,
                Height = 16,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(0, 0, 8, 0)
            };
            RenderOptions.SetBitmapScalingMode(iconImg, BitmapScalingMode.HighQuality);

            if (child.IsFolder)
            {
                iconImg.Source = BrowserAutofillAndVaultEngine.GetFolderBadgeIcon();
            }
            else
            {
                var cached = BrowserAutofillAndVaultEngine.TryGetCachedFaviconSync(child.Url);
                if (cached != null)
                {
                    iconImg.Source = cached;
                }
                else
                {
                    iconImg.Source = BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon(child.Url);
                    string targetUrl = child.Url;
                    _ = Task.Run(async () =>
                    {
                        var fetched = await BrowserAutofillAndVaultEngine.GetOrFetchFaviconAsync(targetUrl);
                        if (fetched != null)
                            _ = Dispatcher.BeginInvoke(() => iconImg.Source = fetched);
                    });
                }
            }
            Grid.SetColumn(iconImg, 0);
            grid.Children.Add(iconImg);

            string displayLabel = child.HasVisibleName
                ? child.Name
                : (child.IsFolder ? "(Ordner)" : BrowserAutofillAndVaultEngine.ExtractHost(child.Url));

            var titleTxt = new TextBlock
            {
                Text = displayLabel,
                FontSize = 12,
                FontWeight = child.IsFolder ? FontWeights.SemiBold : FontWeights.Normal,
                Foreground = (Brush)Resources["TextPrimaryBrush"],
                VerticalAlignment = VerticalAlignment.Center,
                TextTrimming = TextTrimming.CharacterEllipsis
            };
            Grid.SetColumn(titleTxt, 1);
            grid.Children.Add(titleTxt);

            if (child.IsFolder)
            {
                var subCountTxt = new TextBlock
                {
                    Text = "▸",
                    FontSize = 11,
                    Foreground = (Brush)Resources["TextSecondaryBrush"],
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(6, 0, 0, 0)
                };
                Grid.SetColumn(subCountTxt, 2);
                grid.Children.Add(subCountTxt);
            }

            itemBtn.Content = grid;

            var capturedChild = child;

            // Hover over subfolder inside open folder popup -> opens cascading side popup immediately!
            itemBtn.MouseEnter += (_, _) =>
            {
                if (!isSubfolderPopup)
                {
                    if (capturedChild.IsFolder)
                    {
                        OpenCascadingSubfolderPopup(capturedChild, itemBtn);
                    }
                    else
                    {
                        BookmarkSubfolderPopup.IsOpen = false;
                    }
                }
            };

            itemBtn.Click += async (_, ev) =>
            {
                ev.Handled = true;
                if (capturedChild.IsFolder)
                {
                    BookmarkSubfolderPopup.IsOpen = false;
                    _openFolderStack.Push(capturedChild);
                    RebuildFolderPopupItems();
                }
                else if (!string.IsNullOrWhiteSpace(capturedChild.Url))
                {
                    CloseAllFolderPopups();
                    if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
                        await CreateAndSelectTabAsync(capturedChild.Url);
                    else if (_activeTab != null)
                        NavigateTabTo(_activeTab, capturedChild.Url);
                }
            };

            itemBtn.MouseUp += async (_, ev) =>
            {
                if (ev.ChangedButton == MouseButton.Middle && !capturedChild.IsFolder && !string.IsNullOrWhiteSpace(capturedChild.Url))
                {
                    ev.Handled = true;
                    CloseAllFolderPopups();
                    await CreateAndSelectTabAsync(capturedChild.Url);
                }
            };

            itemBtn.MouseRightButtonUp += (_, ev) =>
            {
                ev.Handled = true;
                UIElement anchor = _lastFolderAnchorElement ?? BookmarkBarContainer;
                CloseAllFolderPopups();
                OpenBookmarkEditPopup(capturedChild, anchor);
            };

            // Drag & Drop inside folder popup to reorder items
            itemBtn.PreviewMouseLeftButtonDown += (_, ev) =>
            {
                _bookmarkDragStartPoint = ev.GetPosition(this);
                _draggingBookmarkNode = capturedChild;
            };

            itemBtn.PreviewMouseMove += (_, ev) =>
            {
                if (ev.LeftButton == MouseButtonState.Pressed && _draggingBookmarkNode == capturedChild)
                {
                    Point pos = ev.GetPosition(this);
                    if (Math.Abs(pos.X - _bookmarkDragStartPoint.X) > 6 || Math.Abs(pos.Y - _bookmarkDragStartPoint.Y) > 6)
                    {
                        _draggingBookmarkNode = null;
                        if (itemBtn.IsMouseCaptured) itemBtn.ReleaseMouseCapture();
                        DragDrop.DoDragDrop(itemBtn, capturedChild, DragDropEffects.Move);
                    }
                }
            };

            itemBtn.Drop += (_, ev) =>
            {
                ev.Handled = true;
                if (ev.Data.GetDataPresent(typeof(BookmarkNodeDto)) &&
                    ev.Data.GetData(typeof(BookmarkNodeDto)) is BookmarkNodeDto dragged &&
                    dragged.Id != capturedChild.Id)
                {
                    var (foundDragged, draggedParentList, _) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, dragged.Id);
                    var (_, targetParentList, _) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, capturedChild.Id);
                    if (foundDragged != null && draggedParentList != null && targetParentList != null)
                    {
                        draggedParentList.Remove(foundDragged);
                        int targetIdx = targetParentList.IndexOf(capturedChild);
                        if (targetIdx < 0) targetParentList.Add(foundDragged);
                        else targetParentList.Insert(targetIdx, foundDragged);

                        BrowserShieldAndSettings.SaveSettings(_settings);
                        RebuildBookmarksBarUi();
                        RebuildFolderPopupItems();
                    }
                }
            };

            return itemBtn;
        }

        private void OpenCascadingSubfolderPopup(BookmarkNodeDto subfolder, UIElement anchorItemBtn)
        {
            SubfolderPopupTitleTxt.Text = subfolder.HasVisibleName ? $"📁 {subfolder.Name}" : "📁 UNTERORDNER";
            SubfolderPopupItemsStack.Children.Clear();
            subfolder.Children ??= new List<BookmarkNodeDto>();

            if (subfolder.Children.Count == 0)
            {
                SubfolderPopupItemsStack.Children.Add(new TextBlock
                {
                    Text = "Leer",
                    FontSize = 11.5,
                    Foreground = (Brush)Resources["TextSecondaryBrush"],
                    Margin = new Thickness(8, 6, 8, 6)
                });
            }
            else
            {
                foreach (var subItem in subfolder.Children)
                {
                    SubfolderPopupItemsStack.Children.Add(CreatePopupBookmarkRowButton(subItem, isSubfolderPopup: true));
                }
            }

            BookmarkSubfolderPopup.IsOpen = false;
            BookmarkSubfolderPopup.PlacementTarget = anchorItemBtn;
            BookmarkSubfolderPopup.Placement = PlacementMode.Right;
            BookmarkSubfolderPopup.IsOpen = true;
        }

        private void FolderPopupBack_Click(object sender, RoutedEventArgs e)
        {
            if (_openFolderStack.Count > 1)
            {
                BookmarkSubfolderPopup.IsOpen = false;
                _openFolderStack.Pop();
                RebuildFolderPopupItems();
            }
        }

        private void EditCurrentlyOpenFolder_Click(object sender, RoutedEventArgs e)
        {
            if (_openFolderStack.Count == 0) return;
            var folder = _openFolderStack.Peek();
            UIElement anchor = _lastFolderAnchorElement ?? BookmarkBarContainer;
            CloseAllFolderPopups();
            OpenBookmarkEditPopup(folder, anchor);
        }

        private void AddCurrentPageToOpenFolder_Click(object sender, RoutedEventArgs e)
        {
            if (_openFolderStack.Count == 0) return;
            var folder = _openFolderStack.Peek();
            UIElement anchor = _lastFolderAnchorElement ?? BookmarkBarContainer;
            CloseAllFolderPopups();
            AddBookmarkForCurrentPage(openEditorImmediately: true, targetFolder: folder, anchor: anchor);
        }

        private void AddSubfolderToOpenFolder_Click(object sender, RoutedEventArgs e)
        {
            if (_openFolderStack.Count == 0) return;
            var parentFolder = _openFolderStack.Peek();
            parentFolder.Children ??= new List<BookmarkNodeDto>();
            var newSub = new BookmarkNodeDto
            {
                Name = "Neuer Ordner",
                Type = "folder",
                Children = new List<BookmarkNodeDto>()
            };
            parentFolder.Children.Add(newSub);
            BrowserShieldAndSettings.SaveSettings(_settings);
            RebuildBookmarksBarUi();

            UIElement anchor = _lastFolderAnchorElement ?? BookmarkBarContainer;
            CloseAllFolderPopups();
            OpenBookmarkEditPopup(newSub, anchor);
        }

        private void Window_PreviewMouseDown(object sender, MouseButtonEventArgs e)
        {
            if (!BookmarkFolderPopup.IsOpen) return;

            if (e.OriginalSource is DependencyObject dep)
            {
                if (IsDescendantOf(dep, BookmarkFolderPopupBorder) ||
                    IsDescendantOf(dep, BookmarkSubfolderPopupBorder) ||
                    IsDescendantOf(dep, BookmarksBarStack))
                {
                    return;
                }
            }

            CloseAllFolderPopups();
        }

        private void Window_Deactivated(object sender, EventArgs e)
        {
            CloseAllFolderPopups();
        }

        private static bool IsDescendantOf(DependencyObject? current, DependencyObject? targetAncestor)
        {
            if (current == null || targetAncestor == null) return false;
            while (current != null)
            {
                if (ReferenceEquals(current, targetAncestor)) return true;
                current = VisualTreeHelper.GetParent(current) ?? LogicalTreeHelper.GetParent(current);
            }
            return false;
        }

        // =====================================================================
        // RIGHT-CLICK BOOKMARK / FOLDER RENAME & MANAGER POPOVER
        // (Supports empty name "" for Icon-Only, Enter to confirm!)
        // =====================================================================
        private void BookmarkStarBtn_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            string url = _activeTab?.CurrentUrl ?? "";
            var existing = BrowserShieldAndSettings.FindBookmarkByUrl(_settings.BookmarkBarNodes, url);
            if (existing != null)
            {
                OpenBookmarkEditPopup(existing, BookmarkStarBtn);
            }
            else
            {
                AddBookmarkForCurrentPage(openEditorImmediately: true, targetFolder: null, anchor: BookmarkStarBtn);
            }
        }

        private void CreateNewRootFolder_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            var folder = new BookmarkNodeDto
            {
                Name = "Neuer Ordner",
                Type = "folder",
                Children = new List<BookmarkNodeDto>()
            };
            _settings.BookmarkBarNodes.Add(folder);
            BrowserShieldAndSettings.SaveSettings(_settings);
            RebuildBookmarksBarUi();
            OpenBookmarkEditPopup(folder, BookmarkBarContainer);
        }

        private void AddBookmarkForCurrentPage(bool openEditorImmediately, BookmarkNodeDto? targetFolder, UIElement anchor)
        {
            string url = _activeTab?.CurrentUrl ?? _settings.HomeUrl;
            if (string.IsNullOrWhiteSpace(url)) url = _settings.HomeUrl;
            string title = _activeTab?.Title ?? BrowserAutofillAndVaultEngine.ExtractHost(url);
            if (string.IsNullOrWhiteSpace(title) || title == "Laden...")
                title = BrowserAutofillAndVaultEngine.ExtractHost(url);

            var node = new BookmarkNodeDto
            {
                Name = title,
                Type = "url",
                Url = url
            };

            if (targetFolder != null)
            {
                targetFolder.Children ??= new List<BookmarkNodeDto>();
                targetFolder.Children.Add(node);
            }
            else
            {
                _settings.BookmarkBarNodes.Add(node);
            }

            BrowserShieldAndSettings.SaveSettings(_settings);
            RebuildBookmarksBarUi();

            if (openEditorImmediately)
                OpenBookmarkEditPopup(node, anchor);
        }

        private void OpenBookmarkEditPopup(BookmarkNodeDto node, UIElement anchorElement)
        {
            _editingNode = node;
            EditPopupHeaderTxt.Text = node.IsFolder ? "ORDNER UMBENENNEN / VERWALTEN" : "LESEZEICHEN UMBENENNEN / VERWALTEN";
            EditBookmarkUrlSection.Visibility = node.IsFolder ? Visibility.Collapsed : Visibility.Visible;
            EditOpenNewTabBtn.Visibility = node.IsFolder ? Visibility.Collapsed : Visibility.Visible;

            EditBookmarkNameBox.Text = node.Name;
            EditBookmarkUrlBox.Text = node.Url;

            // Populate Folder Destination Combo
            var (_, _, currentParentFolder) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, node.Id);
            string currentParentId = currentParentFolder?.Id ?? "ROOT_BAR";

            var folderTargets = BrowserShieldAndSettings.CollectAllFolderTargets(
                _settings.BookmarkBarNodes,
                excludeSubtreeId: node.IsFolder ? node.Id : null);

            EditBookmarkFolderCombo.ItemsSource = folderTargets
                .Select(t => new FolderComboOption
                {
                    FolderId = t.FolderId,
                    DisplayPath = t.DisplayPath,
                    TargetList = t.TargetList
                })
                .ToList();

            var selectedOption = EditBookmarkFolderCombo.Items.Cast<FolderComboOption>()
                .FirstOrDefault(x => string.Equals(x.FolderId, currentParentId, StringComparison.OrdinalIgnoreCase))
                ?? EditBookmarkFolderCombo.Items.Cast<FolderComboOption>().FirstOrDefault();

            EditBookmarkFolderCombo.SelectedItem = selectedOption;

            BookmarkEditPopup.PlacementTarget = anchorElement;
            BookmarkEditPopup.Placement = PlacementMode.Bottom;
            BookmarkEditPopup.IsOpen = true;

            Dispatcher.BeginInvoke(DispatcherPriority.Input, new Action(() =>
            {
                EditBookmarkNameBox.Focus();
                EditBookmarkNameBox.SelectAll();
            }));
        }

        private void EditBookmarkBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                CommitBookmarkEdit();
            }
            else if (e.Key == Key.Escape)
            {
                e.Handled = true;
                BookmarkEditPopup.IsOpen = false;
            }
        }

        private void ConfirmEditBookmark_Click(object sender, RoutedEventArgs e) => CommitBookmarkEdit();

        private void SetBookmarkIconOnly_Click(object sender, RoutedEventArgs e)
        {
            EditBookmarkNameBox.Text = "";
            CommitBookmarkEdit();
        }

        private void CommitBookmarkEdit()
        {
            if (_editingNode == null)
            {
                BookmarkEditPopup.IsOpen = false;
                return;
            }

            // Name is allowed to be empty ("") for icon-only display in the Bookmark Bar!
            _editingNode.Name = (EditBookmarkNameBox.Text ?? "").Trim();

            if (!_editingNode.IsFolder)
            {
                string editedUrl = (EditBookmarkUrlBox.Text ?? "").Trim();
                if (!string.IsNullOrEmpty(editedUrl))
                    _editingNode.Url = BrowserShieldAndSettings.NormalizeUserUrlOrSearch(editedUrl);
            }

            // Check if parent folder changed
            if (EditBookmarkFolderCombo.SelectedItem is FolderComboOption chosenFolder)
            {
                var (found, oldParentList, oldParentFolder) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, _editingNode.Id);
                string oldParentId = oldParentFolder?.Id ?? "ROOT_BAR";
                if (found != null && oldParentList != null && !string.Equals(oldParentId, chosenFolder.FolderId, StringComparison.OrdinalIgnoreCase))
                {
                    oldParentList.Remove(found);
                    chosenFolder.TargetList.Add(found);
                }
            }

            BrowserShieldAndSettings.SaveSettings(_settings);
            BookmarkEditPopup.IsOpen = false;
            RebuildBookmarksBarUi();
        }

        private void MoveEditedBookmarkLeft_Click(object sender, RoutedEventArgs e) => MoveEditedBookmarkByOffset(-1);

        private void MoveEditedBookmarkRight_Click(object sender, RoutedEventArgs e) => MoveEditedBookmarkByOffset(+1);

        private void MoveEditedBookmarkByOffset(int delta)
        {
            if (_editingNode == null) return;
            var (found, parentList, _) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, _editingNode.Id);
            if (found == null || parentList == null) return;

            int idx = parentList.IndexOf(found);
            int newIdx = Math.Clamp(idx + delta, 0, parentList.Count - 1);
            if (idx != newIdx)
            {
                parentList.RemoveAt(idx);
                parentList.Insert(newIdx, found);
                BrowserShieldAndSettings.SaveSettings(_settings);
                RebuildBookmarksBarUi();
            }
        }

        private async void OpenEditedBookmarkNewTab_Click(object sender, RoutedEventArgs e)
        {
            if (_editingNode != null && !_editingNode.IsFolder && !string.IsNullOrWhiteSpace(_editingNode.Url))
            {
                string url = _editingNode.Url;
                BookmarkEditPopup.IsOpen = false;
                await CreateAndSelectTabAsync(url);
            }
        }

        private void DeleteEditedBookmark_Click(object sender, RoutedEventArgs e)
        {
            if (_editingNode == null) return;
            var (found, parentList, _) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, _editingNode.Id);
            if (found != null && parentList != null)
            {
                parentList.Remove(found);
                BrowserShieldAndSettings.SaveSettings(_settings);
            }
            _editingNode = null;
            BookmarkEditPopup.IsOpen = false;
            RebuildBookmarksBarUi();
        }

        private void CreateNewFolderFromEditPopup_Click(object sender, RoutedEventArgs e)
        {
            var newFolder = new BookmarkNodeDto
            {
                Name = "Neuer Ordner",
                Type = "folder",
                Children = new List<BookmarkNodeDto>()
            };

            if (_editingNode != null)
            {
                var (found, oldParentList, _) = BrowserShieldAndSettings.FindNodeAndParent(_settings.BookmarkBarNodes, _editingNode.Id);
                if (found != null && oldParentList != null && !found.IsFolder)
                {
                    int idx = Math.Max(0, oldParentList.IndexOf(found));
                    oldParentList.Remove(found);
                    newFolder.Children.Add(found);
                    oldParentList.Insert(Math.Min(idx, oldParentList.Count), newFolder);
                }
                else
                {
                    _settings.BookmarkBarNodes.Add(newFolder);
                }
            }
            else
            {
                _settings.BookmarkBarNodes.Add(newFolder);
            }

            BrowserShieldAndSettings.SaveSettings(_settings);
            RebuildBookmarksBarUi();
            OpenBookmarkEditPopup(newFolder, BookmarkEditPopup.PlacementTarget ?? BookmarkBarContainer);
        }

        // =====================================================================
        // PASSWORDS, AUTOFILL & CHROME-SYNC DRAWER
        // =====================================================================
        private void ToggleVaultDrawer_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            bool makeVisible = VaultSideDrawer.Visibility != Visibility.Visible;
            VaultSideDrawer.Visibility = makeVisible ? Visibility.Visible : Visibility.Collapsed;
            if (makeVisible)
            {
                SyncVaultQuickHeaderWithCurrentTab();
                RebuildVaultLoginsListUi();
                LoadAutofillProfileIntoUi();
            }
        }

        private void SwitchVaultTabPasswords_Click(object sender, RoutedEventArgs e)
        {
            VaultPasswordsPanel.Visibility = Visibility.Visible;
            VaultAutofillPanel.Visibility = Visibility.Collapsed;
            VaultTabPasswordsBtn.Style = (Style)Resources["GoldPillBtn"];
            VaultTabAutofillBtn.Style = (Style)Resources["NavBtn"];
        }

        private void SwitchVaultTabAutofill_Click(object sender, RoutedEventArgs e)
        {
            VaultPasswordsPanel.Visibility = Visibility.Collapsed;
            VaultAutofillPanel.Visibility = Visibility.Visible;
            VaultTabPasswordsBtn.Style = (Style)Resources["NavBtn"];
            VaultTabAutofillBtn.Style = (Style)Resources["GoldPillBtn"];
            LoadAutofillProfileIntoUi();
        }

        private void SyncVaultQuickHeaderWithCurrentTab()
        {
            string url = _activeTab?.CurrentUrl ?? "";
            string host = BrowserAutofillAndVaultEngine.ExtractHost(url);
            VaultCurrentHostTxt.Text = string.IsNullOrEmpty(host) ? "Aktuelle Seite" : host;

            var match = _vault.Logins
                .Where(x => string.Equals(x.Host, host, StringComparison.OrdinalIgnoreCase))
                .OrderByDescending(x => x.HasPassword)
                .FirstOrDefault();

            if (match != null)
            {
                VaultNewUserBox.Text = match.Username;
                string dec = match.HasPassword ? BrowserAutofillAndVaultEngine.UnprotectSecret(match.EncryptedPassword) : "";
                VaultNewPassBox.Password = dec;
                VaultNewPassRevealBox.Text = dec;
            }
            else
            {
                VaultNewUserBox.Text = _vault.AutofillProfile.Email;
                VaultNewPassBox.Password = "";
                VaultNewPassRevealBox.Text = "";
            }
        }

        private void ToggleVaultNewPassEye_Click(object sender, RoutedEventArgs e)
        {
            _isNewPassRevealed = !_isNewPassRevealed;
            if (_isNewPassRevealed)
            {
                VaultNewPassRevealBox.Text = VaultNewPassBox.Password;
                VaultNewPassBox.Visibility = Visibility.Collapsed;
                VaultNewPassRevealBox.Visibility = Visibility.Visible;
                VaultNewPassRevealBox.Focus();
            }
            else
            {
                VaultNewPassBox.Password = VaultNewPassRevealBox.Text;
                VaultNewPassRevealBox.Visibility = Visibility.Collapsed;
                VaultNewPassBox.Visibility = Visibility.Visible;
                VaultNewPassBox.Focus();
            }
        }

        private void VaultNewPassBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                SaveCurrentPageLogin_Click(sender, e);
            }
        }

        private async void SaveCurrentPageLogin_Click(object sender, RoutedEventArgs e)
        {
            string url = _activeTab?.CurrentUrl ?? "https://mein-seelenfunke.de";
            string user = (VaultNewUserBox.Text ?? "").Trim();
            string pass = _isNewPassRevealed ? (VaultNewPassRevealBox.Text ?? "") : (VaultNewPassBox.Password ?? "");

            if (string.IsNullOrWhiteSpace(user)) return;
            BrowserAutofillAndVaultEngine.UpsertLoginCredential(_vault, url, user, pass);
            RebuildVaultLoginsListUi();

            if (_activeTab?.View.CoreWebView2 != null)
            {
                try
                {
                    string script = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(_vault, url, forceFillForms: true);
                    await _activeTab.View.CoreWebView2.ExecuteScriptAsync(script);
                }
                catch { }
            }
        }

        private async void QuickFillCurrentPage_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            if (_activeTab?.View.CoreWebView2 == null) return;
            try
            {
                string script = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(_vault, _activeTab.CurrentUrl, forceFillForms: true);
                await _activeTab.View.CoreWebView2.ExecuteScriptAsync(script);
            }
            catch { }
        }

        private void VaultSearchBox_TextChanged(object sender, TextChangedEventArgs e) => RebuildVaultLoginsListUi();

        private void RebuildVaultLoginsListUi()
        {
            if (VaultLoginsListStack == null) return;
            VaultLoginsListStack.Children.Clear();

            string q = (VaultSearchBox?.Text ?? "").Trim();
            string currentHost = BrowserAutofillAndVaultEngine.ExtractHost(_activeTab?.CurrentUrl);

            var items = _vault.Logins
                .Where(x => string.IsNullOrEmpty(q) ||
                            x.Host.Contains(q, StringComparison.OrdinalIgnoreCase) ||
                            x.Username.Contains(q, StringComparison.OrdinalIgnoreCase))
                .OrderByDescending(x => string.Equals(x.Host, currentHost, StringComparison.OrdinalIgnoreCase))
                .ThenByDescending(x => x.HasPassword)
                .ThenBy(x => x.Host)
                .Take(140)
                .ToList();

            foreach (var entry in items)
            {
                bool isCurrentSite = !string.IsNullOrEmpty(currentHost) && string.Equals(entry.Host, currentHost, StringComparison.OrdinalIgnoreCase);

                var card = new Border
                {
                    Background = (Brush)Resources["BgElevatedBrush"],
                    BorderBrush = isCurrentSite ? Solid("#C5A059") : (Brush)Resources["BorderSubtleBrush"],
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(9, 7, 9, 7),
                    Margin = new Thickness(0, 0, 0, 5)
                };

                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var favImg = new Image
                {
                    Width = 16,
                    Height = 16,
                    Margin = new Thickness(0, 0, 8, 0),
                    VerticalAlignment = VerticalAlignment.Center,
                    Source = BrowserAutofillAndVaultEngine.TryGetCachedFaviconSync(entry.OriginUrl)
                             ?? BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon(entry.OriginUrl)
                };
                Grid.SetColumn(favImg, 0);
                g.Children.Add(favImg);

                var infoStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                infoStack.Children.Add(new TextBlock
                {
                    Text = entry.Host,
                    FontSize = 11.5,
                    FontWeight = FontWeights.Bold,
                    Foreground = isCurrentSite ? Solid("#C5A059") : (Brush)Resources["TextPrimaryBrush"],
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                infoStack.Children.Add(new TextBlock
                {
                    Text = $"{entry.Username}  ({(entry.HasPassword ? "••••••" : "Chrome-Login")})",
                    FontSize = 10.5,
                    Foreground = (Brush)Resources["TextSecondaryBrush"],
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                Grid.SetColumn(infoStack, 1);
                g.Children.Add(infoStack);

                var actions = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                var captured = entry;

                var fillBtn = new Button
                {
                    Content = "⚡",
                    Width = 26,
                    Height = 25,
                    Padding = new Thickness(0),
                    Margin = new Thickness(0, 0, 3, 0),
                    Style = (Style)Resources["NavBtn"],
                    ToolTip = "Seite öffnen / ausfüllen"
                };
                fillBtn.Click += async (_, _) =>
                {
                    VaultNewUserBox.Text = captured.Username;
                    string plain = captured.HasPassword ? BrowserAutofillAndVaultEngine.UnprotectSecret(captured.EncryptedPassword) : "";
                    VaultNewPassBox.Password = plain;
                    VaultNewPassRevealBox.Text = plain;

                    if (_activeTab != null)
                    {
                        string currentTabHost = BrowserAutofillAndVaultEngine.ExtractHost(_activeTab.CurrentUrl);
                        if (!string.Equals(currentTabHost, captured.Host, StringComparison.OrdinalIgnoreCase))
                        {
                            NavigateTabTo(_activeTab, captured.OriginUrl);
                        }
                        else if (_activeTab.View.CoreWebView2 != null)
                        {
                            string script = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(_vault, _activeTab.CurrentUrl, forceFillForms: true);
                            await _activeTab.View.CoreWebView2.ExecuteScriptAsync(script);
                        }
                    }
                };
                actions.Children.Add(fillBtn);

                var copyUserBtn = new Button
                {
                    Content = "👤",
                    Width = 26,
                    Height = 25,
                    Padding = new Thickness(0),
                    Margin = new Thickness(0, 0, 3, 0),
                    Style = (Style)Resources["NavBtn"],
                    ToolTip = "Benutzername kopieren"
                };
                copyUserBtn.Click += (_, _) =>
                {
                    try { Clipboard.SetText(captured.Username); } catch { }
                };
                actions.Children.Add(copyUserBtn);

                if (captured.HasPassword)
                {
                    var copyPassBtn = new Button
                    {
                        Content = "🔑",
                        Width = 26,
                        Height = 25,
                        Padding = new Thickness(0),
                        Margin = new Thickness(0, 0, 3, 0),
                        Style = (Style)Resources["NavBtn"],
                        ToolTip = "Passwort kopieren"
                    };
                    copyPassBtn.Click += (_, _) =>
                    {
                        try
                        {
                            string plain = BrowserAutofillAndVaultEngine.UnprotectSecret(captured.EncryptedPassword);
                            if (!string.IsNullOrEmpty(plain)) Clipboard.SetText(plain);
                        }
                        catch { }
                    };
                    actions.Children.Add(copyPassBtn);
                }

                var delBtn = new Button
                {
                    Content = "🗑",
                    Width = 25,
                    Height = 25,
                    Padding = new Thickness(0),
                    Style = (Style)Resources["FlatBookmarkBtn"],
                    ToolTip = "Eintrag löschen"
                };
                delBtn.Click += (_, _) =>
                {
                    _vault.Logins.Remove(captured);
                    BrowserAutofillAndVaultEngine.SaveVault(_vault);
                    RebuildVaultLoginsListUi();
                };
                actions.Children.Add(delBtn);

                Grid.SetColumn(actions, 2);
                g.Children.Add(actions);

                card.Child = g;
                VaultLoginsListStack.Children.Add(card);
            }
        }

        private void SyncFromChromeNow_Click(object sender, RoutedEventArgs e)
        {
            var freshTree = BrowserAutofillAndVaultEngine.ImportChromeBookmarkTree();
            if (freshTree.Count > 0)
            {
                _settings.BookmarkBarNodes = freshTree;
                _settings.MigratedChromeBookmarkTreeV3 = true;
                _settings.MigratedChromeBookmarkTreeV4 = true;
                BrowserShieldAndSettings.SaveSettings(_settings);
                RebuildBookmarksBarUi();
            }

            BrowserAutofillAndVaultEngine.ImportChromeAutofillAndLogins(_vault);
            BrowserAutofillAndVaultEngine.SaveVault(_vault);
            LoadAutofillProfileIntoUi();
            RebuildVaultLoginsListUi();
        }

        private void ImportPasswordsCsv_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Chrome Passwörter CSV importieren",
                Filter = "CSV-Dateien (*.csv)|*.csv|Alle Dateien (*.*)|*.*"
            };
            if (dlg.ShowDialog(this) == true)
            {
                BrowserAutofillAndVaultEngine.ImportPasswordsFromCsvFile(_vault, dlg.FileName);
                RebuildVaultLoginsListUi();
                SyncVaultQuickHeaderWithCurrentTab();
            }
        }

        private void LoadAutofillProfileIntoUi()
        {
            if (AfFirstNameBox == null) return;
            var p = _vault.AutofillProfile;
            AfFirstNameBox.Text = p.FirstName;
            AfLastNameBox.Text = p.LastName;
            AfCompanyBox.Text = p.Company;
            AfStreetBox.Text = p.Street;
            AfZipBox.Text = p.Zip;
            AfCityBox.Text = p.City;
            AfEmailBox.Text = p.Email;
            AfPhoneBox.Text = p.Phone;
        }

        private void SaveAutofillProfile_Click(object sender, RoutedEventArgs e)
        {
            var p = _vault.AutofillProfile;
            p.FirstName = (AfFirstNameBox.Text ?? "").Trim();
            p.LastName = (AfLastNameBox.Text ?? "").Trim();
            p.FullName = $"{p.FirstName} {p.LastName}".Trim();
            p.Company = (AfCompanyBox.Text ?? "").Trim();
            p.Street = (AfStreetBox.Text ?? "").Trim();
            p.Zip = (AfZipBox.Text ?? "").Trim();
            p.City = (AfCityBox.Text ?? "").Trim();
            p.Email = (AfEmailBox.Text ?? "").Trim();
            p.Phone = (AfPhoneBox.Text ?? "").Trim();

            BrowserAutofillAndVaultEngine.SaveVault(_vault);
        }

        private void FillAddressOnCurrentPage_Click(object sender, RoutedEventArgs e)
        {
            SaveAutofillProfile_Click(sender, e);
            QuickFillCurrentPage_Click(sender, e);
        }

        // =====================================================================
        // AIRLOCK / FORTRESS MODE & NAVIGATION SHORTCUTS
        // =====================================================================
        private void RefreshAirlockButtonUi()
        {
            if (BrowserShieldAndSettings.IsAirlockTemporarilyOpen(_settings, out TimeSpan rem))
            {
                AirlockModeBtn.Content = _settings.StrictFortressMode
                    ? $"⏱ {rem.Minutes:00}:{rem.Seconds:00}"
                    : "🔓 OFFEN";
            }
            else
            {
                AirlockModeBtn.Content = "🔒 FESTUNG";
            }
        }

        private void ToggleAirlockMode_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            if (!_settings.StrictFortressMode)
            {
                _settings.StrictFortressMode = true;
                _settings.AirlockOpenUntilUtc = null;
            }
            else
            {
                _settings.StrictFortressMode = false;
                _settings.AirlockOpenUntilUtc = null;
                if (_activeTab != null && !string.IsNullOrEmpty(_activeTab.PendingBlockedUrl))
                    NavigateTabTo(_activeTab, _activeTab.PendingBlockedUrl);
            }
            BrowserShieldAndSettings.SaveSettings(_settings);
            RefreshAirlockButtonUi();
        }

        private void AllowBlockedDomainPermanently_Click(object sender, RoutedEventArgs e)
        {
            if (_activeTab == null || string.IsNullOrWhiteSpace(_activeTab.PendingBlockedHost)) return;
            string dom = _activeTab.PendingBlockedHost.Trim().ToLowerInvariant();
            if (!_settings.CustomAllowedDomains.Contains(dom, StringComparer.OrdinalIgnoreCase))
            {
                _settings.CustomAllowedDomains.Add(dom);
                BrowserShieldAndSettings.SaveSettings(_settings);
            }
            if (!string.IsNullOrEmpty(_activeTab.PendingBlockedUrl))
                NavigateTabTo(_activeTab, _activeTab.PendingBlockedUrl);
        }

        private void Open30MinAirlockFromGate_Click(object sender, RoutedEventArgs e)
        {
            _settings.StrictFortressMode = true;
            _settings.AirlockOpenUntilUtc = DateTime.UtcNow.AddMinutes(30);
            BrowserShieldAndSettings.SaveSettings(_settings);
            RefreshAirlockButtonUi();
            if (_activeTab != null && !string.IsNullOrEmpty(_activeTab.PendingBlockedUrl))
                NavigateTabTo(_activeTab, _activeTab.PendingBlockedUrl);
        }

        private async void NewTab_Click(object sender, RoutedEventArgs e) => await CreateAndSelectTabAsync(_settings.HomeUrl);

        private void Back_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            if (_activeTab?.View.CoreWebView2?.CanGoBack == true)
                _activeTab.View.CoreWebView2.GoBack();
        }

        private void Forward_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            if (_activeTab?.View.CoreWebView2?.CanGoForward == true)
                _activeTab.View.CoreWebView2.GoForward();
        }

        private void Reload_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            _activeTab?.View.CoreWebView2?.Reload();
        }

        private void Home_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            if (_activeTab != null)
                NavigateTabTo(_activeTab, _settings.HomeUrl);
        }

        private void AddressBox_GotFocus(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            AddressBox.SelectAll();
        }

        private void AddressBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter && _activeTab != null)
            {
                e.Handled = true;
                NavigateTabTo(_activeTab, AddressBox.Text);
            }
        }

        private void OpenDevTools_Click(object sender, RoutedEventArgs e)
        {
            CloseAllFolderPopups();
            _activeTab?.View.CoreWebView2?.OpenDevToolsWindow();
        }

        private async void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape)
            {
                if (BookmarkFolderPopup.IsOpen || BookmarkSubfolderPopup.IsOpen || BookmarkEditPopup.IsOpen)
                {
                    CloseAllFolderPopups();
                    BookmarkEditPopup.IsOpen = false;
                    e.Handled = true;
                    return;
                }
            }
            else if (e.Key == Key.F12)
            {
                _activeTab?.View.CoreWebView2?.OpenDevToolsWindow();
                e.Handled = true;
            }
            else if (e.Key == Key.F5)
            {
                _activeTab?.View.CoreWebView2?.Reload();
                e.Handled = true;
            }
            else if (e.Key == Key.T && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                await CreateAndSelectTabAsync(_settings.HomeUrl);
                e.Handled = true;
            }
            else if (e.Key == Key.W && (Keyboard.Modifiers & ModifierKeys.Control) != 0 && _activeTab != null)
            {
                CloseTab(_activeTab);
                e.Handled = true;
            }
            else if (e.Key == Key.L && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                AddressBox.Focus();
                AddressBox.SelectAll();
                e.Handled = true;
            }
            else if (e.Key == Key.D && (Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                BookmarkStarBtn_Click(this, new RoutedEventArgs());
                e.Handled = true;
            }
        }

        private static SolidColorBrush Solid(string hex) =>
            new((Color)ColorConverter.ConvertFromString(hex));
    }
}

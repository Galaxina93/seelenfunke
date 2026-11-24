using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Shapes;
using System.Windows.Threading;

namespace Funkenwerk
{
    public partial class MainWindow
    {
        private string _launcherSearchQuery = "";
        private DispatcherTimer? _launcherLiveDotsTimer;
        private readonly Dictionary<string, Ellipse> _launcherRunningDots = new(StringComparer.OrdinalIgnoreCase);

        private void EnsureLauncherInitialized()
        {
            if (_launcherLiveDotsTimer == null)
            {
                _launcherLiveDotsTimer = new DispatcherTimer
                {
                    Interval = TimeSpan.FromSeconds(3)
                };
                _launcherLiveDotsTimer.Tick += (_, _) =>
                {
                    if (LauncherViewGrid != null && LauncherViewGrid.Visibility == Visibility.Visible)
                    {
                        RefreshLauncherRunningDots();
                    }
                };
                _launcherLiveDotsTimer.Start();
            }
            RenderLauncherDeck();
        }

        private void RefreshLauncherRunningDots()
        {
            foreach (var spec in CollectionModuleRegistry.Modules)
            {
                if (_launcherRunningDots.TryGetValue(spec.Key, out var dot))
                {
                    bool running = CollectionModuleRegistry.IsModuleRunning(spec);
                    dot.Visibility = running ? Visibility.Visible : Visibility.Collapsed;
                }
            }
        }

        private void RenderLauncherDeck()
        {
            if (LauncherTilesWrapPanel == null) return;

            LauncherTilesWrapPanel.Children.Clear();
            _launcherRunningDots.Clear();

            string tileBgHex = _isDarkMode ? "#141720" : "#FFFFFF";
            string tileHoverHex = _isDarkMode ? "#1E222F" : "#FDF8ED";
            string tileBorderHex = _isDarkMode ? "#252938" : "#E2E8F0";
            string titleFgHex = _isDarkMode ? "#F9FAFB" : "#111827";
            string iconBoxBgHex = _isDarkMode ? "#1C1A17" : "#FAF5EA";

            if (LauncherSearchBorder != null)
            {
                LauncherSearchBorder.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(_isDarkMode ? "#141720" : "#FFFFFF"));
                LauncherSearchBorder.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString(_isDarkMode ? "#2E3346" : "#CBD5E1"));
            }
            if (LauncherSearchBox != null)
            {
                LauncherSearchBox.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(titleFgHex));
            }
            if (LauncherSearchPlaceholderTxt != null)
            {
                LauncherSearchPlaceholderTxt.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(_isDarkMode ? "#6B7280" : "#9CA3AF"));
                LauncherSearchPlaceholderTxt.Visibility = string.IsNullOrEmpty(_launcherSearchQuery) ? Visibility.Visible : Visibility.Collapsed;
            }
            if (LauncherClearSearchBtn != null)
            {
                LauncherClearSearchBtn.Visibility = string.IsNullOrEmpty(_launcherSearchQuery) ? Visibility.Collapsed : Visibility.Visible;
            }

            var currentSession = FunkenwerkSharedSession.Load();
            var ranked = CollectionModuleRegistry.FilterAndRankModules(_launcherSearchQuery)
                .Where(m => FunkenwerkSharedSession.IsModuleAllowed(m.Key, currentSession))
                .ToList();
            if (LauncherEmptyHintTxt != null)
            {
                LauncherEmptyHintTxt.Visibility = ranked.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            }

            bool highlightTop = !string.IsNullOrWhiteSpace(_launcherSearchQuery) && ranked.Count > 0;

            for (int idx = 0; idx < ranked.Count; idx++)
            {
                var spec = ranked[idx];
                bool isTopMatch = highlightTop && idx == 0;
                bool isRunning = CollectionModuleRegistry.IsModuleRunning(spec);

                string exePathForVer = CollectionModuleRegistry.ResolveModuleExePath(spec);
                string verLabel = FunkenwerkVersioning.GetInstalledBinaryVersionLabel(exePathForVer);

                var tile = new Border
                {
                    Width = 172,
                    Height = 148,
                    Margin = new Thickness(9),
                    CornerRadius = new CornerRadius(16),
                    Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isTopMatch ? tileHoverHex : tileBgHex)),
                    BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isTopMatch ? "#C5A059" : tileBorderHex)),
                    BorderThickness = new Thickness(isTopMatch ? 1.6 : 1.0),
                    Cursor = Cursors.Hand,
                    ToolTip = string.IsNullOrEmpty(verLabel) ? spec.FullName : $"{spec.FullName} · {verLabel}"
                };

                var innerGrid = new Grid();

                // Subtle live running indicator dot (top-right, zero text clutter)
                var liveDot = new Ellipse
                {
                    Width = 9,
                    Height = 9,
                    Fill = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981")),
                    HorizontalAlignment = HorizontalAlignment.Right,
                    VerticalAlignment = VerticalAlignment.Top,
                    Margin = new Thickness(0, 12, 12, 0),
                    Visibility = isRunning ? Visibility.Visible : Visibility.Collapsed,
                    ToolTip = "Geöffnet (Klick holt Fenster nach vorn)"
                };
                _launcherRunningDots[spec.Key] = liveDot;
                innerGrid.Children.Add(liveDot);

                var centerStack = new StackPanel
                {
                    VerticalAlignment = VerticalAlignment.Center,
                    HorizontalAlignment = HorizontalAlignment.Center
                };

                UIElement iconContent = BuildModuleTileIconElement(spec);
                centerStack.Children.Add(new Border
                {
                    Width = 56,
                    Height = 56,
                    HorizontalAlignment = HorizontalAlignment.Center,
                    Margin = new Thickness(0, 0, 0, 12),
                    Child = iconContent
                });

                centerStack.Children.Add(new TextBlock
                {
                    Text = spec.ShortTitle,
                    FontSize = 14.5,
                    FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(titleFgHex)),
                    HorizontalAlignment = HorizontalAlignment.Center,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });

                innerGrid.Children.Add(centerStack);
                tile.Child = innerGrid;

                var capturedSpec = spec;
                string normalBg = isTopMatch ? tileHoverHex : tileBgHex;
                string normalBorder = isTopMatch ? "#C5A059" : tileBorderHex;
                double normalThick = isTopMatch ? 1.6 : 1.0;

                tile.MouseEnter += (_, _) =>
                {
                    tile.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(tileHoverHex));
                    tile.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"));
                    tile.BorderThickness = new Thickness(1.6);
                };
                tile.MouseLeave += (_, _) =>
                {
                    tile.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(normalBg));
                    tile.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString(normalBorder));
                    tile.BorderThickness = new Thickness(normalThick);
                };
                tile.MouseLeftButtonUp += (_, _) =>
                {
                    PlaySoundActionPop();
                    SyncSharedSessionSnapshot();
                    CollectionModuleRegistry.LaunchOrFocusModule(capturedSpec.Key);
                    _ = Task.Delay(600).ContinueWith(_ => Dispatcher.Invoke(RefreshLauncherRunningDots));
                };

                LauncherTilesWrapPanel.Children.Add(tile);
            }
        }

        private static UIElement BuildModuleTileIconElement(CollectionModuleSpec spec)
        {
            try
            {
                string iconPath = CollectionModuleRegistry.ResolveModuleIconPath(spec);
                if (!string.IsNullOrWhiteSpace(iconPath) && File.Exists(iconPath))
                {
                    var bmp = new BitmapImage();
                    bmp.BeginInit();
                    bmp.CacheOption = BitmapCacheOption.OnLoad;
                    bmp.DecodePixelWidth = 128;
                    bmp.UriSource = new Uri(iconPath, UriKind.Absolute);
                    bmp.EndInit();
                    bmp.Freeze();
                    var img = new Image
                    {
                        Source = bmp,
                        Width = 54,
                        Height = 54,
                        Stretch = Stretch.Uniform,
                        SnapsToDevicePixels = true,
                        UseLayoutRounding = true,
                        HorizontalAlignment = HorizontalAlignment.Center,
                        VerticalAlignment = VerticalAlignment.Center
                    };
                    RenderOptions.SetBitmapScalingMode(img, BitmapScalingMode.HighQuality);
                    return img;
                }
            }
            catch { }

            var vb = new Viewbox { Width = 26, Height = 26 };
            var canvas = new Canvas { Width = 24, Height = 24 };
            canvas.Children.Add(new System.Windows.Shapes.Path
            {
                Data = Geometry.Parse(spec.VectorPathData),
                Stroke = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")),
                StrokeThickness = 2.0,
                StrokeStartLineCap = PenLineCap.Round,
                StrokeEndLineCap = PenLineCap.Round,
                StrokeLineJoin = PenLineJoin.Round,
                Fill = Brushes.Transparent
            });
            vb.Child = canvas;
            return vb;
        }

        private void LauncherSearchBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            _launcherSearchQuery = LauncherSearchBox?.Text ?? "";
            RenderLauncherDeck();
        }

        private void LauncherClearSearch_Click(object sender, RoutedEventArgs e)
        {
            if (LauncherSearchBox != null)
            {
                LauncherSearchBox.Text = "";
                LauncherSearchBox.Focus();
            }
        }

        private void LauncherSearchBox_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape && !string.IsNullOrEmpty(_launcherSearchQuery))
            {
                e.Handled = true;
                if (LauncherSearchBox != null) LauncherSearchBox.Text = "";
            }
            else if (e.Key == Key.Enter)
            {
                e.Handled = true;
                var currentSession = FunkenwerkSharedSession.Load();
                var ranked = CollectionModuleRegistry.FilterAndRankModules(_launcherSearchQuery)
                    .Where(m => FunkenwerkSharedSession.IsModuleAllowed(m.Key, currentSession))
                    .ToList();
                if (ranked.Count > 0)
                {
                    PlaySoundActionPop();
                    SyncSharedSessionSnapshot();
                    CollectionModuleRegistry.LaunchOrFocusModule(ranked[0].Key);
                    _ = Task.Delay(600).ContinueWith(_ => Dispatcher.Invoke(RefreshLauncherRunningDots));
                }
            }
        }

        private void SyncSharedSessionSnapshot(string? verifiedPassword = null)
        {
            try
            {
                string email = (_layoutSettings.SavedAuthEmail ?? "").Trim();

                if (!string.IsNullOrEmpty(verifiedPassword))
                {
                    FunkenwerkSharedSession.SaveVerifiedOnlineSession(_apiBaseUrl, _apiToken, email, verifiedPassword);
                    var s = FunkenwerkSharedSession.Load();
                    s.IsDarkMode = _isDarkMode;
                    s.IsSessionLocked = _layoutSettings.IsSessionLocked;
                    FunkenwerkSharedSession.Save(s);
                }
                else
                {
                    var s = FunkenwerkSharedSession.Load();
                    if (!string.IsNullOrWhiteSpace(_apiBaseUrl)) s.ApiBaseUrl = _apiBaseUrl;
                    if (!string.IsNullOrWhiteSpace(_apiToken))
                    {
                        s.BearerToken = _apiToken;
                        s.HasVerifiedOnlineOnce = true;
                    }
                    else if (string.Equals((s.ApiBaseUrl ?? "").TrimEnd('/'), (_apiBaseUrl ?? "").TrimEnd('/'), StringComparison.OrdinalIgnoreCase))
                    {
                        s.BearerToken = "";
                    }
                    if (!string.IsNullOrWhiteSpace(email))
                    {
                        s.UserEmail = email;
                    }
                    s.IsDarkMode = _isDarkMode;
                    s.IsSessionLocked = _layoutSettings.IsSessionLocked;
                    FunkenwerkSharedSession.Save(s);
                }
            }
            catch { }
        }

        // =====================================================================
        // NAMED PIPE IPC FORWARDERS TO PLAYER & SMARTHOME MODULES
        // =====================================================================
        private static async Task<(bool Ok, string Summary, string RawJson)> SendModulePipeCommandAsync(
            string pipeName,
            string moduleKeyToAutoLaunch,
            object payloadObj)
        {
            string reqJson = JsonSerializer.Serialize(payloadObj);
            for (int attempt = 0; attempt < 2; attempt++)
            {
                try
                {
                    using var client = new NamedPipeClientStream(".", pipeName, PipeDirection.InOut, PipeOptions.Asynchronous);
                    await client.ConnectAsync(attempt == 0 ? 500 : 2800).ConfigureAwait(false);
                    using var writer = new StreamWriter(client, new UTF8Encoding(false), 4096, leaveOpen: true) { AutoFlush = true };
                    using var reader = new StreamReader(client, Encoding.UTF8, false, 4096, leaveOpen: true);

                    await writer.WriteLineAsync(reqJson).ConfigureAwait(false);
                    string? respLine = await reader.ReadLineAsync().ConfigureAwait(false);
                    if (!string.IsNullOrWhiteSpace(respLine))
                    {
                        using var doc = JsonDocument.Parse(respLine);
                        bool ok = doc.RootElement.TryGetProperty("ok", out var okEl) && okEl.ValueKind == JsonValueKind.True;
                        string summary = doc.RootElement.TryGetProperty("summary", out var sEl) && sEl.ValueKind == JsonValueKind.String
                            ? (sEl.GetString() ?? "Befehl ausgeführt.")
                            : "Befehl ausgeführt.";
                        return (ok, summary, respLine);
                    }
                }
                catch
                {
                    if (attempt == 0)
                    {
                        var launch = CollectionModuleRegistry.LaunchOrFocusModule(moduleKeyToAutoLaunch);
                        if (!launch.Ok)
                        {
                            return (false, launch.Message, "");
                        }
                        await Task.Delay(900).ConfigureAwait(false);
                    }
                }
            }
            return (false, $"Modul '{moduleKeyToAutoLaunch}' hat auf den lokalen Pipe-Befehl nicht geantwortet.", "");
        }
    }
}

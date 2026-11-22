using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Runtime.CompilerServices;
#if !FUNKENWERK_NO_WPF
using System.ComponentModel;
using System.Windows;
using WpfApp = System.Windows.Application;
using WpfWindow = System.Windows.Window;
using WpfControl = System.Windows.Controls.Control;
using WpfPanel = System.Windows.Controls.Panel;
using WpfGrid = System.Windows.Controls.Grid;
using WpfBorder = System.Windows.Controls.Border;
using WpfTextBlock = System.Windows.Controls.TextBlock;
using WpfColumnDefinition = System.Windows.Controls.ColumnDefinition;
using WpfRowDefinition = System.Windows.Controls.RowDefinition;
using WpfBrush = System.Windows.Media.Brush;
using WpfSolidBrush = System.Windows.Media.SolidColorBrush;
using WpfColor = System.Windows.Media.Color;
using WpfColorConverter = System.Windows.Media.ColorConverter;
using WpfHorizontal = System.Windows.HorizontalAlignment;
using WpfVertical = System.Windows.VerticalAlignment;
#endif

namespace Funkenwerk
{
    /// <summary>
    /// Einheitliche Versionierungs-Engine fuer Funkenwerk und alle Desktop-Module
    /// analog zur Android-App (SemVer versionName + streng monotoner Integer Build-Code / versionCode).
    /// Blendet die aktive Version automatisch dezent unten rechts in jedem Hauptfenster ein.
    /// </summary>
    public static class FunkenwerkVersioning
    {
        public static string ProductName { get; }
        public static string VersionName { get; }
        public static int VersionCode { get; }
        public static string ShortBadgeText => $"v{VersionName} · Build {VersionCode}";
        public static string SentinelVersionString => $"{VersionName} ({VersionCode})";
        public static string FullTooltipText => $"{ProductName} · Version {VersionName} (Build {VersionCode})";

        static FunkenwerkVersioning()
        {
            var asm = Assembly.GetEntryAssembly() ?? typeof(FunkenwerkVersioning).Assembly;
            ProductName = asm.GetCustomAttribute<AssemblyProductAttribute>()?.Product
                          ?? asm.GetName().Name
                          ?? "Funkenwerk";

            var (vName, vCode) = ExtractAssemblyVersion(asm);
            VersionName = vName;
            VersionCode = vCode;
        }

        public static (string VersionName, int VersionCode) ExtractAssemblyVersion(Assembly asm)
        {
            string info = asm.GetCustomAttribute<AssemblyInformationalVersionAttribute>()?.InformationalVersion ?? "";
            string fileVer = asm.GetCustomAttribute<AssemblyFileVersionAttribute>()?.Version ?? "";
            Version? asmVer = asm.GetName().Version;

            int buildCode = 1;
            string semVer = "";

            if (!string.IsNullOrWhiteSpace(info))
            {
                int plusIdx = info.IndexOf('+');
                semVer = (plusIdx >= 0 ? info.Substring(0, plusIdx) : info).Trim();
                if (plusIdx >= 0 && plusIdx + 1 < info.Length)
                {
                    string meta = info.Substring(plusIdx + 1).Trim();
                    if (meta.StartsWith("b", StringComparison.OrdinalIgnoreCase))
                        meta = meta.Substring(1);
                    int gitSep = meta.IndexOf('.');
                    if (gitSep > 0) meta = meta.Substring(0, gitSep);
                    if (int.TryParse(meta, out int parsedMeta) && parsedMeta > 0)
                        buildCode = parsedMeta;
                }
            }

            if (!string.IsNullOrWhiteSpace(fileVer) && Version.TryParse(fileVer, out var fv))
            {
                if (string.IsNullOrWhiteSpace(semVer))
                    semVer = $"{Math.Max(0, fv.Major)}.{Math.Max(0, fv.Minor)}.{Math.Max(0, fv.Build)}";
                if (fv.Revision > 0)
                    buildCode = fv.Revision;
            }

            if (string.IsNullOrWhiteSpace(semVer) && asmVer != null)
            {
                semVer = $"{Math.Max(0, asmVer.Major)}.{Math.Max(0, asmVer.Minor)}.{Math.Max(0, asmVer.Build)}";
                if (asmVer.Revision > 0 && buildCode <= 1)
                    buildCode = asmVer.Revision;
            }

            if (string.IsNullOrWhiteSpace(semVer))
                semVer = "1.0.0";

            return (semVer, Math.Max(1, buildCode));
        }

        public static string GetInstalledBinaryVersionLabel(string? exePath)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(exePath)) return "";
                string target = exePath;
                string dllCandidate = Path.ChangeExtension(exePath, ".dll");
                if (File.Exists(dllCandidate))
                    target = dllCandidate;
                if (!File.Exists(target)) return "";

                var fvi = FileVersionInfo.GetVersionInfo(target);
                string prodVer = fvi.ProductVersion ?? "";
                int plusIdx = prodVer.IndexOf('+');
                string cleanSem = (plusIdx >= 0 ? prodVer.Substring(0, plusIdx) : prodVer).Trim();
                if (string.IsNullOrWhiteSpace(cleanSem))
                    cleanSem = $"{fvi.FileMajorPart}.{fvi.FileMinorPart}.{fvi.FileBuildPart}";

                int code = fvi.FilePrivatePart > 0 ? fvi.FilePrivatePart : 1;
                if (plusIdx >= 0 && plusIdx + 1 < prodVer.Length)
                {
                    string meta = prodVer.Substring(plusIdx + 1).Trim().TrimStart('b', 'B');
                    int dot = meta.IndexOf('.');
                    if (dot > 0) meta = meta.Substring(0, dot);
                    if (int.TryParse(meta, out int mCode) && mCode > 0) code = mCode;
                }

                return $"v{cleanSem} · Build {code}";
            }
            catch
            {
                return "";
            }
        }

#if !FUNKENWERK_NO_WPF
        private static int s_hookRegistered;

        private static readonly DependencyProperty IsVersionAttachedProperty =
            DependencyProperty.RegisterAttached("IsVersionAttached", typeof(bool), typeof(FunkenwerkVersioning), new PropertyMetadata(false));

        private static readonly DependencyProperty SyncActionProperty =
            DependencyProperty.RegisterAttached("SyncAction", typeof(Action), typeof(FunkenwerkVersioning), new PropertyMetadata(null));

        [ModuleInitializer]
        internal static void InitWindowVersionHook()
        {
            try
            {
                if (System.Threading.Interlocked.Exchange(ref s_hookRegistered, 1) == 1) return;
                EventManager.RegisterClassHandler(
                    typeof(WpfWindow),
                    FrameworkElement.LoadedEvent,
                    new RoutedEventHandler(OnAnyWindowLoaded));
            }
            catch { }
        }

        private static void OnAnyWindowLoaded(object sender, RoutedEventArgs e)
        {
            if (sender is not WpfWindow win) return;
            bool isMainWindow = ReferenceEquals(WpfApp.Current?.MainWindow, win) ||
                                string.Equals(win.GetType().Name, "MainWindow", StringComparison.Ordinal);
            if (!isMainWindow) return;
            EnsureAttached(win);
        }

        public static void EnsureAttached(WpfWindow win)
        {
            if (win == null) return;
            if ((bool)win.GetValue(IsVersionAttachedProperty))
            {
                SyncWindowTheme(win);
                return;
            }
            win.SetValue(IsVersionAttachedProperty, true);

            string asmName = (Assembly.GetEntryAssembly() ?? win.GetType().Assembly).GetName().Name ?? "";
            bool isFullBleedShell = string.Equals(asmName, "Funkenwerk", StringComparison.OrdinalIgnoreCase) ||
                                    string.Equals(asmName, "FunkenwerkOS", StringComparison.OrdinalIgnoreCase);

            WpfGrid? rootGrid = win.Content as WpfGrid ?? (win.Content as WpfBorder)?.Child as WpfGrid;

            // Mode A: Full-bleed shell (Funkenwerk main suite & Funkenwerk OS) -> sleek bottom-right micro-pill
            if (isFullBleedShell && rootGrid != null)
            {
                var txt = CreateBadgeTextBlock(fontSize: 10.0);
                var pill = new WpfBorder
                {
                    Name = "FunkenwerkVersionCornerPill",
                    Tag = "FixedDarkOverlay",
                    CornerRadius = new CornerRadius(5),
                    Padding = new Thickness(6, 1.5, 6, 1.5),
                    Margin = new Thickness(0, 0, 10, 4),
                    HorizontalAlignment = WpfHorizontal.Right,
                    VerticalAlignment = WpfVertical.Bottom,
                    IsHitTestVisible = false,
                    Child = txt
                };
                WpfGrid.SetRow(pill, 0);
                WpfGrid.SetRowSpan(pill, Math.Max(1, rootGrid.RowDefinitions.Count + 4));
                WpfGrid.SetColumn(pill, 0);
                WpfGrid.SetColumnSpan(pill, Math.Max(1, rootGrid.ColumnDefinitions.Count + 4));
                WpfPanel.SetZIndex(pill, 1500);
                rootGrid.Children.Add(pill);

                FrameworkElement? loginOverlay = win.FindName("AuthLoginOverlayGrid") as FrameworkElement;
                (bool Dark, bool Overlay) lastState = (true, false);
                bool initialized = false;

                void SyncShell()
                {
                    bool overlayVis = loginOverlay != null && loginOverlay.Visibility == Visibility.Visible;
                    bool dark = overlayVis || IsWindowEffectiveDark(win, rootGrid);
                    if (initialized && lastState == (dark, overlayVis)) return;
                    initialized = true;
                    lastState = (dark, overlayVis);

                    pill.Background = HexBrush(dark ? "#8C0B0D12" : "#CCFFFFFF");
                    txt.Foreground = HexBrush(dark ? (overlayVis ? "#D1D5DB" : "#9CA3AF") : "#4B5563");
                }

                RegisterThemeWatchers(win, SyncShell);
                SyncShell();
                return;
            }

            // Mode B: Existing bottom status bar in rootGrid (3D, Drucker, FTP, PDF, SSH, Tresor)
            if (rootGrid != null && TryFindBottomStatusBar(rootGrid, out WpfBorder? barBorder, out WpfGrid? barGrid) && barBorder != null && barGrid != null)
            {
                barGrid.ColumnDefinitions.Add(new WpfColumnDefinition { Width = GridLength.Auto });
                var barTxt = CreateBadgeTextBlock(fontSize: 10.5);
                barTxt.Margin = new Thickness(12, 0, 2, 0);
                barTxt.IsHitTestVisible = true;
                WpfGrid.SetColumn(barTxt, barGrid.ColumnDefinitions.Count - 1);
                barGrid.Children.Add(barTxt);

                var fallbackTxt = CreateBadgeTextBlock(fontSize: 10.0);
                fallbackTxt.Margin = new Thickness(0, 0, 10, 5);
                fallbackTxt.HorizontalAlignment = WpfHorizontal.Right;
                fallbackTxt.VerticalAlignment = WpfVertical.Bottom;
                fallbackTxt.IsHitTestVisible = false;
                WpfGrid.SetRow(fallbackTxt, 0);
                WpfGrid.SetRowSpan(fallbackTxt, Math.Max(1, rootGrid.RowDefinitions.Count + 4));
                WpfGrid.SetColumn(fallbackTxt, 0);
                WpfGrid.SetColumnSpan(fallbackTxt, Math.Max(1, rootGrid.ColumnDefinitions.Count + 4));
                WpfPanel.SetZIndex(fallbackTxt, 1500);
                rootGrid.Children.Add(fallbackTxt);

                (bool Dark, Visibility BarVis) lastBarState = (true, Visibility.Visible);
                bool barInit = false;

                void SyncBar()
                {
                    bool barDark = IsSurfaceDark(barBorder.Background, win, rootGrid);
                    Visibility bVis = barBorder.Visibility;
                    if (barInit && lastBarState == (barDark, bVis)) return;
                    barInit = true;
                    lastBarState = (barDark, bVis);

                    string fg = barDark ? "#9CA3AF" : "#4B5563";
                    barTxt.Foreground = HexBrush(fg);
                    fallbackTxt.Foreground = HexBrush(IsWindowEffectiveDark(win, rootGrid) ? "#9CA3AF" : "#4B5563");
                    fallbackTxt.Visibility = bVis == Visibility.Visible ? Visibility.Collapsed : Visibility.Visible;
                }

                RegisterThemeWatchers(win, SyncBar);
                SyncBar();
                return;
            }

            // Mode C: Wrap window content in a clean 2-row Grid with a slim 20px bottom-right version strip
            if (win.Content is UIElement existingContent)
            {
                win.Content = null;
                var wrapper = new WpfGrid();
                wrapper.RowDefinitions.Add(new WpfRowDefinition { Height = new GridLength(1, GridUnitType.Star) });
                wrapper.RowDefinitions.Add(new WpfRowDefinition { Height = GridLength.Auto });

                WpfGrid.SetRow(existingContent, 0);
                wrapper.Children.Add(existingContent);

                var stripTxt = CreateBadgeTextBlock(fontSize: 10.0);
                stripTxt.HorizontalAlignment = WpfHorizontal.Right;
                stripTxt.IsHitTestVisible = true;

                var stripBorder = new WpfBorder
                {
                    Name = "FunkenwerkVersionFooterStrip",
                    Tag = "FixedDarkOverlay",
                    BorderThickness = new Thickness(0, 1, 0, 0),
                    Padding = new Thickness(12, 2, 12, 2),
                    Child = stripTxt
                };
                WpfGrid.SetRow(stripBorder, 1);
                wrapper.Children.Add(stripBorder);
                win.Content = wrapper;

                bool isPlayer = string.Equals(asmName, "FunkenwerkPlayer", StringComparison.OrdinalIgnoreCase);
                (bool Dark, bool FullscreenFocus) lastStripState = (true, false);
                bool stripInit = false;

                void SyncStrip()
                {
                    bool fullscreenFocus = isPlayer && win.WindowStyle == WindowStyle.None && win.WindowState == WindowState.Maximized;
                    bool dark = IsWindowEffectiveDark(win, existingContent as WpfPanel ?? rootGrid);
                    if (stripInit && lastStripState == (dark, fullscreenFocus)) return;
                    stripInit = true;
                    lastStripState = (dark, fullscreenFocus);

                    stripBorder.Visibility = fullscreenFocus ? Visibility.Collapsed : Visibility.Visible;
                    stripBorder.Background = HexBrush(dark ? "#0B0D12" : "#FFFFFF");
                    stripBorder.BorderBrush = HexBrush(dark ? "#1F2430" : "#E5E7EB");
                    stripTxt.Foreground = HexBrush(dark ? "#9CA3AF" : "#4B5563");
                }

                RegisterThemeWatchers(win, SyncStrip);
                SyncStrip();
            }
        }

        public static void SyncWindowTheme(WpfWindow? win)
        {
            if (win?.GetValue(SyncActionProperty) is Action sync)
            {
                sync();
            }
        }

        private static void RegisterThemeWatchers(WpfWindow win, Action syncAction)
        {
            win.SetValue(SyncActionProperty, syncAction);
            try
            {
                DependencyPropertyDescriptor.FromProperty(WpfControl.BackgroundProperty, typeof(WpfWindow))
                    ?.AddValueChanged(win, (_, _) => syncAction());
            }
            catch { }
            win.LayoutUpdated += (_, _) => syncAction();
            win.Closed += (_, _) => win.ClearValue(SyncActionProperty);
        }

        private static WpfTextBlock CreateBadgeTextBlock(double fontSize) => new()
        {
            Name = "FunkenwerkVersionCornerTxt",
            Text = ShortBadgeText,
            ToolTip = FullTooltipText,
            FontSize = fontSize,
            FontWeight = FontWeights.SemiBold,
            VerticalAlignment = WpfVertical.Center
        };

        private static bool TryFindBottomStatusBar(WpfGrid rootGrid, out WpfBorder? statusBorder, out WpfGrid? barGrid)
        {
            statusBorder = null;
            barGrid = null;
            int rowCount = rootGrid.RowDefinitions.Count;
            if (rowCount < 3) return false;

            int lastRowIdx = rowCount - 1;
            var lastRowDef = rootGrid.RowDefinitions[lastRowIdx];
            bool rowLooksLikeBar = lastRowDef.Height.IsAuto || (lastRowDef.Height.IsAbsolute && lastRowDef.Height.Value <= 44);

            foreach (UIElement child in rootGrid.Children)
            {
                if (child is WpfBorder bd &&
                    WpfGrid.GetRow(bd) == lastRowIdx &&
                    WpfGrid.GetRowSpan(bd) <= 1 &&
                    bd.Child is WpfGrid innerGrid)
                {
                    string nm = bd.Name ?? "";
                    bool namedBar = nm.Contains("Footer", StringComparison.OrdinalIgnoreCase) ||
                                    nm.Contains("BottomBar", StringComparison.OrdinalIgnoreCase) ||
                                    nm.Contains("StatusBar", StringComparison.OrdinalIgnoreCase);
                    if (rowLooksLikeBar || namedBar || (!double.IsNaN(bd.Height) && bd.Height <= 44))
                    {
                        statusBorder = bd;
                        barGrid = innerGrid;
                        return true;
                    }
                }
            }
            return false;
        }

        private static bool IsSurfaceDark(WpfBrush? surfaceBrush, WpfWindow win, WpfPanel? fallbackPanel)
        {
            if (surfaceBrush is WpfSolidBrush scb && scb.Color.A > 40)
            {
                return RelativeLuminance(scb.Color) < 0.42;
            }
            return IsWindowEffectiveDark(win, fallbackPanel);
        }

        private static bool IsWindowEffectiveDark(WpfWindow win, WpfPanel? rootPanel)
        {
            if (rootPanel?.Background is WpfSolidBrush pb && pb.Color.A > 40)
            {
                return RelativeLuminance(pb.Color) < 0.42;
            }
            if (win.Background is WpfSolidBrush wb && wb.Color.A > 40)
            {
                return RelativeLuminance(wb.Color) < 0.42;
            }
            return true;
        }

        private static double RelativeLuminance(WpfColor c)
        {
            static double C(byte v)
            {
                double s = v / 255.0;
                return s <= 0.03928 ? s / 12.92 : Math.Pow((s + 0.055) / 1.055, 2.4);
            }
            return 0.2126 * C(c.R) + 0.7152 * C(c.G) + 0.0722 * C(c.B);
        }

        private static WpfSolidBrush HexBrush(string hex) =>
            new((WpfColor)WpfColorConverter.ConvertFromString(hex));
#endif
    }
}

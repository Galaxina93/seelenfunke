using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Text.RegularExpressions;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Threading;
using Microsoft.Win32;

namespace FunkenwerkPlayer
{
    public partial class MainWindow : Window
    {
        public static readonly Regex SafeCacheIdRegex = new(@"^[A-Za-z0-9_-]{1,128}$", RegexOptions.Compiled);

        private readonly List<string> _initialArgs;
        private readonly bool _disableAutoDiscovery;
        private readonly List<string> _playlist = new();
        private readonly Dictionary<string, string> _trackDisplayTitles = new(StringComparer.OrdinalIgnoreCase);
        private int _currentIndex = -1;
        private string? _currentFilePath = null;

        private bool _isDarkMode = true;
        private bool _isPlaying = false;
        private bool _isMuted = false;
        private bool _isLoopEnabled = true;
        private bool _isDraggingSlider = false;
        private bool _isFullscreen = false;
        private bool _isUniformToFill = false;

        private DateTime _seekGraceUntilUtc = DateTime.MinValue;
        private double _lastSeekTargetSeconds = 0.0;

        private WindowState _prevWindowState = WindowState.Normal;
        private WindowStyle _prevWindowStyle = WindowStyle.SingleBorderWindow;
        private double _prevLeft;
        private double _prevTop;
        private double _prevWidth = 1180;
        private double _prevHeight = 720;

        private readonly double[] _speedPresets = { 0.5, 0.75, 1.0, 1.25, 1.5, 2.0 };
        private int _speedIndex = 2; // 1.0x

        // A-B Loop State: 0 = Off, 1 = A set, 2 = A & B active
        private int _abLoopState = 0;
        private TimeSpan _loopPointA = TimeSpan.Zero;
        private TimeSpan _loopPointB = TimeSpan.Zero;

        private readonly DispatcherTimer _playbackTimer;
        private readonly DispatcherTimer _osdTimer;
        private readonly DispatcherTimer _hudAutoHideTimer;
        private readonly DispatcherTimer _singleClickTimer;
        private readonly DispatcherTimer _audioVisualizerTimer;
        private DateTime _lastClickTime = DateTime.MinValue;
        private readonly List<Border> _audioVisualizerBars = new();
        private readonly Random _vizRng = new();

        private readonly Dictionary<(DependencyObject Obj, DependencyProperty Prop), string> _originalDarkBrushes = new();
        private static readonly Dictionary<string, string> _darkToLightHexMap = new(StringComparer.OrdinalIgnoreCase)
        {
            ["#07090D"] = "#EEF2F6",
            ["#0F1117"] = "#F3F4F6",
            ["#11131A"] = "#FFFFFF",
            ["#D911131A"] = "#EBFFFFFF",
            ["#181B24"] = "#F8FAFC",
            ["#232734"] = "#E2E8F0",
            ["#262A36"] = "#CBD5E1",
            ["#26231F"] = "#FDF8ED",
            ["#F9FAFB"] = "#111827",
            ["#D1D5DB"] = "#374151",
            ["#9CA3AF"] = "#4B5563"
        };

        // Testable read-only state properties
        public IReadOnlyList<string> Playlist => _playlist;
        public int CurrentIndex => _currentIndex;
        public string? CurrentFilePath => _currentFilePath;
        public bool IsPlaying => _isPlaying;
        public bool IsMuted => _isMuted;
        public bool IsDarkMode => _isDarkMode;
        public bool IsFullscreen => _isFullscreen;
        public int AbLoopState => _abLoopState;
        public TimeSpan LoopPointA => _loopPointA;
        public TimeSpan LoopPointB => _loopPointB;

        public MainWindow() : this(Array.Empty<string>(), disableAutoDiscovery: false)
        {
        }

        public MainWindow(IEnumerable<string> initialFiles, bool disableAutoDiscovery = false)
        {
            _initialArgs = initialFiles?.ToList() ?? new List<string>();
            _disableAutoDiscovery = disableAutoDiscovery;
            InitializeComponent();

            _playbackTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(33) };
            _playbackTimer.Tick += PlaybackTimer_Tick;

            _osdTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(1600) };
            _osdTimer.Tick += (_, _) =>
            {
                _osdTimer.Stop();
                OsdToastBorder.Visibility = Visibility.Collapsed;
            };

            _hudAutoHideTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(2.8) };
            _hudAutoHideTimer.Tick += HudAutoHideTimer_Tick;

            _singleClickTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(210) };
            _singleClickTimer.Tick += (_, _) =>
            {
                _singleClickTimer.Stop();
                if (!string.IsNullOrWhiteSpace(_currentFilePath))
                {
                    TogglePlayPause();
                }
            };

            _audioVisualizerTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(55) };
            _audioVisualizerTimer.Tick += AudioVisualizerTimer_Tick;

            Loaded += MainWindow_Loaded;
        }

        public void InitializeUiForHeadlessTest()
        {
            BuildAudioVisualizerBars();
            CaptureOriginalDarkBrushes(this);
            ApplyThemeMode(_isDarkMode);
            PlayerMediaElement.Volume = VolumeSlider.Value / 100.0;
        }

        private void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            CenterWindowOnActiveScreen();
            BuildAudioVisualizerBars();
            CaptureOriginalDarkBrushes(this);
            ApplyThemeMode(_isDarkMode);

            PlayerMediaElement.Volume = VolumeSlider.Value / 100.0;

            if (_initialArgs.Count > 0)
            {
                LoadPathsIntoPlayer(_initialArgs, autoPlayFirst: true);
            }
            else if (!_disableAutoDiscovery)
            {
                TryPopulateDesktopMediaQueue(autoPlay: false);
            }
        }

        private void MainWindow_Closed(object? sender, EventArgs e)
        {
            _playbackTimer.Stop();
            _osdTimer.Stop();
            _hudAutoHideTimer.Stop();
            _singleClickTimer.Stop();
            _audioVisualizerTimer.Stop();

            try
            {
                PlayerMediaElement.Stop();
                PlayerMediaElement.Close();
                PlayerMediaElement.Source = null;
            }
            catch { }

            Mouse.OverrideCursor = null;
        }

        public void CenterWindowOnActiveScreen()
        {
            if (WindowState != WindowState.Normal) return;
            Rect workArea = SystemParameters.WorkArea;
            Width = Math.Min(Width, workArea.Width * 0.92);
            Height = Math.Min(Height, workArea.Height * 0.90);
            Left = workArea.Left + (workArea.Width - Width) / 2.0;
            Top = workArea.Top + (workArea.Height - Height) / 2.0;
        }

        private void BuildAudioVisualizerBars()
        {
            AudioBarsPanel.Children.Clear();
            _audioVisualizerBars.Clear();
            for (int i = 0; i < 28; i++)
            {
                var bar = new Border
                {
                    Width = 8,
                    Height = 14,
                    CornerRadius = new CornerRadius(4),
                    Margin = new Thickness(3, 0, 3, 0),
                    VerticalAlignment = VerticalAlignment.Bottom,
                    Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(i % 2 == 0 ? "#C5A059" : "#DC2626"))
                };
                _audioVisualizerBars.Add(bar);
                AudioBarsPanel.Children.Add(bar);
            }
        }

        private void AudioVisualizerTimer_Tick(object? sender, EventArgs e)
        {
            if (!_isPlaying || AudioVisualizerOverlay.Visibility != Visibility.Visible) return;
            double t = DateTime.Now.TimeOfDay.TotalSeconds;
            for (int i = 0; i < _audioVisualizerBars.Count; i++)
            {
                double wave = Math.Abs(Math.Sin(t * 6.5 + i * 0.45));
                double jitter = _vizRng.NextDouble() * 0.35;
                double h = 12 + (wave * 0.65 + jitter) * 72;
                _audioVisualizerBars[i].Height = Math.Clamp(h, 10, 86);
            }
        }

        private SolidColorBrush BrushFromHex(string hex)
        {
            if (!_isDarkMode)
            {
                if (_darkToLightHexMap.TryGetValue(hex, out var lightHex))
                {
                    hex = lightHex;
                }
                else if (hex.Length == 9 && hex.StartsWith("#", StringComparison.Ordinal))
                {
                    string alphaHex = hex.Substring(1, 2);
                    string rgbPart = "#" + hex.Substring(3, 6);
                    if (_darkToLightHexMap.TryGetValue(rgbPart, out var mappedRgb) && mappedRgb.Length == 7)
                    {
                        hex = "#" + alphaHex + mappedRgb.Substring(1);
                    }
                }
            }
            return new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
        }

        private void CaptureOriginalDarkBrushes(DependencyObject parent, HashSet<DependencyObject>? visited = null)
        {
            if (parent == null) return;
            visited ??= new HashSet<DependencyObject>();
            if (!visited.Add(parent)) return;

            RecordBrush(parent, Border.BackgroundProperty);
            RecordBrush(parent, Border.BorderBrushProperty);
            RecordBrush(parent, Panel.BackgroundProperty);
            RecordBrush(parent, Control.BackgroundProperty);
            RecordBrush(parent, Control.BorderBrushProperty);
            RecordBrush(parent, Control.ForegroundProperty);
            RecordBrush(parent, TextBlock.ForegroundProperty);

            foreach (var child in LogicalTreeHelper.GetChildren(parent))
            {
                if (child is DependencyObject dChild)
                    CaptureOriginalDarkBrushes(dChild, visited);
            }
            if (parent is Visual)
            {
                int count = VisualTreeHelper.GetChildrenCount(parent);
                for (int i = 0; i < count; i++)
                    CaptureOriginalDarkBrushes(VisualTreeHelper.GetChild(parent, i), visited);
            }
        }

        private void RecordBrush(DependencyObject obj, DependencyProperty prop)
        {
            if (ReferenceEquals(obj, VideoStageGrid) || ReferenceEquals(obj, RootGrid)) return;
            var key = (obj, prop);
            if (_originalDarkBrushes.ContainsKey(key)) return;
            if (obj.ReadLocalValue(prop) is SolidColorBrush scb && scb.Color.A > 0)
            {
                if (scb.Color.A == 255)
                {
                    string hex = $"#{scb.Color.R:X2}{scb.Color.G:X2}{scb.Color.B:X2}";
                    if (!string.Equals(hex, "#000000", StringComparison.OrdinalIgnoreCase))
                    {
                        _originalDarkBrushes[key] = hex;
                    }
                }
                else
                {
                    string argbHex = $"#{scb.Color.A:X2}{scb.Color.R:X2}{scb.Color.G:X2}{scb.Color.B:X2}";
                    _originalDarkBrushes[key] = argbHex;
                }
            }
        }

        public void ApplyThemeMode(bool isDark)
        {
            _isDarkMode = isDark;
            foreach (var kv in _originalDarkBrushes)
            {
                try
                {
                    kv.Key.Obj.SetValue(kv.Key.Prop, BrushFromHex(kv.Value));
                }
                catch { }
            }

            if (ThemeToggleBtn != null)
            {
                ThemeToggleBtn.Content = _isDarkMode ? "🌙" : "☀";
            }

            UpdateDynamicControlBrushes();
            RenderPlaylistUI();
        }

        private void UpdateDynamicControlBrushes()
        {
            if (LoopBtn != null)
            {
                LoopBtn.Foreground = _isLoopEnabled ? BrushFromHex("#C5A059") : BrushFromHex("#9CA3AF");
                LoopBtn.BorderBrush = _isLoopEnabled ? BrushFromHex("#C5A059") : BrushFromHex("#262A36");
            }

            if (AbLoopBtn != null)
            {
                AbLoopBtn.Foreground = _abLoopState > 0 ? BrushFromHex("#C5A059") : BrushFromHex("#D1D5DB");
            }
        }

        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            ApplyThemeMode(!_isDarkMode);
        }

        private void ShowOsdToast(string text)
        {
            OsdToastTxt.Text = text;
            OsdToastBorder.Visibility = Visibility.Visible;
            _osdTimer.Stop();
            _osdTimer.Start();
        }

        public static string? TryResolveSafeMusicCacheTrackPath(string musicCacheDir, string? rawId)
        {
            if (string.IsNullOrWhiteSpace(musicCacheDir) || string.IsNullOrWhiteSpace(rawId))
                return null;

            if (!SafeCacheIdRegex.IsMatch(rawId))
                return null;

            try
            {
                string fullCacheDir = Path.GetFullPath(musicCacheDir)
                    .TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                string candidate = Path.Combine(fullCacheDir, $"{rawId}.m4a");
                string full = Path.GetFullPath(candidate);

                if (!full.StartsWith(fullCacheDir + Path.DirectorySeparatorChar, StringComparison.OrdinalIgnoreCase))
                    return null;

                if (!File.Exists(full))
                    return null;

                var attrs = File.GetAttributes(full);
                if ((attrs & FileAttributes.ReparsePoint) != 0)
                    return null;

                return full;
            }
            catch
            {
                return null;
            }
        }

        public static int LoadFunkenwerkMusicCacheIfEmpty(
            string musicCacheDir,
            List<string> targetPlaylist,
            IDictionary<string, string>? trackDisplayTitles = null)
        {
            if (targetPlaylist == null || targetPlaylist.Count > 0)
                return 0;

            if (string.IsNullOrWhiteSpace(musicCacheDir) || !Directory.Exists(musicCacheDir))
                return 0;

            int added = 0;
            try
            {
                string fullCacheDir = Path.GetFullPath(musicCacheDir)
                    .TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                string cacheJson = Path.Combine(fullCacheDir, "playlists_cache.json");

                if (File.Exists(cacheJson))
                {
                    try
                    {
                        var jsonAttrs = File.GetAttributes(cacheJson);
                        if ((jsonAttrs & FileAttributes.ReparsePoint) == 0)
                        {
                            using var doc = System.Text.Json.JsonDocument.Parse(File.ReadAllText(cacheJson));
                            var root = doc.RootElement;
                            if (root.ValueKind == System.Text.Json.JsonValueKind.Object &&
                                root.TryGetProperty("data", out var dataArr) &&
                                dataArr.ValueKind == System.Text.Json.JsonValueKind.Array)
                            {
                                foreach (var pl in dataArr.EnumerateArray())
                                {
                                    if (!pl.TryGetProperty("tracks", out var tracksArr) ||
                                        tracksArr.ValueKind != System.Text.Json.JsonValueKind.Array)
                                        continue;

                                    foreach (var tr in tracksArr.EnumerateArray())
                                    {
                                        string id = tr.TryGetProperty("id", out var idP) && idP.ValueKind == System.Text.Json.JsonValueKind.String
                                            ? (idP.GetString() ?? "")
                                            : "";
                                        string extId = tr.TryGetProperty("external_id", out var exP) && exP.ValueKind == System.Text.Json.JsonValueKind.String
                                            ? (exP.GetString() ?? "")
                                            : "";
                                        string title = tr.TryGetProperty("title", out var tiP) && tiP.ValueKind == System.Text.Json.JsonValueKind.String
                                            ? (tiP.GetString() ?? "")
                                            : "";
                                        string artist = tr.TryGetProperty("artist", out var arP) && arP.ValueKind == System.Text.Json.JsonValueKind.String
                                            ? (arP.GetString() ?? "")
                                            : "";

                                        string? resolved = TryResolveSafeMusicCacheTrackPath(fullCacheDir, id)
                                                           ?? TryResolveSafeMusicCacheTrackPath(fullCacheDir, extId);

                                        if (resolved != null)
                                        {
                                            string label = !string.IsNullOrWhiteSpace(artist) && !string.IsNullOrWhiteSpace(title)
                                                ? $"{artist} - {title}"
                                                : (!string.IsNullOrWhiteSpace(title) ? title : Path.GetFileNameWithoutExtension(resolved));

                                            if (trackDisplayTitles != null)
                                                trackDisplayTitles[resolved] = label;

                                            if (!targetPlaylist.Contains(resolved, StringComparer.OrdinalIgnoreCase))
                                            {
                                                targetPlaylist.Add(resolved);
                                                added++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    catch { }
                }

                foreach (var m4a in Directory.GetFiles(fullCacheDir, "*.m4a"))
                {
                    string stem = Path.GetFileNameWithoutExtension(m4a);
                    string? safeM4a = TryResolveSafeMusicCacheTrackPath(fullCacheDir, stem);
                    if (safeM4a != null && !targetPlaylist.Contains(safeM4a, StringComparer.OrdinalIgnoreCase))
                    {
                        targetPlaylist.Add(safeM4a);
                        added++;
                    }
                }
            }
            catch { }

            return added;
        }

        private void TryPopulateDesktopMediaQueue(bool autoPlay)
        {
            try
            {
                var discovered = new List<string>();
                string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                if (Directory.Exists(desktop))
                {
                    var desktopVideos = Directory.GetFiles(desktop)
                        .Where(f => WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(Path.GetExtension(f)))
                        .Select(f => new FileInfo(f))
                        .Where(fi => (fi.Attributes & FileAttributes.ReparsePoint) == 0)
                        .OrderByDescending(f => f.LastWriteTimeUtc)
                        .Select(f => f.FullName);
                    discovered.AddRange(desktopVideos);
                }

                string musicCacheDir = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Funkenwerk", "MusicCache");

                LoadFunkenwerkMusicCacheIfEmpty(musicCacheDir, discovered, _trackDisplayTitles);

                if (discovered.Count > 0)
                {
                    _playlist.Clear();
                    _playlist.AddRange(discovered);
                    RenderPlaylistUI();
                    if (autoPlay)
                    {
                        PlayIndex(0);
                    }
                }
            }
            catch { }
        }

        public static int CompareNaturalStrings(string? a, string? b)
        {
            if (ReferenceEquals(a, b)) return 0;
            if (a is null) return -1;
            if (b is null) return 1;

            int ia = 0, ib = 0;
            while (ia < a.Length && ib < b.Length)
            {
                if (char.IsDigit(a[ia]) && char.IsDigit(b[ib]))
                {
                    int sa = ia;
                    while (ia < a.Length && char.IsDigit(a[ia])) ia++;
                    int sb = ib;
                    while (ib < b.Length && char.IsDigit(b[ib])) ib++;

                    ReadOnlySpan<char> spanA = a.AsSpan(sa, ia - sa).TrimStart('0');
                    ReadOnlySpan<char> spanB = b.AsSpan(sb, ib - sb).TrimStart('0');

                    if (spanA.Length != spanB.Length)
                        return spanA.Length.CompareTo(spanB.Length);

                    int digitCmp = spanA.CompareTo(spanB, StringComparison.Ordinal);
                    if (digitCmp != 0)
                        return digitCmp;

                    int rawLenCmp = (ia - sa).CompareTo(ib - sb);
                    if (rawLenCmp != 0)
                        return rawLenCmp;
                }
                else
                {
                    char ca = char.ToUpperInvariant(a[ia]);
                    char cb = char.ToUpperInvariant(b[ib]);
                    if (ca != cb)
                        return ca.CompareTo(cb);
                    ia++;
                    ib++;
                }
            }

            return (a.Length - ia).CompareTo(b.Length - ib);
        }

        public void LoadPathsIntoPlayer(IEnumerable<string> paths, bool autoPlayFirst)
        {
            var collected = new List<string>();
            string? firstExplicitFile = null;

            foreach (var rawPath in paths)
            {
                if (string.IsNullOrWhiteSpace(rawPath)) continue;
                try
                {
                    string full = Path.GetFullPath(rawPath);
                    if (Directory.Exists(full))
                    {
                        var dirFiles = Directory.GetFiles(full)
                            .Where(f => WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(Path.GetExtension(f)))
                            .Where(f => (File.GetAttributes(f) & FileAttributes.ReparsePoint) == 0)
                            .ToList();
                        dirFiles.Sort((x, y) => CompareNaturalStrings(Path.GetFileName(x), Path.GetFileName(y)));
                        collected.AddRange(dirFiles);
                    }
                    else if (File.Exists(full) &&
                             WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(Path.GetExtension(full)) &&
                             (File.GetAttributes(full) & FileAttributes.ReparsePoint) == 0)
                    {
                        firstExplicitFile ??= full;

                        string? parentDir = Path.GetDirectoryName(full);
                        if (!string.IsNullOrWhiteSpace(parentDir) && Directory.Exists(parentDir))
                        {
                            var siblings = Directory.GetFiles(parentDir)
                                .Where(f => WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(Path.GetExtension(f)))
                                .Where(f => (File.GetAttributes(f) & FileAttributes.ReparsePoint) == 0)
                                .ToList();
                            siblings.Sort((x, y) => CompareNaturalStrings(Path.GetFileName(x), Path.GetFileName(y)));
                            foreach (var sib in siblings)
                            {
                                if (!collected.Contains(sib, StringComparer.OrdinalIgnoreCase))
                                    collected.Add(sib);
                            }
                        }

                        if (!collected.Contains(full, StringComparer.OrdinalIgnoreCase))
                        {
                            collected.Add(full);
                        }
                    }
                }
                catch { }
            }

            if (collected.Count == 0) return;

            _playlist.Clear();
            foreach (var f in collected.Distinct(StringComparer.OrdinalIgnoreCase))
            {
                _playlist.Add(f);
            }

            int startIdx = 0;
            if (firstExplicitFile != null)
            {
                int found = _playlist.FindIndex(p => string.Equals(p, firstExplicitFile, StringComparison.OrdinalIgnoreCase));
                if (found >= 0) startIdx = found;
            }

            RenderPlaylistUI();
            if (autoPlayFirst)
            {
                PlayIndex(startIdx);
            }
        }

        public void ResetAndUnloadActiveMedia()
        {
            _playbackTimer.Stop();
            _audioVisualizerTimer.Stop();
            _hudAutoHideTimer.Stop();
            _singleClickTimer.Stop();

            try
            {
                PlayerMediaElement.Stop();
                PlayerMediaElement.Close();
                PlayerMediaElement.Source = null;
            }
            catch { }

            _isPlaying = false;
            _currentFilePath = null;
            _currentIndex = -1;
            _abLoopState = 0;

            AbLoopBtn.Content = "A↔B";
            AbLoopBtn.Foreground = BrushFromHex("#D1D5DB");
            PlayPauseBtn.Content = "▶";
            TimelineSlider.Value = 0;
            TimelineSlider.Maximum = 100;
            TimeElapsedTxt.Text = "00:00";
            TimeTotalTxt.Text = "00:00";

            Title = "Funkenwerk Player";
            CurrentTitleTxt.Text = "Bereit";
            MediaFormatBadge.Visibility = Visibility.Collapsed;
            AudioVisualizerOverlay.Visibility = Visibility.Collapsed;
            EmptyStateOverlay.Visibility = Visibility.Visible;

            ShowHudBars();
        }

        public void PlayIndex(int index)
        {
            if (index < 0 || index >= _playlist.Count) return;
            string path = _playlist[index];
            if (!File.Exists(path)) return;

            _currentIndex = index;
            _currentFilePath = path;
            _abLoopState = 0;
            AbLoopBtn.Content = "A↔B";
            AbLoopBtn.Foreground = BrushFromHex("#D1D5DB");

            string fileName = _trackDisplayTitles.TryGetValue(path, out string? niceTitle) && !string.IsNullOrWhiteSpace(niceTitle)
                ? niceTitle
                : Path.GetFileName(path);
            string ext = Path.GetExtension(path).TrimStart('.').ToUpperInvariant();

            Title = $"{fileName} - Funkenwerk Player";
            CurrentTitleTxt.Text = fileName;
            MediaFormatBadgeTxt.Text = ext;
            MediaFormatBadge.Visibility = Visibility.Visible;
            EmptyStateOverlay.Visibility = Visibility.Collapsed;

            bool isAudio = WindowsDefaultPlayerRegistrar.IsAudioExtension(Path.GetExtension(path));
            if (isAudio)
            {
                AudioTrackTitleTxt.Text = _trackDisplayTitles.TryGetValue(path, out string? niceAudioTitle) && !string.IsNullOrWhiteSpace(niceAudioTitle)
                    ? niceAudioTitle
                    : Path.GetFileNameWithoutExtension(path);
                AudioVisualizerOverlay.Visibility = Visibility.Visible;
                _audioVisualizerTimer.Start();
            }
            else
            {
                AudioVisualizerOverlay.Visibility = Visibility.Collapsed;
                _audioVisualizerTimer.Stop();
            }

            try
            {
                PlayerMediaElement.Stop();
                PlayerMediaElement.Source = new Uri(path, UriKind.Absolute);
                PlayerMediaElement.SpeedRatio = _speedPresets[_speedIndex];
                PlayerMediaElement.Play();
                _isPlaying = true;
                PlayPauseBtn.Content = "⏸";
                _playbackTimer.Start();
                ResetHudAutoHideTimer();
            }
            catch { }

            RenderPlaylistUI();
        }

        private void PlayerMediaElement_MediaOpened(object sender, RoutedEventArgs e)
        {
            if (PlayerMediaElement.NaturalDuration.HasTimeSpan)
            {
                TimeSpan total = PlayerMediaElement.NaturalDuration.TimeSpan;
                TimelineSlider.Maximum = Math.Max(0.1, total.TotalSeconds);
                TimeTotalTxt.Text = FormatTime(total);
            }

            int vw = PlayerMediaElement.NaturalVideoWidth;
            int vh = PlayerMediaElement.NaturalVideoHeight;
            if (vw > 0 && vh > 0 && !string.IsNullOrWhiteSpace(_currentFilePath))
            {
                string ext = Path.GetExtension(_currentFilePath).TrimStart('.').ToUpperInvariant();
                MediaFormatBadgeTxt.Text = $"{vw}x{vh} · {ext}";
            }
        }

        public void HandleMediaEnded()
        {
            if (_abLoopState == 2 && _loopPointB > _loopPointA)
            {
                PlayerMediaElement.Position = _loopPointA;
                PlayerMediaElement.Play();
                _isPlaying = true;
                PlayPauseBtn.Content = "⏸";
                return;
            }

            if (_isLoopEnabled)
            {
                PlayerMediaElement.Position = TimeSpan.Zero;
                PlayerMediaElement.Play();
                _isPlaying = true;
                PlayPauseBtn.Content = "⏸";
                return;
            }

            if (_currentIndex >= 0 && _currentIndex + 1 < _playlist.Count)
            {
                PlayIndex(_currentIndex + 1);
            }
            else
            {
                PlayerMediaElement.Pause();
                PlayerMediaElement.Position = TimeSpan.Zero;
                _isPlaying = false;
                PlayPauseBtn.Content = "▶";
                ShowHudBars();
            }
        }

        private void PlayerMediaElement_MediaEnded(object sender, RoutedEventArgs e)
        {
            HandleMediaEnded();
        }

        private void PlayerMediaElement_MediaFailed(object? sender, ExceptionRoutedEventArgs e)
        {
            ShowOsdToast($"⚠ Wiedergabefehler: {e.ErrorException?.Message ?? "Format"}");
        }

        private void PlaybackTimer_Tick(object? sender, EventArgs e)
        {
            if (_isDraggingSlider || !PlayerMediaElement.NaturalDuration.HasTimeSpan) return;

            TimeSpan pos = PlayerMediaElement.Position;
            TimeSpan total = PlayerMediaElement.NaturalDuration.TimeSpan;

            if (DateTime.UtcNow < _seekGraceUntilUtc && Math.Abs(pos.TotalSeconds - _lastSeekTargetSeconds) > 0.45)
            {
                return;
            }

            if (_abLoopState == 2 && _loopPointB > _loopPointA && (pos >= _loopPointB || pos < _loopPointA))
            {
                PlayerMediaElement.Position = _loopPointA;
                pos = _loopPointA;
            }

            TimelineSlider.Value = Math.Min(TimelineSlider.Maximum, pos.TotalSeconds);
            TimeElapsedTxt.Text = FormatTime(pos);
            TimeTotalTxt.Text = FormatTime(total);
        }

        private static string FormatTime(TimeSpan ts)
        {
            if (ts < TimeSpan.Zero) ts = TimeSpan.Zero;
            if (ts.TotalHours >= 1.0)
                return $"{(int)ts.TotalHours:D2}:{ts.Minutes:D2}:{ts.Seconds:D2}";
            return $"{ts.Minutes:D2}:{ts.Seconds:D2}";
        }

        public TimeSpan ClampSeekPosition(TimeSpan target, TimeSpan naturalDuration)
        {
            if (naturalDuration < TimeSpan.Zero)
                naturalDuration = TimeSpan.Zero;

            TimeSpan minPos = TimeSpan.Zero;
            TimeSpan maxPos = naturalDuration;

            if (_abLoopState == 2 && _loopPointB > _loopPointA)
            {
                minPos = _loopPointA;
                maxPos = naturalDuration > TimeSpan.Zero && _loopPointB > naturalDuration ? naturalDuration : _loopPointB;
            }

            if (target < minPos) return minPos;
            if (maxPos > minPos && target > maxPos) return maxPos;
            return target;
        }

        public void SetAbLoopForTest(TimeSpan pointA, TimeSpan pointB)
        {
            _loopPointA = pointA;
            _loopPointB = pointB;
            _abLoopState = pointB > pointA ? 2 : 0;
            AbLoopBtn.Content = _abLoopState == 2 ? $"{FormatTime(_loopPointA)}↔{FormatTime(_loopPointB)}" : "A↔B";
            UpdateDynamicControlBrushes();
        }

        private void CommitManualSeekSeconds(double requestedSeconds)
        {
            if (string.IsNullOrWhiteSpace(_currentFilePath)) return;

            TimeSpan total = PlayerMediaElement.NaturalDuration.HasTimeSpan
                ? PlayerMediaElement.NaturalDuration.TimeSpan
                : TimeSpan.FromSeconds(Math.Max(0.1, TimelineSlider.Maximum));

            TimeSpan clamped = ClampSeekPosition(TimeSpan.FromSeconds(requestedSeconds), total);
            _lastSeekTargetSeconds = clamped.TotalSeconds;
            _seekGraceUntilUtc = DateTime.UtcNow.AddMilliseconds(280);

            TimelineSlider.Value = Math.Clamp(clamped.TotalSeconds, TimelineSlider.Minimum, TimelineSlider.Maximum);
            PlayerMediaElement.Position = clamped;
            TimeElapsedTxt.Text = FormatTime(clamped);
        }

        private void TogglePlayPause()
        {
            if (string.IsNullOrWhiteSpace(_currentFilePath))
            {
                if (_playlist.Count > 0)
                    PlayIndex(0);
                else
                    OpenFile_Click(this, new RoutedEventArgs());
                return;
            }

            try
            {
                if (_isPlaying)
                {
                    PlayerMediaElement.Pause();
                    _isPlaying = false;
                    PlayPauseBtn.Content = "▶";
                    ShowOsdToast("⏸ Pause");
                    ShowHudBars();
                }
                else
                {
                    PlayerMediaElement.Play();
                    _isPlaying = true;
                    PlayPauseBtn.Content = "⏸";
                    ShowOsdToast("▶ Wiedergabe");
                    ResetHudAutoHideTimer();
                }
            }
            catch { }
        }

        private void PlayPause_Click(object sender, RoutedEventArgs e)
        {
            TogglePlayPause();
        }

        private void Stop_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_currentFilePath)) return;
            try
            {
                PlayerMediaElement.Stop();
                _isPlaying = false;
                PlayPauseBtn.Content = "▶";
                TimelineSlider.Value = 0;
                TimeElapsedTxt.Text = "00:00";
                ShowHudBars();
            }
            catch { }
        }

        private void SeekRelative(double deltaSeconds)
        {
            if (string.IsNullOrWhiteSpace(_currentFilePath) || !PlayerMediaElement.NaturalDuration.HasTimeSpan) return;
            TimeSpan total = PlayerMediaElement.NaturalDuration.TimeSpan;
            TimeSpan target = ClampSeekPosition(PlayerMediaElement.Position + TimeSpan.FromSeconds(deltaSeconds), total);
            CommitManualSeekSeconds(target.TotalSeconds);
            ShowOsdToast($"{(deltaSeconds >= 0 ? "⏩ +" : "⏪ ")}{deltaSeconds:F0}s ({FormatTime(target)})");
        }

        private void SkipBackward_Click(object sender, RoutedEventArgs e) => SeekRelative(-5.0);
        private void SkipForward_Click(object sender, RoutedEventArgs e) => SeekRelative(5.0);

        public void StepPreviousTrack()
        {
            if (_playlist.Count == 0) return;
            if (_currentIndex < 0 || _currentIndex >= _playlist.Count)
            {
                PlayIndex(_playlist.Count - 1);
                return;
            }
            int prev = (_currentIndex - 1 + _playlist.Count) % _playlist.Count;
            PlayIndex(prev);
        }

        public void StepNextTrack()
        {
            if (_playlist.Count == 0) return;
            if (_currentIndex < 0 || _currentIndex >= _playlist.Count)
            {
                PlayIndex(0);
                return;
            }
            int next = (_currentIndex + 1) % _playlist.Count;
            PlayIndex(next);
        }

        private void PrevTrack_Click(object sender, RoutedEventArgs e) => StepPreviousTrack();
        private void NextTrack_Click(object sender, RoutedEventArgs e) => StepNextTrack();

        private double ComputeSliderValueFromMouse(MouseEventArgs e)
        {
            double width = TimelineSlider.ActualWidth;
            if (width <= 1.0) return TimelineSlider.Value;
            Point pt = e.GetPosition(TimelineSlider);
            double ratio = Math.Clamp(pt.X / width, 0.0, 1.0);
            double rawVal = TimelineSlider.Minimum + ratio * (TimelineSlider.Maximum - TimelineSlider.Minimum);

            if (!string.IsNullOrWhiteSpace(_currentFilePath))
            {
                TimeSpan total = PlayerMediaElement.NaturalDuration.HasTimeSpan
                    ? PlayerMediaElement.NaturalDuration.TimeSpan
                    : TimeSpan.FromSeconds(Math.Max(0.1, TimelineSlider.Maximum));
                rawVal = ClampSeekPosition(TimeSpan.FromSeconds(rawVal), total).TotalSeconds;
            }

            return Math.Clamp(rawVal, TimelineSlider.Minimum, TimelineSlider.Maximum);
        }

        private void TimelineSlider_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            _isDraggingSlider = true;
            TimelineSlider.CaptureMouse();
            double targetVal = ComputeSliderValueFromMouse(e);
            TimelineSlider.Value = targetVal;
            if (!string.IsNullOrWhiteSpace(_currentFilePath))
            {
                CommitManualSeekSeconds(targetVal);
            }
            e.Handled = true;
        }

        private void TimelineSlider_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (!_isDraggingSlider) return;
            if (e.LeftButton != MouseButtonState.Pressed)
            {
                _isDraggingSlider = false;
                if (TimelineSlider.IsMouseCaptured)
                    TimelineSlider.ReleaseMouseCapture();
                return;
            }

            double targetVal = ComputeSliderValueFromMouse(e);
            TimelineSlider.Value = targetVal;
            if (!string.IsNullOrWhiteSpace(_currentFilePath))
            {
                CommitManualSeekSeconds(targetVal);
            }
            e.Handled = true;
        }

        private void TimelineSlider_PreviewMouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (!_isDraggingSlider) return;
            double targetVal = ComputeSliderValueFromMouse(e);
            _isDraggingSlider = false;
            if (TimelineSlider.IsMouseCaptured)
            {
                TimelineSlider.ReleaseMouseCapture();
            }

            TimelineSlider.Value = targetVal;
            if (!string.IsNullOrWhiteSpace(_currentFilePath))
            {
                CommitManualSeekSeconds(targetVal);
            }
            e.Handled = true;
        }

        private void TimelineSlider_LostMouseCapture(object sender, MouseEventArgs e)
        {
            _isDraggingSlider = false;
        }

        private void TimelineSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_isDraggingSlider && !string.IsNullOrWhiteSpace(_currentFilePath))
            {
                TimeElapsedTxt.Text = FormatTime(TimeSpan.FromSeconds(TimelineSlider.Value));
            }
        }

        private void VolumeSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (PlayerMediaElement == null || VolumePercentTxt == null) return;
            if (_isMuted && e.NewValue > e.OldValue)
            {
                _isMuted = false;
            }

            int vol = (int)Math.Round(VolumeSlider.Value);
            VolumePercentTxt.Text = $"{vol}%";
            PlayerMediaElement.Volume = _isMuted ? 0.0 : (vol / 100.0);

            if (MuteBtn != null)
            {
                MuteBtn.Content = (_isMuted || vol == 0) ? "🔇" : (vol < 45 ? "🔉" : "🔊");
            }
        }

        public void SetMuted(bool muted)
        {
            _isMuted = muted;
            PlayerMediaElement.Volume = _isMuted ? 0.0 : (VolumeSlider.Value / 100.0);
            int vol = (int)Math.Round(VolumeSlider.Value);
            MuteBtn.Content = (_isMuted || vol == 0) ? "🔇" : (vol < 45 ? "🔉" : "🔊");
        }

        public void AdjustVolumeByDelta(double delta)
        {
            if (delta > 0 && _isMuted)
            {
                _isMuted = false;
            }

            double target = Math.Clamp(VolumeSlider.Value + delta, 0.0, 100.0);
            VolumeSlider.Value = target;
            int vol = (int)Math.Round(target);
            PlayerMediaElement.Volume = _isMuted ? 0.0 : (vol / 100.0);
            if (MuteBtn != null)
            {
                MuteBtn.Content = (_isMuted || vol == 0) ? "🔇" : (vol < 45 ? "🔉" : "🔊");
            }
            ShowOsdToast(_isMuted ? "🔇 Stumm" : $"🔊 Lautstärke: {vol}%");
        }

        private void Mute_Click(object sender, RoutedEventArgs e)
        {
            SetMuted(!_isMuted);
            ShowOsdToast(_isMuted ? "🔇 Stumm" : $"🔊 {(int)Math.Round(VolumeSlider.Value)}%");
        }

        private void LoopToggle_Click(object sender, RoutedEventArgs e)
        {
            _isLoopEnabled = !_isLoopEnabled;
            LoopBtn.Content = _isLoopEnabled ? "🔁 Loop An" : "🔁 Loop Aus";
            UpdateDynamicControlBrushes();
            ShowOsdToast(_isLoopEnabled ? "🔁 Endlosschleife Aktiv" : "🔁 Endlosschleife Aus");
        }

        private void SpeedCycle_Click(object sender, RoutedEventArgs e)
        {
            _speedIndex = (_speedIndex + 1) % _speedPresets.Length;
            double spd = _speedPresets[_speedIndex];
            PlayerMediaElement.SpeedRatio = spd;
            SpeedBtn.Content = $"{spd:0.##}x";
            ShowOsdToast($"⏩ Geschwindigkeit: {spd:0.##}x");
        }

        private void AbLoop_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_currentFilePath)) return;

            if (_abLoopState == 0)
            {
                _loopPointA = PlayerMediaElement.Position;
                _abLoopState = 1;
                AbLoopBtn.Content = $"A: {FormatTime(_loopPointA)}";
                UpdateDynamicControlBrushes();
                ShowOsdToast($"🔁 Punkt A gesetzt ({FormatTime(_loopPointA)})");
            }
            else if (_abLoopState == 1)
            {
                if (PlayerMediaElement.Position > _loopPointA + TimeSpan.FromMilliseconds(300))
                {
                    _loopPointB = PlayerMediaElement.Position;
                    _abLoopState = 2;
                    AbLoopBtn.Content = $"{FormatTime(_loopPointA)}↔{FormatTime(_loopPointB)}";
                    UpdateDynamicControlBrushes();
                    ShowOsdToast($"🔁 A-B Loop aktiv ({FormatTime(_loopPointA)} - {FormatTime(_loopPointB)})");
                }
                else
                {
                    _abLoopState = 0;
                    AbLoopBtn.Content = "A↔B";
                    UpdateDynamicControlBrushes();
                    ShowOsdToast("🔁 A-B Loop aufgehoben");
                }
            }
            else
            {
                _abLoopState = 0;
                AbLoopBtn.Content = "A↔B";
                UpdateDynamicControlBrushes();
                ShowOsdToast("🔁 A-B Loop aufgehoben");
            }
        }

        private void AspectToggle_Click(object sender, RoutedEventArgs e)
        {
            _isUniformToFill = !_isUniformToFill;
            PlayerMediaElement.Stretch = _isUniformToFill ? Stretch.UniformToFill : Stretch.Uniform;
            AspectBtn.Content = _isUniformToFill ? "Ausfüllen" : "Einpassen";
            ShowOsdToast(_isUniformToFill ? "📐 Bildformat: Ausfüllen (Zoom)" : "📐 Bildformat: Einpassen (Original)");
        }

        public static string BuildSnapshotFileName(DateTime timestamp)
        {
            return $"Funkenwerk_Snapshot_{timestamp:yyyyMMdd_HHmmss_fff}.png";
        }

        public bool CanTakeVideoSnapshot()
        {
            if (string.IsNullOrWhiteSpace(_currentFilePath))
                return false;
            if (WindowsDefaultPlayerRegistrar.IsAudioExtension(Path.GetExtension(_currentFilePath)))
                return false;
            if (!PlayerMediaElement.HasVideo ||
                PlayerMediaElement.NaturalVideoWidth <= 0 ||
                PlayerMediaElement.NaturalVideoHeight <= 0)
                return false;
            return true;
        }

        public bool TryCaptureSnapshotToDirectory(string outputDirectory, out string? savedSnapshotPath)
        {
            savedSnapshotPath = null;
            if (!CanTakeVideoSnapshot())
            {
                ShowOsdToast("⚠ Screenshot nur bei Videodateien möglich");
                return false;
            }

            try
            {
                int w = PlayerMediaElement.NaturalVideoWidth;
                int h = PlayerMediaElement.NaturalVideoHeight;
                if (w <= 0 || h <= 0)
                    return false;

                var dv = new DrawingVisual();
                using (var dc = dv.RenderOpen())
                {
                    var vb = new VisualBrush(PlayerMediaElement) { Stretch = Stretch.Fill };
                    dc.DrawRectangle(vb, null, new Rect(0, 0, w, h));
                }

                var rtb = new RenderTargetBitmap(w, h, 96, 96, PixelFormats.Pbgra32);
                rtb.Render(dv);

                var encoder = new PngBitmapEncoder();
                encoder.Frames.Add(BitmapFrame.Create(rtb));

                Directory.CreateDirectory(outputDirectory);
                string snapName = BuildSnapshotFileName(DateTime.Now);
                string snapPath = Path.Combine(outputDirectory, snapName);

                using (var fs = new FileStream(snapPath, FileMode.Create, FileAccess.Write, FileShare.Read))
                {
                    encoder.Save(fs);
                }

                savedSnapshotPath = snapPath;
                ShowOsdToast($"📷 Screenshot gespeichert: {snapName}");
                return true;
            }
            catch
            {
                return false;
            }
        }

        private void Snapshot_Click(object sender, RoutedEventArgs e)
        {
            string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
            TryCaptureSnapshotToDirectory(desktop, out _);
        }

        public void ToggleFullscreen()
        {
            _isFullscreen = !_isFullscreen;
            if (_isFullscreen)
            {
                _prevWindowState = WindowState;
                _prevWindowStyle = WindowStyle;
                _prevLeft = Left;
                _prevTop = Top;
                _prevWidth = Width;
                _prevHeight = Height;

                WindowStyle = WindowStyle.None;
                WindowState = WindowState.Maximized;
                FullscreenBtn.Content = "🗗";
                ShowOsdToast("⛶ Vollbild (ESC zum Beenden)");
                ResetHudAutoHideTimer();
            }
            else
            {
                WindowStyle = _prevWindowStyle;
                WindowState = _prevWindowState;
                if (WindowState == WindowState.Normal)
                {
                    Width = _prevWidth;
                    Height = _prevHeight;
                    Left = _prevLeft;
                    Top = _prevTop;
                }
                FullscreenBtn.Content = "⛶";
                ShowHudBars();
            }
        }

        private void Fullscreen_Click(object sender, RoutedEventArgs e)
        {
            ToggleFullscreen();
        }

        private void VideoStage_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (EmptyStateOverlay.Visibility == Visibility.Visible) return;

            DateTime now = DateTime.Now;
            if ((now - _lastClickTime).TotalMilliseconds <= 260)
            {
                _singleClickTimer.Stop();
                _lastClickTime = DateTime.MinValue;
                ToggleFullscreen();
            }
            else
            {
                _lastClickTime = now;
                _singleClickTimer.Stop();
                _singleClickTimer.Start();
            }
        }

        private void EmptyState_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (_playlist.Count > 0)
            {
                PlayIndex(0);
            }
            else
            {
                OpenFile_Click(sender, e);
            }
        }

        private void Window_MouseWheel(object sender, MouseWheelEventArgs e)
        {
            double step = (Keyboard.Modifiers & ModifierKeys.Shift) != 0 ? 1.0 : 5.0;
            double delta = e.Delta > 0 ? step : -step;
            AdjustVolumeByDelta(delta);
        }

        private void Window_MouseMove(object sender, MouseEventArgs e)
        {
            ShowHudBars();
            ResetHudAutoHideTimer();
        }

        private void ResetHudAutoHideTimer()
        {
            _hudAutoHideTimer.Stop();
            if (_isPlaying && _isFullscreen)
            {
                _hudAutoHideTimer.Start();
            }
        }

        private void HudAutoHideTimer_Tick(object? sender, EventArgs e)
        {
            _hudAutoHideTimer.Stop();
            if (_isPlaying && _isFullscreen && PlaylistDrawerBorder.Visibility != Visibility.Visible)
            {
                TopHudBar.Visibility = Visibility.Collapsed;
                BottomHudBar.Visibility = Visibility.Collapsed;
                Mouse.OverrideCursor = Cursors.None;
            }
        }

        private void ShowHudBars()
        {
            if (TopHudBar.Visibility != Visibility.Visible)
                TopHudBar.Visibility = Visibility.Visible;
            if (BottomHudBar.Visibility != Visibility.Visible)
                BottomHudBar.Visibility = Visibility.Visible;
            if (Mouse.OverrideCursor != null)
                Mouse.OverrideCursor = null;
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.OriginalSource is TextBox) return;

            switch (e.Key)
            {
                case Key.Space:
                case Key.K:
                    e.Handled = true;
                    TogglePlayPause();
                    break;
                case Key.F11:
                case Key.F:
                    e.Handled = true;
                    ToggleFullscreen();
                    break;
                case Key.Escape:
                    if (_isFullscreen)
                    {
                        e.Handled = true;
                        ToggleFullscreen();
                    }
                    break;
                case Key.Left:
                    e.Handled = true;
                    SeekRelative((Keyboard.Modifiers & ModifierKeys.Shift) != 0 ? -1.0 : -5.0);
                    break;
                case Key.Right:
                    e.Handled = true;
                    SeekRelative((Keyboard.Modifiers & ModifierKeys.Shift) != 0 ? 1.0 : 5.0);
                    break;
                case Key.OemComma:
                    e.Handled = true;
                    if (_isPlaying) TogglePlayPause();
                    SeekRelative(-1.0 / 30.0);
                    break;
                case Key.OemPeriod:
                    e.Handled = true;
                    if (_isPlaying) TogglePlayPause();
                    SeekRelative(1.0 / 30.0);
                    break;
                case Key.Up:
                    e.Handled = true;
                    AdjustVolumeByDelta(5.0);
                    break;
                case Key.Down:
                    e.Handled = true;
                    AdjustVolumeByDelta(-5.0);
                    break;
                case Key.M:
                    e.Handled = true;
                    Mute_Click(this, new RoutedEventArgs());
                    break;
                case Key.L:
                    e.Handled = true;
                    LoopToggle_Click(this, new RoutedEventArgs());
                    break;
                case Key.S:
                    e.Handled = true;
                    Snapshot_Click(this, new RoutedEventArgs());
                    break;
                case Key.N:
                    e.Handled = true;
                    StepNextTrack();
                    break;
                case Key.P:
                    e.Handled = true;
                    StepPreviousTrack();
                    break;
            }
        }

        private void OpenFile_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Video- oder Audiodatei in Funkenwerk Player öffnen",
                Filter = "Alle Mediendateien (*.mp4;*.mov;*.mkv;*.webm;*.avi;*.wmv;*.mp3;*.wav;*.flac;*.m4a;*.ogg)|*.mp4;*.mov;*.mkv;*.webm;*.avi;*.wmv;*.mp3;*.wav;*.flac;*.m4a;*.ogg|Alle Dateien (*.*)|*.*",
                Multiselect = true
            };
            if (dlg.ShowDialog() == true && dlg.FileNames.Length > 0)
            {
                LoadPathsIntoPlayer(dlg.FileNames, autoPlayFirst: true);
            }
        }

        private void OpenLatestDesktopVideo_Click(object sender, RoutedEventArgs e)
        {
            TryPopulateDesktopMediaQueue(autoPlay: true);
        }

        private void SetDefaultPlayer_Click(object sender, RoutedEventArgs e)
        {
            bool ok = WindowsDefaultPlayerRegistrar.RegisterAsWindowsMediaPlayer(out _, setDefaultVerb: true);
            if (ok)
            {
                DefaultPlayerBtn.Content = "✓ Standard-Player aktiv";
                ShowOsdToast("★ Als Windows Mediaplayer registriert!");

                string? sampleTarget = _currentFilePath;
                if (string.IsNullOrWhiteSpace(sampleTarget) && _playlist.Count > 0)
                    sampleTarget = _playlist[0];

                if (!string.IsNullOrWhiteSpace(sampleTarget) && File.Exists(sampleTarget))
                {
                    WindowsDefaultPlayerRegistrar.OpenWindowsOpenWithDialog(sampleTarget);
                }
                else
                {
                    WindowsDefaultPlayerRegistrar.OpenWindowsDefaultAppsSettings();
                }
            }
        }

        private void TogglePlaylist_Click(object sender, RoutedEventArgs e)
        {
            PlaylistDrawerBorder.Visibility = PlaylistDrawerBorder.Visibility == Visibility.Visible
                ? Visibility.Collapsed
                : Visibility.Visible;
        }

        public void ClearPlaylist()
        {
            _playlist.Clear();
            ResetAndUnloadActiveMedia();
            RenderPlaylistUI();
        }

        private void ClearPlaylist_Click(object sender, RoutedEventArgs e)
        {
            ClearPlaylist();
        }

        public void RemoveTrackAt(int index)
        {
            if (index < 0 || index >= _playlist.Count) return;

            bool wasActive = (index == _currentIndex);
            _playlist.RemoveAt(index);

            if (wasActive || _playlist.Count == 0)
            {
                ResetAndUnloadActiveMedia();
            }
            else if (_currentIndex > index)
            {
                _currentIndex--;
            }

            RenderPlaylistUI();
        }

        private void RenderPlaylistUI()
        {
            if (PlaylistItemsStack == null || PlaylistToggleBtn == null) return;
            PlaylistToggleBtn.Content = $"☰ Playlist ({_playlist.Count})";
            PlaylistItemsStack.Children.Clear();

            for (int i = 0; i < _playlist.Count; i++)
            {
                int idx = i;
                string filePath = _playlist[i];
                bool isActive = i == _currentIndex;

                var row = new Border
                {
                    Background = BrushFromHex(isActive ? "#26231F" : "#181B24"),
                    BorderBrush = BrushFromHex(isActive ? "#C5A059" : "#232734"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(10, 7, 10, 7),
                    Margin = new Thickness(0, 0, 0, 5),
                    Cursor = Cursors.Hand
                };

                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                string displayLabel = _trackDisplayTitles.TryGetValue(filePath, out string? mappedTitle) && !string.IsNullOrWhiteSpace(mappedTitle)
                    ? mappedTitle
                    : Path.GetFileName(filePath);
                var txt = new TextBlock
                {
                    Text = $"{(isActive ? "▶ " : "")}{displayLabel}",
                    Foreground = BrushFromHex(isActive ? "#C5A059" : "#F9FAFB"),
                    FontSize = 11.5,
                    FontWeight = isActive ? FontWeights.Bold : FontWeights.SemiBold,
                    VerticalAlignment = VerticalAlignment.Center,
                    TextTrimming = TextTrimming.CharacterEllipsis
                };
                Grid.SetColumn(txt, 0);
                g.Children.Add(txt);

                var rmBtn = new Button
                {
                    Content = "✕",
                    Width = 22,
                    Height = 22,
                    Focusable = false,
                    Background = Brushes.Transparent,
                    Foreground = BrushFromHex("#9CA3AF"),
                    BorderThickness = new Thickness(0),
                    Cursor = Cursors.Hand,
                    FontSize = 10.5,
                    FontWeight = FontWeights.Bold,
                    Margin = new Thickness(6, 0, 0, 0)
                };
                rmBtn.Click += (_, e) =>
                {
                    e.Handled = true;
                    RemoveTrackAt(idx);
                };
                Grid.SetColumn(rmBtn, 1);
                g.Children.Add(rmBtn);

                row.MouseLeftButtonUp += (_, _) => PlayIndex(idx);
                row.Child = g;
                PlaylistItemsStack.Children.Add(row);
            }
        }

        private void Window_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                e.Effects = DragDropEffects.Copy;
                e.Handled = true;
            }
        }

        private void Window_Drop(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] files && files.Length > 0)
            {
                e.Handled = true;
                LoadPathsIntoPlayer(files, autoPlayFirst: true);
            }
        }
    }
}

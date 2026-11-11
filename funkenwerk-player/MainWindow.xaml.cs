using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Shapes;
using System.Windows.Threading;
using Microsoft.Win32;

namespace FunkenwerkPlayer
{
    public partial class MainWindow : Window
    {
        private readonly List<string> _initialArgs;
        private readonly List<string> _playlist = new();
        private readonly Dictionary<string, string> _trackDisplayTitles = new(StringComparer.OrdinalIgnoreCase);
        private int _currentIndex = -1;
        private string _currentFilePath = "";

        private bool _isDarkMode = true;
        private bool _isPlaying = false;
        private bool _isMuted = false;
        private bool _isLoopEnabled = true;
        private bool _isDraggingSlider = false;
        private bool _isFullscreen = false;
        private bool _isUniformToFill = false;

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
            ["#181B24"] = "#F8FAFC",
            ["#232734"] = "#E2E8F0",
            ["#262A36"] = "#CBD5E1",
            ["#26231F"] = "#FDF8ED",
            ["#F9FAFB"] = "#111827",
            ["#D1D5DB"] = "#374151",
            ["#9CA3AF"] = "#4B5563"
        };

        public MainWindow() : this(Array.Empty<string>())
        {
        }

        public MainWindow(IEnumerable<string> initialFiles)
        {
            _initialArgs = initialFiles?.ToList() ?? new List<string>();
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
            else
            {
                // Pre-populate playlist with any recent Desktop videos so user can click Play immediately if desired
                TryPopulateDesktopMediaQueue(autoPlay: false);
            }
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
            if (!_isDarkMode && _darkToLightHexMap.TryGetValue(hex, out var lightHex))
            {
                hex = lightHex;
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
            if (obj.ReadLocalValue(prop) is SolidColorBrush scb && scb.Color.A == 255)
            {
                string hex = $"#{scb.Color.R:X2}{scb.Color.G:X2}{scb.Color.B:X2}";
                if (!string.Equals(hex, "#000000", StringComparison.OrdinalIgnoreCase))
                {
                    _originalDarkBrushes[key] = hex;
                }
            }
        }

        private void ApplyThemeMode(bool isDark)
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

            RenderPlaylistUI();
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

        private void TryPopulateDesktopMediaQueue(bool autoPlay)
        {
            try
            {
                var discovered = new List<string>();
                string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                if (Directory.Exists(desktop))
                {
                    var desktopVideos = Directory.GetFiles(desktop)
                        .Where(f => WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(System.IO.Path.GetExtension(f)))
                        .Select(f => new FileInfo(f))
                        .OrderByDescending(f => f.LastWriteTimeUtc)
                        .Select(f => f.FullName);
                    discovered.AddRange(desktopVideos);
                }

                string musicCacheDir = System.IO.Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Funkenwerk", "MusicCache");
                if (Directory.Exists(musicCacheDir))
                {
                    string cacheJson = System.IO.Path.Combine(musicCacheDir, "playlists_cache.json");
                    if (File.Exists(cacheJson))
                    {
                        try
                        {
                            using var doc = System.Text.Json.JsonDocument.Parse(File.ReadAllText(cacheJson));
                            var root = doc.RootElement;
                            if (root.ValueKind == System.Text.Json.JsonValueKind.Object && root.TryGetProperty("data", out var dataArr) && dataArr.ValueKind == System.Text.Json.JsonValueKind.Array)
                            {
                                foreach (var pl in dataArr.EnumerateArray())
                                {
                                    if (!pl.TryGetProperty("tracks", out var tracksArr) || tracksArr.ValueKind != System.Text.Json.JsonValueKind.Array)
                                        continue;
                                    foreach (var tr in tracksArr.EnumerateArray())
                                    {
                                        string id = tr.TryGetProperty("id", out var idP) ? (idP.GetString() ?? "") : "";
                                        string extId = tr.TryGetProperty("external_id", out var exP) ? (exP.GetString() ?? "") : "";
                                        string title = tr.TryGetProperty("title", out var tiP) ? (tiP.GetString() ?? "") : "";
                                        string artist = tr.TryGetProperty("artist", out var arP) ? (arP.GetString() ?? "") : "";

                                        string candidate = System.IO.Path.Combine(musicCacheDir, $"{id}.m4a");
                                        if (!File.Exists(candidate) && !string.IsNullOrWhiteSpace(extId))
                                            candidate = System.IO.Path.Combine(musicCacheDir, $"{extId}.m4a");

                                        if (File.Exists(candidate))
                                        {
                                            string label = !string.IsNullOrWhiteSpace(artist) && !string.IsNullOrWhiteSpace(title)
                                                ? $"{artist} - {title}"
                                                : (!string.IsNullOrWhiteSpace(title) ? title : System.IO.Path.GetFileNameWithoutExtension(candidate));
                                            _trackDisplayTitles[candidate] = label;
                                            if (!discovered.Contains(candidate, StringComparer.OrdinalIgnoreCase))
                                                discovered.Add(candidate);
                                        }
                                    }
                                }
                            }
                        }
                        catch { }
                    }

                    foreach (var m4a in Directory.GetFiles(musicCacheDir, "*.m4a"))
                    {
                        if (!discovered.Contains(m4a, StringComparer.OrdinalIgnoreCase))
                            discovered.Add(m4a);
                    }
                }

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

        private void LoadPathsIntoPlayer(IEnumerable<string> paths, bool autoPlayFirst)
        {
            var collected = new List<string>();
            string? firstExplicitFile = null;

            foreach (var rawPath in paths)
            {
                if (string.IsNullOrWhiteSpace(rawPath)) continue;
                try
                {
                    string full = System.IO.Path.GetFullPath(rawPath);
                    if (Directory.Exists(full))
                    {
                        var dirFiles = Directory.GetFiles(full)
                            .Where(f => WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(System.IO.Path.GetExtension(f)))
                            .OrderBy(f => f, StringComparer.OrdinalIgnoreCase);
                        collected.AddRange(dirFiles);
                    }
                    else if (File.Exists(full) && WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(System.IO.Path.GetExtension(full)))
                    {
                        firstExplicitFile ??= full;
                        collected.Add(full);

                        // Also include sibling media files from the same directory so Next/Prev work seamlessly like VLC
                        string? parentDir = System.IO.Path.GetDirectoryName(full);
                        if (!string.IsNullOrWhiteSpace(parentDir) && Directory.Exists(parentDir))
                        {
                            var siblings = Directory.GetFiles(parentDir)
                                .Where(f => WindowsDefaultPlayerRegistrar.AllSupportedExtensions.Contains(System.IO.Path.GetExtension(f)))
                                .OrderBy(f => f, StringComparer.OrdinalIgnoreCase);
                            foreach (var sib in siblings)
                            {
                                if (!collected.Contains(sib, StringComparer.OrdinalIgnoreCase))
                                    collected.Add(sib);
                            }
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

        private void PlayIndex(int index)
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
                : System.IO.Path.GetFileName(path);
            string ext = System.IO.Path.GetExtension(path).TrimStart('.').ToUpperInvariant();

            Title = $"{fileName} - Funkenwerk Player";
            CurrentTitleTxt.Text = fileName;
            MediaFormatBadgeTxt.Text = ext;
            MediaFormatBadge.Visibility = Visibility.Visible;
            EmptyStateOverlay.Visibility = Visibility.Collapsed;

            bool isAudio = WindowsDefaultPlayerRegistrar.IsAudioExtension(System.IO.Path.GetExtension(path));
            if (isAudio)
            {
                AudioTrackTitleTxt.Text = _trackDisplayTitles.TryGetValue(path, out string? niceAudioTitle) && !string.IsNullOrWhiteSpace(niceAudioTitle)
                    ? niceAudioTitle
                    : System.IO.Path.GetFileNameWithoutExtension(path);
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
            if (vw > 0 && vh > 0)
            {
                string ext = System.IO.Path.GetExtension(_currentFilePath).TrimStart('.').ToUpperInvariant();
                MediaFormatBadgeTxt.Text = $"{vw}x{vh} · {ext}";
            }
        }

        private void PlayerMediaElement_MediaEnded(object sender, RoutedEventArgs e)
        {
            if (_isLoopEnabled)
            {
                PlayerMediaElement.Position = TimeSpan.Zero;
                PlayerMediaElement.Play();
                return;
            }

            if (_currentIndex + 1 < _playlist.Count)
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

        private void PlayerMediaElement_MediaFailed(object? sender, ExceptionRoutedEventArgs e)
        {
            ShowOsdToast($"⚠ Wiedergabefehler: {e.ErrorException?.Message ?? "Format"}");
        }

        private void PlaybackTimer_Tick(object? sender, EventArgs e)
        {
            if (_isDraggingSlider || !PlayerMediaElement.NaturalDuration.HasTimeSpan) return;

            TimeSpan pos = PlayerMediaElement.Position;
            TimeSpan total = PlayerMediaElement.NaturalDuration.TimeSpan;

            if (_abLoopState == 2 && pos >= _loopPointB)
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
            if (ts.TotalHours >= 1.0)
                return $"{(int)ts.TotalHours:D2}:{ts.Minutes:D2}:{ts.Seconds:D2}";
            return $"{ts.Minutes:D2}:{ts.Seconds:D2}";
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
            double total = PlayerMediaElement.NaturalDuration.TimeSpan.TotalSeconds;
            double target = Math.Clamp(PlayerMediaElement.Position.TotalSeconds + deltaSeconds, 0.0, total);
            PlayerMediaElement.Position = TimeSpan.FromSeconds(target);
            TimelineSlider.Value = target;
            TimeElapsedTxt.Text = FormatTime(PlayerMediaElement.Position);
            ShowOsdToast($"{(deltaSeconds >= 0 ? "⏩ +" : "⏪ ")}{deltaSeconds:F0}s ({FormatTime(PlayerMediaElement.Position)})");
        }

        private void SkipBackward_Click(object sender, RoutedEventArgs e) => SeekRelative(-5.0);
        private void SkipForward_Click(object sender, RoutedEventArgs e) => SeekRelative(5.0);

        private void PrevTrack_Click(object sender, RoutedEventArgs e)
        {
            if (_playlist.Count == 0) return;
            int prev = (_currentIndex - 1 + _playlist.Count) % _playlist.Count;
            PlayIndex(prev);
        }

        private void NextTrack_Click(object sender, RoutedEventArgs e)
        {
            if (_playlist.Count == 0) return;
            int next = (_currentIndex + 1) % _playlist.Count;
            PlayIndex(next);
        }

        private void TimelineSlider_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            _isDraggingSlider = true;
        }

        private void TimelineSlider_PreviewMouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            _isDraggingSlider = false;
            if (!string.IsNullOrWhiteSpace(_currentFilePath) && PlayerMediaElement.NaturalDuration.HasTimeSpan)
            {
                PlayerMediaElement.Position = TimeSpan.FromSeconds(TimelineSlider.Value);
                TimeElapsedTxt.Text = FormatTime(PlayerMediaElement.Position);
            }
        }

        private void TimelineSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_isDraggingSlider && !string.IsNullOrWhiteSpace(_currentFilePath))
            {
                var pos = TimeSpan.FromSeconds(TimelineSlider.Value);
                PlayerMediaElement.Position = pos;
                TimeElapsedTxt.Text = FormatTime(pos);
            }
        }

        private void VolumeSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (PlayerMediaElement == null || VolumePercentTxt == null) return;
            int vol = (int)Math.Round(VolumeSlider.Value);
            VolumePercentTxt.Text = $"{vol}%";
            if (!_isMuted)
            {
                PlayerMediaElement.Volume = vol / 100.0;
            }
            if (MuteBtn != null)
            {
                MuteBtn.Content = (_isMuted || vol == 0) ? "🔇" : (vol < 45 ? "🔉" : "🔊");
            }
        }

        private void Mute_Click(object sender, RoutedEventArgs e)
        {
            _isMuted = !_isMuted;
            PlayerMediaElement.Volume = _isMuted ? 0.0 : (VolumeSlider.Value / 100.0);
            MuteBtn.Content = _isMuted ? "🔇" : "🔊";
            ShowOsdToast(_isMuted ? "🔇 Stumm" : $"🔊 {(int)VolumeSlider.Value}%");
        }

        private void LoopToggle_Click(object sender, RoutedEventArgs e)
        {
            _isLoopEnabled = !_isLoopEnabled;
            LoopBtn.Content = _isLoopEnabled ? "🔁 Loop An" : "🔁 Loop Aus";
            LoopBtn.Foreground = _isLoopEnabled ? BrushFromHex("#C5A059") : BrushFromHex("#9CA3AF");
            LoopBtn.BorderBrush = _isLoopEnabled ? BrushFromHex("#C5A059") : BrushFromHex("#262A36");
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
                AbLoopBtn.Foreground = BrushFromHex("#C5A059");
                ShowOsdToast($"🔁 Punkt A gesetzt ({FormatTime(_loopPointA)})");
            }
            else if (_abLoopState == 1)
            {
                if (PlayerMediaElement.Position > _loopPointA + TimeSpan.FromMilliseconds(300))
                {
                    _loopPointB = PlayerMediaElement.Position;
                    _abLoopState = 2;
                    AbLoopBtn.Content = $"{FormatTime(_loopPointA)}↔{FormatTime(_loopPointB)}";
                    ShowOsdToast($"🔁 A-B Loop aktiv ({FormatTime(_loopPointA)} - {FormatTime(_loopPointB)})");
                }
                else
                {
                    _abLoopState = 0;
                    AbLoopBtn.Content = "A↔B";
                    AbLoopBtn.Foreground = BrushFromHex("#D1D5DB");
                    ShowOsdToast("🔁 A-B Loop aufgehoben");
                }
            }
            else
            {
                _abLoopState = 0;
                AbLoopBtn.Content = "A↔B";
                AbLoopBtn.Foreground = BrushFromHex("#D1D5DB");
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

        private void Snapshot_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_currentFilePath) || VideoStageGrid.ActualWidth < 10) return;

            try
            {
                int w = (int)Math.Max(100, PlayerMediaElement.ActualWidth);
                int h = (int)Math.Max(100, PlayerMediaElement.ActualHeight);
                var rtb = new RenderTargetBitmap(w, h, 96, 96, PixelFormats.Pbgra32);
                rtb.Render(PlayerMediaElement);

                var encoder = new PngBitmapEncoder();
                encoder.Frames.Add(BitmapFrame.Create(rtb));

                string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                string snapName = $"Funkenwerk_Snapshot_{DateTime.Now:yyyyMMdd_HHmmss}.png";
                string snapPath = System.IO.Path.Combine(desktop, snapName);

                using (var fs = new FileStream(snapPath, FileMode.Create, FileAccess.Write))
                {
                    encoder.Save(fs);
                }

                ShowOsdToast($"📷 Screenshot auf Desktop: {snapName}");
            }
            catch { }
        }

        private void ToggleFullscreen()
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
                    CenterWindowOnActiveScreen();
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
            VolumeSlider.Value = Math.Clamp(VolumeSlider.Value + delta, 0.0, 100.0);
            ShowOsdToast($"🔊 Lautstärke: {(int)Math.Round(VolumeSlider.Value)}%");
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

        private void Window_KeyDown(object sender, KeyEventArgs e)
        {
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
                    VolumeSlider.Value = Math.Clamp(VolumeSlider.Value + 5, 0, 100);
                    ShowOsdToast($"🔊 {(int)VolumeSlider.Value}%");
                    break;
                case Key.Down:
                    e.Handled = true;
                    VolumeSlider.Value = Math.Clamp(VolumeSlider.Value - 5, 0, 100);
                    ShowOsdToast($"🔊 {(int)VolumeSlider.Value}%");
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
                    NextTrack_Click(this, new RoutedEventArgs());
                    break;
                case Key.P:
                    e.Handled = true;
                    PrevTrack_Click(this, new RoutedEventArgs());
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
            bool ok = WindowsDefaultPlayerRegistrar.RegisterAsWindowsMediaPlayer(out _);
            if (ok)
            {
                DefaultPlayerBtn.Content = "✓ Standard-Player aktiv";
                ShowOsdToast("★ Als Windows Mediaplayer registriert!");

                // If we have a current media file (or sample on Desktop), open Windows 'Open With' / Default Apps so user can confirm 'Always' if VLC held UserChoice
                string sampleTarget = _currentFilePath;
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

        private void ClearPlaylist_Click(object sender, RoutedEventArgs e)
        {
            _playlist.Clear();
            _currentIndex = -1;
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
                    : System.IO.Path.GetFileName(filePath);
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
                    Background = Brushes.Transparent,
                    Foreground = BrushFromHex("#9CA3AF"),
                    BorderThickness = new Thickness(0),
                    Cursor = Cursors.Hand,
                    FontSize = 10.5,
                    FontWeight = FontWeights.Bold,
                    Margin = new Thickness(6, 0, 0, 0)
                };
                rmBtn.Click += (s, e) =>
                {
                    e.Handled = true;
                    _playlist.RemoveAt(idx);
                    if (_currentIndex == idx) _currentIndex = -1;
                    else if (_currentIndex > idx) _currentIndex--;
                    RenderPlaylistUI();
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

using System;
using System.Collections.Generic;
using System.IO;
using System.IO.Pipes;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace Funkenwerk
{
    public sealed class MarketingHostLayoutSettings
    {
        public bool IsDarkMode { get; set; } = true;
        public bool CompactMode { get; set; } = false;
        public int SoundVolume { get; set; } = 85;
    }

    public sealed class TaskItemDto
    {
        public string Id { get; set; } = "";
        public string Title { get; set; } = "";
        public string Priority { get; set; } = "normal";
        public bool IsCompleted { get; set; }
    }

    public partial class MainWindow : Window
    {
        private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(15) };
        private readonly MarketingHostLayoutSettings _layoutSettings = new();
        private readonly List<TaskItemDto> _allTasks = new();
        private string _apiBaseUrl = "https://stage.mein-seelenfunke.de/api";
        private string _apiToken = "";
        private bool _isDarkMode = true;

        private readonly Dictionary<(DependencyObject Obj, DependencyProperty Prop), string> _originalDarkBrushes = new();
        private static readonly Dictionary<string, string> _darkToLightHexMap = new(StringComparer.OrdinalIgnoreCase)
        {
            ["#0B0D12"] = "#F8FAFC",
            ["#0F1117"] = "#F3F4F6",
            ["#11131A"] = "#FFFFFF",
            ["#13161F"] = "#FFFFFF",
            ["#141720"] = "#FFFFFF",
            ["#151821"] = "#FFFFFF",
            ["#151822"] = "#FFFFFF",
            ["#181B24"] = "#F8FAFC",
            ["#1E222D"] = "#F1F5F9",
            ["#222634"] = "#E2E8F0",
            ["#262A36"] = "#CBD5E1",
            ["#26231F"] = "#FDF8ED",
            ["#F9FAFB"] = "#111827",
            ["#E5E7EB"] = "#1F2937",
            ["#D1D5DB"] = "#374151",
            ["#9CA3AF"] = "#4B5563"
        };

        public bool IsDarkMode => _isDarkMode;

        public MainWindow()
        {
            InitializeComponent();
            var shared = FunkenwerkSharedSession.Load();
            if (!string.IsNullOrWhiteSpace(shared.ApiBaseUrl)) _apiBaseUrl = shared.ApiBaseUrl;
            if (!string.IsNullOrWhiteSpace(shared.BearerToken))
            {
                _apiToken = shared.BearerToken;
                _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", _apiToken);
            }
            _isDarkMode = shared.IsDarkMode;
            _layoutSettings.IsDarkMode = _isDarkMode;

            Loaded += MainWindow_Loaded;
            Closed += (_, _) =>
            {
                try { _mktMusicPreviewPlayer.Stop(); _mktMusicPreviewPlayer.Close(); } catch { }
            };
        }

        private void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            CaptureOriginalDarkBrushes(this);
            EnsureMarketingInitialized();
            ApplyStandaloneTheme(_isDarkMode);
        }

        private SolidColorBrush BrushFromHex(string hex)
        {
            if (!_isDarkMode && _darkToLightHexMap.TryGetValue(hex, out string? light))
            {
                hex = light;
            }
            return new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
        }

        private void CaptureOriginalDarkBrushes(DependencyObject parent, HashSet<DependencyObject>? visited = null)
        {
            if (parent == null) return;
            visited ??= new HashSet<DependencyObject>();
            if (!visited.Add(parent)) return;

            foreach (var prop in new[] { Border.BackgroundProperty, Border.BorderBrushProperty, Panel.BackgroundProperty, Control.BackgroundProperty, Control.BorderBrushProperty, Control.ForegroundProperty, TextBlock.ForegroundProperty })
            {
                if (parent.ReadLocalValue(prop) is SolidColorBrush scb && scb.Color.A == 255)
                {
                    string hex = $"#{scb.Color.R:X2}{scb.Color.G:X2}{scb.Color.B:X2}";
                    _originalDarkBrushes.TryAdd((parent, prop), hex);
                }
            }
            foreach (var child in LogicalTreeHelper.GetChildren(parent))
                if (child is DependencyObject d) CaptureOriginalDarkBrushes(d, visited);
            if (parent is Visual)
            {
                int count = VisualTreeHelper.GetChildrenCount(parent);
                for (int i = 0; i < count; i++) CaptureOriginalDarkBrushes(VisualTreeHelper.GetChild(parent, i), visited);
            }
        }

        public void ApplyStandaloneTheme(bool isDark)
        {
            _isDarkMode = isDark;
            _layoutSettings.IsDarkMode = isDark;
            foreach (var kv in _originalDarkBrushes)
            {
                try { kv.Key.Obj.SetValue(kv.Key.Prop, BrushFromHex(kv.Value)); } catch { }
            }
            if (StandaloneThemeBtn != null) StandaloneThemeBtn.Content = isDark ? "🌙" : "☀";
            if (_isMarketingInitialized)
            {
                try { RenderMarketingThemeRefresh(); } catch { }
            }
        }

        private void StandaloneThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            ApplyStandaloneTheme(!_isDarkMode);
            var s = FunkenwerkSharedSession.Load();
            s.IsDarkMode = _isDarkMode;
            FunkenwerkSharedSession.Save(s);
        }

        private static void PauseExternalPlayerIfRunning()
        {
            _ = Task.Run(async () =>
            {
                try
                {
                    using var client = new NamedPipeClientStream(".", "Funkenwerk_PlayerPipe_v1", PipeDirection.InOut, PipeOptions.Asynchronous);
                    await client.ConnectAsync(250);
                    using var writer = new StreamWriter(client, new UTF8Encoding(false), 1024, leaveOpen: true) { AutoFlush = true };
                    await writer.WriteLineAsync("{\"action\":\"pause\"}");
                }
                catch { }
            });
        }

        private Task LoadTodoListsAsync() => Task.CompletedTask;
        private void UpdateSpacesBadges() { }
        private void UpdateMiniPlayerVisibility() { }
        private void SaveSettingsToFile() { }
        private void PlaySoundNav() { }
        private void PlaySoundActionPop() { }
        private void PlaySoundTaskDone() { }
        private void PlaySoundMailChime() { }
    }
}

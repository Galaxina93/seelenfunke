using System;
using System.Collections.Generic;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace Funkenwerk
{
    public sealed class CameraHostLayoutSettings
    {
        public bool IsDarkMode { get; set; } = true;
        public bool DarkMode { get => IsDarkMode; set => IsDarkMode = value; }
        public string CameraMediaDirectory { get; set; } = "";
        public string SelectedMicrophoneName { get; set; } = "";
        public string PrimaryCameraName { get; set; } = "";
    }

    public static class FunkenwerkSoundEngine
    {
        public static void PlayCountdownBeep(double volume = 0.6) { }
        public static void PlayCameraShutter(double volume = 0.8) { }
    }

    public partial class MainWindow : Window
    {
        private readonly CameraHostLayoutSettings _layoutSettings = new();
        private bool _isDarkMode = true;
        private string _activeSpace = "camera";
        private Button? SpaceCameraBtn => null;
        private ComboBox? SettingsMicrophoneCombo => null;

        private bool _brushesCaptured;
        private readonly Dictionary<(DependencyObject Obj, DependencyProperty Prop), string> _originalDarkBrushes = new();

        private static readonly HashSet<string> _solidAccentBgHexes = new(StringComparer.OrdinalIgnoreCase)
        {
            "#C5A059",
            "#DA373C",
            "#DC2626",
            "#991B1B",
            "#23A55A"
        };

        private static readonly Dictionary<string, string> _darkToLightHexMap = new(StringComparer.OrdinalIgnoreCase)
        {
            // Dark surfaces -> crisp Light surfaces
            ["#0B0D12"] = "#FFFFFF",
            ["#0D0F14"] = "#E2E8F0",
            ["#0F1117"] = "#F3F4F6",
            ["#0F172A"] = "#F8FAFC",
            ["#111214"] = "#E2E8F0",
            ["#11131A"] = "#F8FAFC",
            ["#13161F"] = "#F1F5F9",
            ["#141720"] = "#FFFFFF",
            ["#151821"] = "#FFFFFF",
            ["#161922"] = "#FFFFFF",
            ["#181B24"] = "#FFFFFF",
            ["#1E1F22"] = "#F8FAFC",
            ["#1E222D"] = "#F1F5F9",
            ["#1E293B"] = "#E2E8F0",
            ["#1F222D"] = "#E2E8F0",
            ["#1F2430"] = "#EEF2F7",
            ["#222634"] = "#CBD5E1",
            ["#232734"] = "#E2E8F0",
            ["#262A36"] = "#CBD5E1",
            ["#2B2D31"] = "#E2E8F0",
            ["#2E3446"] = "#CBD5E1",
            ["#374151"] = "#CBD5E1",
            ["#3F4147"] = "#CBD5E1",
            ["#26231F"] = "#FEF9EC",
            ["#2E291F"] = "#FEF3C7",
            ["#382D18"] = "#E5CFA0",
            ["#163026"] = "#ECFDF5",
            ["#064E3B"] = "#ECFDF5",
            ["#2D161B"] = "#FEF2F2",

            // Light foregrounds -> high-contrast Dark foregrounds on Light surfaces
            ["#FFFFFF"] = "#111827",
            ["#F9FAFB"] = "#111827",
            ["#F2F3F5"] = "#111827",
            ["#ECFDF5"] = "#065F46",
            ["#A7F3D0"] = "#047857",
            ["#E5E7EB"] = "#1F2937",
            ["#D1D5DB"] = "#374151",
            ["#B5BAC1"] = "#374151",
            ["#9CA3AF"] = "#4B5563",
            ["#949BA4"] = "#4B5563",
            ["#6B7280"] = "#4B5563",
            ["#34D399"] = "#047857",
            ["#38BDF8"] = "#0369A1",
            ["#60A5FA"] = "#1D4ED8",
            ["#5865F2"] = "#2563EB",
            ["#F87171"] = "#DC2626",
            ["#FBBF24"] = "#B45309",
            ["#FB923C"] = "#C2410C"
        };

        public bool IsDarkMode => _isDarkMode;

        public MainWindow()
        {
            InitializeComponent();
            var shared = FunkenwerkSharedSession.Load();
            _isDarkMode = shared.IsDarkMode;
            _layoutSettings.IsDarkMode = _isDarkMode;

            Loaded += MainWindow_Loaded;
            Closed += (_, _) =>
            {
                try { ShutdownCameraStudioEngine(); } catch { }
            };
        }

        private void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            EnsureOriginalBrushesCaptured();
            EnsureCameraStudioInitialized();
            ApplyStandaloneTheme(_isDarkMode);
        }

        public void InitializeForSelfTest()
        {
            FunkenwerkVersioning.EnsureAttached(this);
            EnsureOriginalBrushesCaptured();
            LoadCameraStudioConfig();
            if (_camConfig.VideoSignalClips.Count == 0)
            {
                _camConfig.VideoSignalClips.Add(new VideoSignalClipItem { Title = "SelfTest_TarnClip", FilePath = "selftest.mp4", AutoStopDurationSec = 10 });
            }
            if (_camConfig.MusicSignalClips.Count == 0)
            {
                _camConfig.MusicSignalClips.Add(new MusicSignalClipItem { Title = "SelfTest_Song", FilePath = "selftest.mp3", AutoStopDurationSec = 10 });
            }
            ApplyStandaloneTheme(_isDarkMode);
        }

        private static SolidColorBrush DirectHexBrush(string hex) =>
            new((Color)ColorConverter.ConvertFromString(hex));

        private SolidColorBrush BrushFromHex(string hex)
        {
            if (!_isDarkMode && _darkToLightHexMap.TryGetValue(hex, out string? light))
            {
                hex = light;
            }
            return DirectHexBrush(hex);
        }

        private static SolidColorBrush MapCapturedPropertyBrush(string origHex, DependencyProperty prop, bool isDark)
        {
            if (isDark) return DirectHexBrush(origHex);

            bool isForeground = prop == Control.ForegroundProperty || prop == TextBlock.ForegroundProperty;
            if (isForeground && string.Equals(origHex, "#C5A059", StringComparison.OrdinalIgnoreCase))
            {
                return DirectHexBrush("#8A6522");
            }
            if (_darkToLightHexMap.TryGetValue(origHex, out string? mapped))
            {
                return DirectHexBrush(mapped);
            }
            return DirectHexBrush(origHex);
        }

        private static bool HasSolidAccentBackground(DependencyObject obj)
        {
            foreach (var bgProp in new[] { Control.BackgroundProperty, Border.BackgroundProperty, Panel.BackgroundProperty })
            {
                if (obj.ReadLocalValue(bgProp) is SolidColorBrush scb && scb.Color.A == 255)
                {
                    string hex = $"#{scb.Color.R:X2}{scb.Color.G:X2}{scb.Color.B:X2}";
                    if (_solidAccentBgHexes.Contains(hex)) return true;
                }
            }
            return false;
        }

        private void EnsureOriginalBrushesCaptured()
        {
            if (_brushesCaptured) return;
            _brushesCaptured = true;
            CaptureOriginalDarkBrushes(this, insideFixedOverlay: false, insideSolidAccent: false, visited: new HashSet<DependencyObject>());
        }

        private void CaptureOriginalDarkBrushes(
            DependencyObject? parent,
            bool insideFixedOverlay,
            bool insideSolidAccent,
            HashSet<DependencyObject> visited)
        {
            if (parent == null || !visited.Add(parent)) return;

            if (parent is FrameworkElement fe &&
                fe.Tag is string tagStr &&
                string.Equals(tagStr, "FixedDarkOverlay", StringComparison.OrdinalIgnoreCase))
            {
                insideFixedOverlay = true;
            }

            if (HasSolidAccentBackground(parent))
            {
                insideSolidAccent = true;
            }

            if (!insideFixedOverlay && !insideSolidAccent)
            {
                foreach (var prop in new[]
                {
                    Window.BackgroundProperty,
                    Window.ForegroundProperty,
                    Border.BackgroundProperty,
                    Border.BorderBrushProperty,
                    Panel.BackgroundProperty,
                    Control.BackgroundProperty,
                    Control.BorderBrushProperty,
                    Control.ForegroundProperty,
                    TextBlock.ForegroundProperty
                })
                {
                    if (parent.ReadLocalValue(prop) is SolidColorBrush scb && scb.Color.A == 255)
                    {
                        string hex = $"#{scb.Color.R:X2}{scb.Color.G:X2}{scb.Color.B:X2}";
                        if (!string.Equals(hex, "#000000", StringComparison.OrdinalIgnoreCase))
                        {
                            _originalDarkBrushes.TryAdd((parent, prop), hex);
                        }
                    }
                }
            }

            foreach (var child in LogicalTreeHelper.GetChildren(parent))
            {
                if (child is DependencyObject d)
                    CaptureOriginalDarkBrushes(d, insideFixedOverlay, insideSolidAccent, visited);
            }
            if (parent is Visual)
            {
                int count = VisualTreeHelper.GetChildrenCount(parent);
                for (int i = 0; i < count; i++)
                    CaptureOriginalDarkBrushes(VisualTreeHelper.GetChild(parent, i), insideFixedOverlay, insideSolidAccent, visited);
            }
        }

        public void ApplyStandaloneTheme(bool isDark)
        {
            EnsureOriginalBrushesCaptured();
            _isDarkMode = isDark;
            _layoutSettings.IsDarkMode = isDark;

            Resources["CamComboBgBrush"] = DirectHexBrush(isDark ? "#181B24" : "#FFFFFF");
            Resources["CamComboFgBrush"] = DirectHexBrush(isDark ? "#F9FAFB" : "#111827");
            Resources["CamComboBorderBrush"] = DirectHexBrush(isDark ? "#2E3446" : "#CBD5E1");
            Resources["CamComboPopupBgBrush"] = DirectHexBrush(isDark ? "#141720" : "#FFFFFF");
            Resources["CamComboItemHoverBrush"] = DirectHexBrush(isDark ? "#26231F" : "#FEF9EC");
            Resources["CamScrollThumbBrush"] = DirectHexBrush(isDark ? "#374151" : "#CBD5E1");

            foreach (var kv in _originalDarkBrushes)
            {
                try { kv.Key.Obj.SetValue(kv.Key.Prop, MapCapturedPropertyBrush(kv.Value, kv.Key.Prop, isDark)); } catch { }
            }

            if (CamViewportContainerBorder != null)
            {
                CamViewportContainerBorder.BorderBrush = DirectHexBrush(isDark ? "#222634" : "#CBD5E1");
            }
            if (StandaloneThemeBtn != null)
            {
                StandaloneThemeBtn.Content = isDark ? "🌙" : "☀";
            }

            RefreshAllCameraThemeDependentUi();
            FunkenwerkVersioning.SyncWindowTheme(this);
        }

        private void RefreshAllCameraThemeDependentUi()
        {
            try { SwitchCameraInspectorTab(_activeCamInspectorTab); } catch { }
            try { UpdateHardwareCameraStartStopButtonUI(); } catch { }
            try { UpdateVirtualCamButtonUI(); } catch { }
            try { UpdateSplitModeButtonsUI(); } catch { }
            try { UpdatePipQuickUiState(); } catch { }
            try { UpdateMicMuteButtonUI(_camConfig.MicMuted); } catch { }
            try { UpdateSoundMuteButtonUI(_camConfig.SoundMuted); } catch { }
            try { UpdateRecordingSpeedButtonsUI(); } catch { }
            try { UpdateVoicePresetButtonsUI(); } catch { }
            try { UpdateTarnDurationButtonsUI(); } catch { }
            try { RenderVideoSignalDeckList(); } catch { }
            try { RenderMusicSignalDeckList(); } catch { }
            try { UpdateBeautyFilterButtonsUI(); } catch { }
            try { UpdateColorFilterButtonsUI(); } catch { }
            try { UpdateDistortionFilterButtonsUI(); } catch { }
            try { UpdateDeepFakeArButtonsUI(); } catch { }
            try { RenderDeepFakePresetsUI(); } catch { }
            try { RenderCameraMediaGallery(); } catch { }
            try { UpdatePreviewSpeedButtonsUI(); } catch { }
        }

        private void StandaloneThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            ApplyStandaloneTheme(!_isDarkMode);
            var s = FunkenwerkSharedSession.Load();
            s.IsDarkMode = _isDarkMode;
            FunkenwerkSharedSession.Save(s);
        }

        private void CamOpenCameraSettings_Click(object sender, RoutedEventArgs e)
        {
            LoadCameraStudioConfig();
            CamSettingsPathBox.Text = GetEffectiveCameraMediaDir();
            CamSettingsModalOverlay.Visibility = Visibility.Visible;
        }

        private void CloseCamSettingsModal_Click(object sender, RoutedEventArgs e) =>
            CamSettingsModalOverlay.Visibility = Visibility.Collapsed;

        private void BrowseCamSettingsFolder_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFolderDialog
            {
                Title = "Speicherordner für Kamera-Medien wählen",
                InitialDirectory = GetEffectiveCameraMediaDir()
            };
            if (dlg.ShowDialog() == true && !string.IsNullOrWhiteSpace(dlg.FolderName))
            {
                CamSettingsPathBox.Text = dlg.FolderName;
            }
        }

        private void RegisterVirtualCamFromModal_Click(object sender, RoutedEventArgs e)
        {
            var (_, msg) = FunkenwerkVirtualCamDriver.EnsureRegistered(_camConfig.Width, _camConfig.Height, _camConfig.Fps);
            MessageBox.Show(msg, "Funkenwerk Virtuelle Kamera", MessageBoxButton.OK, MessageBoxImage.Information);
        }

        private void SaveCamSettingsModal_Click(object sender, RoutedEventArgs e)
        {
            string p = CamSettingsPathBox.Text.Trim();
            if (!string.IsNullOrWhiteSpace(p))
            {
                try
                {
                    Directory.CreateDirectory(p);
                    _camConfig.MediaDirectory = p;
                    _layoutSettings.CameraMediaDirectory = p;
                    SaveCameraStudioConfig();
                    RenderCameraMediaGallery();
                }
                catch { }
            }
            CamSettingsModalOverlay.Visibility = Visibility.Collapsed;
        }

        private static string ResolveFfmpegBinaryPath()
        {
            string localExe = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "ffmpeg.exe");
            if (File.Exists(localExe)) return localExe;
            string localToolsExe = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "tools", "ffmpeg.exe");
            if (File.Exists(localToolsExe)) return localToolsExe;
            return "ffmpeg";
        }

        private static void RevealFileInExplorerSafely(string filePath)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath)) return;
                var psi = new System.Diagnostics.ProcessStartInfo
                {
                    FileName = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows), "explorer.exe"),
                    UseShellExecute = false
                };
                psi.ArgumentList.Add("/select," + Path.GetFullPath(filePath));
                System.Diagnostics.Process.Start(psi);
            }
            catch { }
        }

        private static void OpenDirectoryInExplorerSafely(string dirPath)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(dirPath) || !Directory.Exists(dirPath)) return;
                var psi = new System.Diagnostics.ProcessStartInfo
                {
                    FileName = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows), "explorer.exe"),
                    UseShellExecute = false
                };
                psi.ArgumentList.Add(Path.GetFullPath(dirPath));
                System.Diagnostics.Process.Start(psi);
            }
            catch { }
        }

        private void SaveSettingsToFile() { }
        private void PlaySoundNav() { }
        private void PlaySoundActionPop() { }
        private void PlaySoundSmartPtzTick() { }
        private void PlaySoundTaskDone() { }
        private void PlaySoundMailChime() { }
    }
}

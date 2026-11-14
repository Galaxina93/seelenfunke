using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Threading;

namespace FunkenwerkCraft
{
    public partial class MainWindow : Window
    {
        private static readonly (string Value, string Label)[] FitModeOptions =
        [
            ("fill", "Vollbild"),
            ("contain_blur", "Einpassen (Blur)")
        ];

        private static readonly (string Value, string Label)[] SpeedModeOptions =
        [
            ("speed_ramp", "Speed-Ramp"),
            ("slow_motion", "Slow-Motion"),
            ("linear", "Linear")
        ];

        private static readonly (string Value, string Label)[] TransitionOptions =
        [
            ("whip_zoom", "Whip Zoom"),
            ("whip_pan", "Whip Pan"),
            ("velvet_dissolve", "Velvet Dissolve"),
            ("dark_cut", "Dark Cut"),
            ("flash_cut", "Flash Cut"),
            ("smoothleft", "Smooth Slide")
        ];

        private CraftSettingsModel _settings;
        private CraftProjectModel _currentProject;
        private string _soundFilter = "all";
        private bool _isRendering;
        private bool _suppressUiEvents;
        private bool _hasPendingAutoSave;
        private int _selectedSceneIndex;
        private readonly List<Border> _sceneCardBorders = new();
        private readonly MediaPlayer _sfxPreviewPlayer = new();
        private readonly DispatcherTimer _autoSaveTimer;
        private CancellationTokenSource? _ipcCts;

        internal CraftProjectModel CurrentProject => _currentProject;
        internal bool IsDarkMode => _settings.DarkMode;
        internal int SelectedSceneIndex
        {
            get => _selectedSceneIndex;
            set => SetSelectedSceneIndex(value);
        }

        public MainWindow(string? initialProject = null, bool startIpcServer = true)
        {
            InitializeComponent();

            _autoSaveTimer = new DispatcherTimer
            {
                Interval = TimeSpan.FromMilliseconds(350)
            };
            _autoSaveTimer.Tick += (_, _) => FlushAutoSave();

            _settings = CraftEngine.LoadSettings();
            ApplyTheme(_settings.DarkMode);

            string targetProj = !string.IsNullOrWhiteSpace(initialProject)
                ? initialProject!
                : _settings.LastProjectName;

            try
            {
                _currentProject = CraftEngine.LoadOrCreateProject(targetProj);
            }
            catch
            {
                _currentProject = CraftEngine.LoadOrCreateProject("Projekt 1");
            }

            RefreshAllUi();

            if (startIpcServer)
            {
                Loaded += (_, _) => StartNamedPipeServer();
            }

            Closed += (_, _) =>
            {
                FlushAutoSave();
                _autoSaveTimer.Stop();
                _ipcCts?.Cancel();
                ReleasePreviewMediaLock();
                _sfxPreviewPlayer.Close();
            };
        }

        internal void SetThemeForTest(bool dark)
        {
            ApplyTheme(dark);
            RefreshAllUi();
        }

        internal void AssignEffectToSelectedScene(CraftSoundItem sound)
        {
            if (_currentProject.Scenes.Count == 0)
            {
                StatusBannerTxt.Text = "Keine Szene vorhanden: Bitte zuerst Medien hinzufuegen.";
                return;
            }

            string projDir = CraftEngine.GetProjectDirectory(_currentProject.Name);
            _ = CraftEngine.ResolveSafeAudioPath(sound.RelativePath, projDir, requireExists: false);

            int targetIdx = Math.Clamp(_selectedSceneIndex, 0, _currentProject.Scenes.Count - 1);
            _currentProject.Scenes[targetIdx].TransitionSfx = sound.RelativePath;
            CraftEngine.SaveProject(_currentProject);
            _hasPendingAutoSave = false;
            StatusBannerTxt.Text = $"Effekt fuer Szene #{targetIdx + 1} gesetzt: {sound.FileName}";
        }

        private void ScheduleAutoSave()
        {
            if (_suppressUiEvents || _currentProject == null) return;
            _hasPendingAutoSave = true;
            _autoSaveTimer.Stop();
            _autoSaveTimer.Start();
        }

        internal void FlushAutoSave()
        {
            _autoSaveTimer.Stop();
            if (!_hasPendingAutoSave || _currentProject == null) return;
            try
            {
                CraftEngine.SaveProject(_currentProject);
                _hasPendingAutoSave = false;
            }
            catch (Exception ex)
            {
                StatusBannerTxt.Text = ex.Message;
            }
        }

        private void SetSelectedSceneIndex(int index)
        {
            if (_currentProject.Scenes.Count == 0)
            {
                _selectedSceneIndex = 0;
                return;
            }

            _selectedSceneIndex = Math.Clamp(index, 0, _currentProject.Scenes.Count - 1);
            for (int i = 0; i < _sceneCardBorders.Count; i++)
            {
                bool isSelected = i == _selectedSceneIndex;
                _sceneCardBorders[i].BorderBrush = (System.Windows.Media.Brush)Resources[isSelected ? "GoldBrush" : "BorderSubtleBrush"];
            }
        }

        private static void OpenFolderInExplorerSafe(string folderPath)
        {
            if (string.IsNullOrWhiteSpace(folderPath) || !Directory.Exists(folderPath))
                return;

            string explorerExe = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows), "explorer.exe");
            if (!File.Exists(explorerExe)) return;

            var psi = new ProcessStartInfo
            {
                FileName = explorerExe,
                UseShellExecute = false
            };
            psi.ArgumentList.Add(Path.GetFullPath(folderPath));
            Process.Start(psi);
        }

        private void ApplyTheme(bool dark)
        {
            _settings.DarkMode = dark;
            CraftEngine.SaveSettings(_settings);

            if (dark)
            {
                Resources["BgMainBrush"] = BrushHex("#0F1117");
                Resources["BgPanelBrush"] = BrushHex("#141720");
                Resources["BgCardBrush"] = BrushHex("#1B1F2B");
                Resources["BgInputBrush"] = BrushHex("#0F1219");
                Resources["BorderSubtleBrush"] = BrushHex("#262B3A");
                Resources["GoldBrush"] = BrushHex("#C5A059");
                Resources["GoldTintBgBrush"] = BrushHex("#26221B");
                Resources["TextPrimaryBrush"] = BrushHex("#F9FAFB");
                Resources["TextMutedBrush"] = BrushHex("#9CA3AF");
                ThemeToggleBtn.Content = "🌙 Darkmode";
            }
            else
            {
                Resources["BgMainBrush"] = BrushHex("#F4F5F7");
                Resources["BgPanelBrush"] = BrushHex("#FFFFFF");
                Resources["BgCardBrush"] = BrushHex("#F9FAFB");
                Resources["BgInputBrush"] = BrushHex("#FFFFFF");
                Resources["BorderSubtleBrush"] = BrushHex("#E5E7EB");
                Resources["GoldBrush"] = BrushHex("#B0893E");
                Resources["GoldTintBgBrush"] = BrushHex("#FAF5EB");
                Resources["TextPrimaryBrush"] = BrushHex("#111827");
                Resources["TextMutedBrush"] = BrushHex("#6B7280");
                ThemeToggleBtn.Content = "☀ Lightmode";
            }
        }

        private static SolidColorBrush BrushHex(string hex)
        {
            var b = (SolidColorBrush)new BrushConverter().ConvertFromString(hex)!;
            b.Freeze();
            return b;
        }

        private void RefreshAllUi()
        {
            _suppressUiEvents = true;
            try
            {
                ActiveProjectTitleTxt.Text = _currentProject.Name;
                ActiveMusicLabelTxt.Text = string.IsNullOrWhiteSpace(_currentProject.BackgroundMusic)
                    ? "Kein Musikbett gewaehlt"
                    : Path.GetFileName(_currentProject.BackgroundMusic);
                MusicVolSlider.Value = Math.Round(_currentProject.MusicVolume * 100.0);

                UpdateAspectButtonsUi();
                RenderProjectsList();
                RenderSoundPoolList();
                RenderScenesList();

                if (!string.IsNullOrWhiteSpace(_currentProject.LastRenderedPath) && File.Exists(_currentProject.LastRenderedPath))
                {
                    NowPlayingLabelTxt.Text = $"Fertig: {Path.GetFileName(_currentProject.LastRenderedPath)}";
                    PreviewEmptyTxt.Text = "Fertiges Video bereit (▶ Play)";
                }
                else
                {
                    NowPlayingLabelTxt.Text = "";
                    PreviewEmptyTxt.Text = "Vorschau bereit";
                }
            }
            finally
            {
                _suppressUiEvents = false;
            }
        }

        private void UpdateAspectButtonsUi()
        {
            string ar = _currentProject.AspectRatio ?? "9:16";
            SetPillActive(Aspect916Btn, ar == "9:16");
            SetPillActive(Aspect11Btn, ar == "1:1");
            SetPillActive(Aspect169Btn, ar == "16:9");
        }

        private void SetPillActive(System.Windows.Controls.Button btn, bool active)
        {
            btn.Background = (System.Windows.Media.Brush)Resources[active ? "GoldTintBgBrush" : "BgCardBrush"];
            btn.Foreground = (System.Windows.Media.Brush)Resources[active ? "GoldBrush" : "TextMutedBrush"];
            btn.BorderBrush = (System.Windows.Media.Brush)Resources[active ? "GoldBrush" : "BorderSubtleBrush"];
            btn.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
        }

        private void RenderProjectsList()
        {
            ProjectsListPanel.Children.Clear();
            foreach (string name in CraftEngine.ListProjectNames())
            {
                bool active = string.Equals(name, _currentProject.Name, StringComparison.OrdinalIgnoreCase);
                int mediaCount = CraftEngine.ListProjectMediaFiles(name).Count;

                var card = new Border
                {
                    Background = (System.Windows.Media.Brush)Resources[active ? "GoldTintBgBrush" : "BgCardBrush"],
                    BorderBrush = (System.Windows.Media.Brush)Resources[active ? "GoldBrush" : "BorderSubtleBrush"],
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(11, 8, 11, 8),
                    Margin = new Thickness(0, 0, 0, 7),
                    Cursor = System.Windows.Input.Cursors.Hand
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var title = new TextBlock
                {
                    Text = $"📁 {name}",
                    Foreground = (System.Windows.Media.Brush)Resources[active ? "GoldBrush" : "TextPrimaryBrush"],
                    FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold,
                    FontSize = 12.5,
                    VerticalAlignment = VerticalAlignment.Center
                };
                var badge = new TextBlock
                {
                    Text = $"{mediaCount} Dateien",
                    Foreground = (System.Windows.Media.Brush)Resources["TextMutedBrush"],
                    FontSize = 11,
                    VerticalAlignment = VerticalAlignment.Center
                };

                Grid.SetColumn(title, 0);
                Grid.SetColumn(badge, 1);
                grid.Children.Add(title);
                grid.Children.Add(badge);
                card.Child = grid;

                string captured = name;
                card.MouseLeftButtonUp += (_, _) => SelectProject(captured);
                ProjectsListPanel.Children.Add(card);
            }
        }

        private void SelectProject(string projectName)
        {
            FlushAutoSave();
            ReleasePreviewMediaLock();
            _selectedSceneIndex = 0;
            _currentProject = CraftEngine.LoadOrCreateProject(projectName);
            _settings.LastProjectName = _currentProject.Name;
            CraftEngine.SaveSettings(_settings);
            RefreshAllUi();
        }

        private void RenderSoundPoolList()
        {
            SoundPoolPanel.Children.Clear();
            var sounds = CraftEngine.ListSoundPool(_soundFilter);
            foreach (var s in sounds)
            {
                var row = new Border
                {
                    Background = (System.Windows.Media.Brush)Resources["BgCardBrush"],
                    BorderBrush = (System.Windows.Media.Brush)Resources["BorderSubtleBrush"],
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(9, 6, 9, 6),
                    Margin = new Thickness(0, 0, 0, 6)
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var playBtn = CreateMiniButton("▶", false);
                playBtn.Margin = new Thickness(0, 0, 8, 0);
                CraftSoundItem previewSound = s;
                playBtn.Click += (_, _) =>
                {
                    try
                    {
                        string? safeFull = CraftEngine.ResolveSafeAudioPath(previewSound.RelativePath, null, requireExists: true);
                        if (string.IsNullOrWhiteSpace(safeFull)) return;
                        _sfxPreviewPlayer.Stop();
                        _sfxPreviewPlayer.Open(new Uri(safeFull));
                        _sfxPreviewPlayer.Volume = 0.75;
                        _sfxPreviewPlayer.Play();
                        StatusBannerTxt.Text = $"Sound-Vorschau: {previewSound.FileName}";
                    }
                    catch (Exception ex)
                    {
                        StatusBannerTxt.Text = ex.Message;
                    }
                };

                var infoStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                infoStack.Children.Add(new TextBlock
                {
                    Text = Path.GetFileNameWithoutExtension(s.FileName).Replace('_', ' '),
                    Foreground = (System.Windows.Media.Brush)Resources["TextPrimaryBrush"],
                    FontSize = 11.5,
                    FontWeight = FontWeights.SemiBold,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                infoStack.Children.Add(new TextBlock
                {
                    Text = $"{s.DurationSec:0.0}s · {s.License}",
                    Foreground = (System.Windows.Media.Brush)Resources["TextMutedBrush"],
                    FontSize = 10
                });

                bool isBeat = s.Category.Contains("Beats", StringComparison.OrdinalIgnoreCase);
                var assignBtn = CreateMiniButton(isBeat ? "Als Musik" : "Als Effekt", true);
                CraftSoundItem capturedSound = s;
                assignBtn.Click += (_, _) =>
                {
                    try
                    {
                        if (isBeat)
                        {
                            string projDir = CraftEngine.GetProjectDirectory(_currentProject.Name);
                            _ = CraftEngine.ResolveSafeAudioPath(capturedSound.RelativePath, projDir, requireExists: false);
                            _currentProject.BackgroundMusic = capturedSound.RelativePath;
                            CraftEngine.SaveProject(_currentProject);
                            _hasPendingAutoSave = false;
                            ActiveMusicLabelTxt.Text = capturedSound.FileName;
                            StatusBannerTxt.Text = $"Musikbett gesetzt: {capturedSound.FileName}";
                        }
                        else
                        {
                            AssignEffectToSelectedScene(capturedSound);
                        }
                    }
                    catch (Exception ex)
                    {
                        StatusBannerTxt.Text = ex.Message;
                    }
                };

                Grid.SetColumn(playBtn, 0);
                Grid.SetColumn(infoStack, 1);
                Grid.SetColumn(assignBtn, 2);
                grid.Children.Add(playBtn);
                grid.Children.Add(infoStack);
                grid.Children.Add(assignBtn);
                row.Child = grid;

                SoundPoolPanel.Children.Add(row);
            }
        }

        private void RenderScenesList()
        {
            ScenesListPanel.Children.Clear();
            _sceneCardBorders.Clear();

            if (_currentProject.Scenes.Count == 0)
            {
                _selectedSceneIndex = 0;
                ScenesListPanel.Children.Add(new TextBlock
                {
                    Text = "Noch keine Szenen im Projekt. Ziehe Fotos/Videos hierher oder klicke auf ＋ Medien.",
                    Foreground = (System.Windows.Media.Brush)Resources["TextMutedBrush"],
                    FontSize = 12.5,
                    Margin = new Thickness(6, 20, 6, 0),
                    TextWrapping = TextWrapping.Wrap
                });
                return;
            }

            _selectedSceneIndex = Math.Clamp(_selectedSceneIndex, 0, _currentProject.Scenes.Count - 1);

            for (int i = 0; i < _currentProject.Scenes.Count; i++)
            {
                int idx = i;
                var scene = _currentProject.Scenes[i];
                bool isSelected = idx == _selectedSceneIndex;

                var card = new Border
                {
                    Background = (System.Windows.Media.Brush)Resources["BgCardBrush"],
                    BorderBrush = (System.Windows.Media.Brush)Resources[isSelected ? "GoldBrush" : "BorderSubtleBrush"],
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(12, 10, 12, 10),
                    Margin = new Thickness(0, 0, 0, 9)
                };
                card.PreviewMouseLeftButtonDown += (_, _) => SetSelectedSceneIndex(idx);
                _sceneCardBorders.Add(card);

                var outer = new StackPanel();

                // Top Row: #Index + Filename + OriginalAudio CheckBox + Preview / Move / Delete
                var topGrid = new Grid { Margin = new Thickness(0, 0, 0, 8) };
                topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var numBadge = new Border
                {
                    Background = (System.Windows.Media.Brush)Resources["GoldTintBgBrush"],
                    BorderBrush = (System.Windows.Media.Brush)Resources["GoldBrush"],
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(6),
                    Padding = new Thickness(7, 2, 7, 2),
                    Margin = new Thickness(0, 0, 8, 0),
                    Child = new TextBlock
                    {
                        Text = $"#{idx + 1}",
                        Foreground = (System.Windows.Media.Brush)Resources["GoldBrush"],
                        FontSize = 11,
                        FontWeight = FontWeights.Bold
                    }
                };

                bool isOutroScene = string.Equals(scene.MediaType, "brand_outro", StringComparison.OrdinalIgnoreCase)
                    || string.Equals(scene.FileName, "__BRAND_OUTRO__", StringComparison.OrdinalIgnoreCase);
                bool isVideoScene = !isOutroScene && string.Equals(scene.MediaType, "video", StringComparison.OrdinalIgnoreCase);

                var fileLbl = new TextBlock
                {
                    Text = isOutroScene ? "✨ Mystischer Marken-Abgang (Mein Seelenfunke)" : scene.FileName,
                    Foreground = (System.Windows.Media.Brush)Resources[isOutroScene ? "GoldBrush" : "TextPrimaryBrush"],
                    FontWeight = FontWeights.SemiBold,
                    FontSize = 12,
                    VerticalAlignment = VerticalAlignment.Center,
                    TextTrimming = TextTrimming.CharacterEllipsis
                };

                var actions = new StackPanel
                {
                    Orientation = System.Windows.Controls.Orientation.Horizontal,
                    VerticalAlignment = VerticalAlignment.Center
                };

                if (isVideoScene)
                {
                    var origAudioChk = new System.Windows.Controls.CheckBox
                    {
                        Content = "O-Ton",
                        IsChecked = scene.KeepOriginalAudio,
                        Foreground = (System.Windows.Media.Brush)Resources["TextMutedBrush"],
                        FontSize = 11,
                        VerticalAlignment = VerticalAlignment.Center,
                        Margin = new Thickness(0, 0, 8, 0),
                        Cursor = System.Windows.Input.Cursors.Hand
                    };
                    origAudioChk.Checked += (_, _) => { scene.KeepOriginalAudio = true; ScheduleAutoSave(); };
                    origAudioChk.Unchecked += (_, _) => { scene.KeepOriginalAudio = false; ScheduleAutoSave(); };
                    actions.Children.Add(origAudioChk);
                }

                var prevBtn = CreateMiniButton("▶ Vorschau", false);
                prevBtn.Margin = new Thickness(0, 0, 5, 0);
                prevBtn.Click += (_, _) => PreviewSingleScene(scene);

                var upBtn = CreateMiniButton("▲", false);
                upBtn.Margin = new Thickness(0, 0, 4, 0);
                upBtn.Click += (_, _) => MoveScene(idx, -1);

                var downBtn = CreateMiniButton("▼", false);
                downBtn.Margin = new Thickness(0, 0, 4, 0);
                downBtn.Click += (_, _) => MoveScene(idx, 1);

                var delBtn = CreateMiniButton("✕", false);
                delBtn.Click += (_, _) =>
                {
                    _currentProject.Scenes.RemoveAt(idx);
                    if (_selectedSceneIndex >= _currentProject.Scenes.Count)
                        _selectedSceneIndex = Math.Max(0, _currentProject.Scenes.Count - 1);
                    CraftEngine.SaveProject(_currentProject);
                    _hasPendingAutoSave = false;
                    RenderScenesList();
                };

                actions.Children.Add(prevBtn);
                actions.Children.Add(upBtn);
                actions.Children.Add(downBtn);
                actions.Children.Add(delBtn);

                Grid.SetColumn(numBadge, 0);
                Grid.SetColumn(fileLbl, 1);
                Grid.SetColumn(actions, 2);
                topGrid.Children.Add(numBadge);
                topGrid.Children.Add(fileLbl);
                topGrid.Children.Add(actions);
                outer.Children.Add(topGrid);

                // Row 2: Start, Duration, FitMode, SpeedMode, Transition
                var timingGrid = new Grid { Margin = new Thickness(0, 0, 0, 7) };
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(68) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(6) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(68) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(6) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(112) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(6) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(112) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(6) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

                var startBox = CreateLabeledInput("Start (s)", scene.StartSec.ToString("0.0", CultureInfo.InvariantCulture), val =>
                {
                    if (double.TryParse(val.Replace(',', '.'), NumberStyles.Float, CultureInfo.InvariantCulture, out double v))
                    {
                        scene.StartSec = Math.Max(0, v);
                        ScheduleAutoSave();
                    }
                });

                var durBox = CreateLabeledInput("Dauer (s)", scene.DurationSec.ToString("0.0", CultureInfo.InvariantCulture), val =>
                {
                    if (double.TryParse(val.Replace(',', '.'), NumberStyles.Float, CultureInfo.InvariantCulture, out double v))
                    {
                        scene.DurationSec = Math.Clamp(v, 0.8, 45.0);
                        ScheduleAutoSave();
                    }
                });

                var fitCombo = CreateLabeledCombo("Bildmodus", FitModeOptions, scene.FitMode, val =>
                {
                    scene.FitMode = val;
                    ScheduleAutoSave();
                });

                var speedCombo = CreateLabeledCombo("Tempo", SpeedModeOptions, scene.SpeedMode, val =>
                {
                    scene.SpeedMode = val;
                    ScheduleAutoSave();
                });

                var transCombo = CreateLabeledCombo("Uebergang", TransitionOptions, scene.Transition, val =>
                {
                    scene.Transition = val;
                    ScheduleAutoSave();
                });

                Grid.SetColumn(startBox, 0);
                Grid.SetColumn(durBox, 2);
                Grid.SetColumn(fitCombo, 4);
                Grid.SetColumn(speedCombo, 6);
                Grid.SetColumn(transCombo, 8);
                timingGrid.Children.Add(startBox);
                timingGrid.Children.Add(durBox);
                timingGrid.Children.Add(fitCombo);
                timingGrid.Children.Add(speedCombo);
                timingGrid.Children.Add(transCombo);
                outer.Children.Add(timingGrid);

                // Row 3: Overlay Title & Subtitle
                var textGrid = new Grid();
                textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

                var titleBox = CreateLabeledInput("Titel im Video", scene.OverlayTitle, val =>
                {
                    scene.OverlayTitle = CraftEngine.SanitizeCleanText(val);
                    ScheduleAutoSave();
                });
                var subBox = CreateLabeledInput("Unterzeile", scene.OverlaySubtitle, val =>
                {
                    scene.OverlaySubtitle = CraftEngine.SanitizeCleanText(val);
                    ScheduleAutoSave();
                });

                Grid.SetColumn(titleBox, 0);
                Grid.SetColumn(subBox, 2);
                textGrid.Children.Add(titleBox);
                textGrid.Children.Add(subBox);
                outer.Children.Add(textGrid);

                card.Child = outer;
                ScenesListPanel.Children.Add(card);
            }
        }

        private StackPanel CreateLabeledInput(string label, string initialValue, Action<string> onChanged)
        {
            var sp = new StackPanel();
            sp.Children.Add(new TextBlock
            {
                Text = label,
                Foreground = (System.Windows.Media.Brush)Resources["TextMutedBrush"],
                FontSize = 10.5,
                Margin = new Thickness(2, 0, 0, 3)
            });
            var tb = new System.Windows.Controls.TextBox
            {
                Text = initialValue,
                Background = (System.Windows.Media.Brush)Resources["BgInputBrush"],
                Foreground = (System.Windows.Media.Brush)Resources["TextPrimaryBrush"],
                CaretBrush = (System.Windows.Media.Brush)Resources["TextPrimaryBrush"],
                BorderBrush = (System.Windows.Media.Brush)Resources["BorderSubtleBrush"],
                BorderThickness = new Thickness(1),
                Padding = new Thickness(7, 4, 7, 4),
                FontSize = 11.5
            };
            tb.TextChanged += (_, _) => onChanged(tb.Text);
            tb.LostFocus += (_, _) => FlushAutoSave();
            sp.Children.Add(tb);
            return sp;
        }

        private StackPanel CreateLabeledCombo(string label, IReadOnlyList<(string Value, string Label)> options, string? currentValue, Action<string> onChanged)
        {
            var sp = new StackPanel();
            sp.Children.Add(new TextBlock
            {
                Text = label,
                Foreground = (System.Windows.Media.Brush)Resources["TextMutedBrush"],
                FontSize = 10.5,
                Margin = new Thickness(2, 0, 0, 3)
            });

            var cb = new System.Windows.Controls.ComboBox
            {
                Style = (Style)Resources["CraftCleanComboStyle"],
                Height = 27
            };

            string normCur = (currentValue ?? "").Trim();
            int selectedIdx = 0;
            bool matched = false;

            for (int i = 0; i < options.Count; i++)
            {
                var item = new ComboBoxItem
                {
                    Content = options[i].Label,
                    Tag = options[i].Value
                };
                cb.Items.Add(item);
                if (string.Equals(options[i].Value, normCur, StringComparison.OrdinalIgnoreCase))
                {
                    selectedIdx = i;
                    matched = true;
                }
            }

            if (!matched && !string.IsNullOrWhiteSpace(normCur))
            {
                var customItem = new ComboBoxItem
                {
                    Content = normCur,
                    Tag = normCur
                };
                cb.Items.Add(customItem);
                selectedIdx = cb.Items.Count - 1;
            }

            cb.SelectedIndex = selectedIdx;
            cb.SelectionChanged += (_, _) =>
            {
                if (cb.SelectedItem is ComboBoxItem cbi && cbi.Tag is string val)
                {
                    onChanged(val);
                }
            };

            sp.Children.Add(cb);
            return sp;
        }

        private System.Windows.Controls.Button CreateMiniButton(string text, bool goldAccent)
        {
            var btn = new System.Windows.Controls.Button
            {
                Content = text,
                Background = (System.Windows.Media.Brush)Resources[goldAccent ? "GoldTintBgBrush" : "BgInputBrush"],
                Foreground = (System.Windows.Media.Brush)Resources[goldAccent ? "GoldBrush" : "TextPrimaryBrush"],
                BorderBrush = (System.Windows.Media.Brush)Resources[goldAccent ? "GoldBrush" : "BorderSubtleBrush"],
                BorderThickness = new Thickness(1),
                Padding = new Thickness(8, 3, 8, 3),
                Cursor = System.Windows.Input.Cursors.Hand,
                FontSize = 11,
                FontWeight = goldAccent ? FontWeights.Bold : FontWeights.SemiBold
            };
            var style = new Style(typeof(Border));
            style.Setters.Add(new Setter(Border.CornerRadiusProperty, new CornerRadius(6)));
            btn.Resources.Add(typeof(Border), style);
            return btn;
        }

        private void MoveScene(int index, int delta)
        {
            int target = index + delta;
            if (target < 0 || target >= _currentProject.Scenes.Count) return;
            var item = _currentProject.Scenes[index];
            _currentProject.Scenes.RemoveAt(index);
            _currentProject.Scenes.Insert(target, item);
            _selectedSceneIndex = target;
            CraftEngine.SaveProject(_currentProject);
            _hasPendingAutoSave = false;
            RenderScenesList();
        }

        private void PreviewSingleScene(CraftSceneItem scene)
        {
            try
            {
                if (string.Equals(scene.MediaType, "brand_outro", StringComparison.OrdinalIgnoreCase)
                    || string.Equals(scene.FileName, "__BRAND_OUTRO__", StringComparison.OrdinalIgnoreCase))
                {
                    if (!string.IsNullOrWhiteSpace(_currentProject.LastRenderedPath) && File.Exists(_currentProject.LastRenderedPath))
                    {
                        PlayMediaInStage(_currentProject.LastRenderedPath);
                        StatusBannerTxt.Text = "Vorschau: Mystischer Marken-Abgang im fertigen Mastervideo";
                    }
                    else
                    {
                        StatusBannerTxt.Text = "Marken-Abgang wird beim Rendern in 60 FPS generiert";
                    }
                    return;
                }

                string full = CraftEngine.ResolveSceneMediaPath(_currentProject.Name, scene.FileName);
                if (!File.Exists(full))
                {
                    StatusBannerTxt.Text = $"Datei nicht gefunden: {scene.FileName}";
                    return;
                }
                PlayMediaInStage(full);
            }
            catch (Exception ex)
            {
                StatusBannerTxt.Text = ex.Message;
            }
        }

        private void PlayMediaInStage(string filePath)
        {
            ReleasePreviewMediaLock();
            string ext = Path.GetExtension(filePath);
            NowPlayingLabelTxt.Text = Path.GetFileName(filePath);
            PreviewEmptyPanel.Visibility = Visibility.Collapsed;

            if (CraftEngine.ImageExts.Contains(ext))
            {
                var bi = new BitmapImage();
                bi.BeginInit();
                bi.CacheOption = BitmapCacheOption.OnLoad;
                bi.UriSource = new Uri(filePath);
                bi.EndInit();
                bi.Freeze();
                PreviewStillImage.Source = bi;
                PreviewStillImage.Visibility = Visibility.Visible;
                PreviewPlayer.Visibility = Visibility.Collapsed;
            }
            else
            {
                PreviewStillImage.Visibility = Visibility.Collapsed;
                PreviewPlayer.Visibility = Visibility.Visible;
                PreviewPlayer.Source = new Uri(filePath);
                PreviewPlayer.Play();
            }
        }

        private void ReleasePreviewMediaLock()
        {
            try
            {
                PreviewPlayer.Stop();
                PreviewPlayer.Close();
                PreviewPlayer.Source = null;
                PreviewStillImage.Source = null;
                PreviewStillImage.Visibility = Visibility.Collapsed;
                PreviewEmptyPanel.Visibility = Visibility.Visible;
            }
            catch { }
        }

        // =====================================================================
        // BUTTON HANDLERS
        // =====================================================================
        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            ApplyTheme(!_settings.DarkMode);
            RefreshAllUi();
        }

        private void LaunchArtCraft_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                CraftEngine.LaunchStorytoldEngine("artcraft");
                StatusBannerTxt.Text = "ArtCraft Engine gestartet";
            }
            catch (Exception ex) { StatusBannerTxt.Text = ex.Message; }
        }

        private async void LaunchFilmCraft_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                FlushAutoSave();
                string fcproj = await CraftEngine.ExportFilmCraftNativeProjectAsync(_currentProject);
                string? target = File.Exists(fcproj) && fcproj.EndsWith(".fcproj", StringComparison.OrdinalIgnoreCase) ? fcproj : null;
                CraftEngine.LaunchStorytoldEngine("filmcraft", target, enableControlBridge: true);
                StatusBannerTxt.Text = "FilmCraft mit Live-Control-Bridge gestartet";
            }
            catch (Exception ex) { StatusBannerTxt.Text = ex.Message; }
        }

        private void LaunchSoundCraft_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                CraftEngine.LaunchStorytoldEngine("soundcraft", null, enableControlBridge: true);
                StatusBannerTxt.Text = "SoundCraft gestartet";
            }
            catch (Exception ex) { StatusBannerTxt.Text = ex.Message; }
        }

        private void OpenMedienDrive_Click(object sender, RoutedEventArgs e)
        {
            string target = Directory.Exists(@"Y:\Medien") ? @"Y:\Medien" : CraftEngine.VideosRoot;
            OpenFolderInExplorerSafe(target);
        }

        private void Aspect916_Click(object sender, RoutedEventArgs e) => SetAspect("9:16");
        private void Aspect11_Click(object sender, RoutedEventArgs e) => SetAspect("1:1");
        private void Aspect169_Click(object sender, RoutedEventArgs e) => SetAspect("16:9");

        private void SetAspect(string ar)
        {
            _currentProject.AspectRatio = ar;
            CraftEngine.SaveProject(_currentProject);
            _hasPendingAutoSave = false;
            UpdateAspectButtonsUi();
        }

        private void NewProject_Click(object sender, RoutedEventArgs e)
        {
            string newName = CraftEngine.ComputeNextProjectName(CraftEngine.VideosRoot);
            SelectProject(newName);
            StatusBannerTxt.Text = $"Neues Projekt angelegt: {newName}";
        }

        private void ImportMedia_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog
            {
                Title = "Medien fuer Projekt auswaehlen",
                Multiselect = true,
                InitialDirectory = Directory.Exists(@"Y:\Medien") ? @"Y:\Medien" : CraftEngine.VideosRoot,
                Filter = "Videos & Bilder|*.mp4;*.mov;*.mkv;*.webm;*.avi;*.m4v;*.jpg;*.jpeg;*.png;*.webp;*.bmp"
            };
            if (dlg.ShowDialog() == true)
            {
                ImportFilesIntoCurrentProject(dlg.FileNames);
            }
        }

        private void ImportFilesIntoCurrentProject(IEnumerable<string> files)
        {
            int added = 0;
            string? lastErr = null;

            foreach (string f in files)
            {
                try
                {
                    string dest = CraftEngine.ImportMediaToProject(_currentProject.Name, f);
                    string ext = Path.GetExtension(dest);
                    bool isImg = CraftEngine.ImageExts.Contains(ext);
                    _currentProject.Scenes.Add(new CraftSceneItem
                    {
                        FileName = Path.GetFileName(dest),
                        MediaType = isImg ? "image" : "video",
                        StartSec = 0,
                        DurationSec = isImg ? 2.8 : 2.2,
                        FitMode = "fill",
                        SpeedMode = isImg ? "linear" : "speed_ramp",
                        SlowMoFactor = 0.42,
                        Transition = "whip_zoom"
                    });
                    added++;
                }
                catch (Exception ex)
                {
                    lastErr = ex.Message;
                }
            }

            if (added > 0)
            {
                _selectedSceneIndex = _currentProject.Scenes.Count - 1;
                CraftEngine.SaveProject(_currentProject);
                _hasPendingAutoSave = false;
                RefreshAllUi();
                StatusBannerTxt.Text = $"{added} Datei(en) in {_currentProject.Name} importiert";
            }
            else if (!string.IsNullOrWhiteSpace(lastErr))
            {
                StatusBannerTxt.Text = lastErr;
            }
        }

        private void RefreshWorkspace_Click(object sender, RoutedEventArgs e)
        {
            FlushAutoSave();
            _currentProject = CraftEngine.LoadOrCreateProject(_currentProject.Name);
            RefreshAllUi();
        }

        private void StopAudioPreview_Click(object sender, RoutedEventArgs e) => _sfxPreviewPlayer.Stop();
        private void FilterSoundsAll_Click(object sender, RoutedEventArgs e) { _soundFilter = "all"; RenderSoundPoolList(); }
        private void FilterSoundsWhoosh_Click(object sender, RoutedEventArgs e) { _soundFilter = "Uebergaenge"; RenderSoundPoolList(); }
        private void FilterSoundsSfx_Click(object sender, RoutedEventArgs e) { _soundFilter = "Effekte"; RenderSoundPoolList(); }
        private void FilterSoundsBeats_Click(object sender, RoutedEventArgs e) { _soundFilter = "Beats"; RenderSoundPoolList(); }

        private void MusicVolSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressUiEvents || _currentProject == null) return;
            _currentProject.MusicVolume = Math.Round(e.NewValue / 100.0, 2);
            ScheduleAutoSave();
        }

        private void AddSceneFromProjectMedia_Click(object sender, RoutedEventArgs e) => ImportMedia_Click(sender, e);

        private void SaveProjectBtn_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                _autoSaveTimer.Stop();
                CraftEngine.SaveProject(_currentProject);
                _hasPendingAutoSave = false;
                StatusBannerTxt.Text = $"Projekt '{_currentProject.Name}' gespeichert";
            }
            catch (Exception ex)
            {
                StatusBannerTxt.Text = ex.Message;
            }
        }

        private void PlayRenderedOrFirst_Click(object sender, RoutedEventArgs e)
        {
            if (!string.IsNullOrWhiteSpace(_currentProject.LastRenderedPath) && File.Exists(_currentProject.LastRenderedPath))
            {
                PlayMediaInStage(_currentProject.LastRenderedPath);
                return;
            }
            if (_currentProject.Scenes.Count > 0)
            {
                int idx = Math.Clamp(_selectedSceneIndex, 0, _currentProject.Scenes.Count - 1);
                PreviewSingleScene(_currentProject.Scenes[idx]);
            }
        }

        private void StopPreview_Click(object sender, RoutedEventArgs e) => ReleasePreviewMediaLock();

        private void PreviewPlayer_MediaEnded(object sender, RoutedEventArgs e)
        {
            PreviewPlayer.Position = TimeSpan.Zero;
            PreviewPlayer.Play();
        }

        private void PreviewPlayer_MediaFailed(object sender, ExceptionRoutedEventArgs e)
        {
            ReleasePreviewMediaLock();
            StatusBannerTxt.Text = $"Vorschau konnte nicht geladen werden: {e.ErrorException?.Message ?? "Unbekannter Medienfehler"}";
        }

        private void OpenProjectFolder_Click(object sender, RoutedEventArgs e)
        {
            string dir = CraftEngine.GetProjectDirectory(_currentProject.Name);
            OpenFolderInExplorerSafe(dir);
        }

        private async void ExportFilmCraftBtn_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                FlushAutoSave();
                CraftEngine.SaveProject(_currentProject);
                string path = await CraftEngine.ExportFilmCraftNativeProjectAsync(_currentProject);
                StatusBannerTxt.Text = $"FilmCraft-Projekt aktualisiert: {Path.GetFileName(path)}";
            }
            catch (Exception ex) { StatusBannerTxt.Text = ex.Message; }
        }

        private async void RenderMasterBtn_Click(object sender, RoutedEventArgs e)
        {
            if (_isRendering) return;
            _isRendering = true;
            RenderMasterBtn.IsEnabled = false;
            RenderProgressBar.Visibility = Visibility.Visible;
            RenderProgressBar.Value = 2;
            FlushAutoSave();
            ReleasePreviewMediaLock();

            try
            {
                CraftEngine.SaveProject(_currentProject);
                var prog = new Progress<(double Pct, string Step)>(p =>
                {
                    RenderProgressBar.Value = p.Pct;
                    StatusBannerTxt.Text = p.Step;
                });

                string outMp4 = await Task.Run(() => CraftEngine.RenderProjectSocialVideoAsync(_currentProject, prog));
                StatusBannerTxt.Text = $"Postbereit gespeichert: {Path.GetFileName(outMp4)}";
                PlayMediaInStage(outMp4);
            }
            catch (Exception ex)
            {
                StatusBannerTxt.Text = $"Renderfehler: {ex.Message}";
            }
            finally
            {
                _isRendering = false;
                RenderMasterBtn.IsEnabled = true;
                RenderProgressBar.Visibility = Visibility.Collapsed;
            }
        }

        private void Window_DragOver(object sender, System.Windows.DragEventArgs e)
        {
            e.Effects = e.Data.GetDataPresent(System.Windows.DataFormats.FileDrop)
                ? System.Windows.DragDropEffects.Copy
                : System.Windows.DragDropEffects.None;
            e.Handled = true;
        }

        private void Window_Drop(object sender, System.Windows.DragEventArgs e)
        {
            if (e.Data.GetData(System.Windows.DataFormats.FileDrop) is string[] files && files.Length > 0)
            {
                ImportFilesIntoCurrentProject(files);
            }
        }

        // =====================================================================
        // NAMED PIPE IPC FOR LIVE MCP CONTROL (BOUNDED <= 64 KB)
        // =====================================================================
        private void StartNamedPipeServer()
        {
            _ipcCts = new CancellationTokenSource();
            var token = _ipcCts.Token;
            _ = Task.Run(async () =>
            {
                while (!token.IsCancellationRequested)
                {
                    try
                    {
                        using var server = NamedPipeServerStreamAcl.Create(
                            CraftEngine.PipeName,
                            PipeDirection.InOut,
                            NamedPipeServerStream.MaxAllowedServerInstances,
                            PipeTransmissionMode.Byte,
                            PipeOptions.Asynchronous,
                            4096,
                            4096,
                            CraftEngine.CreateCurrentUserPipeSecurity());

                        await server.WaitForConnectionAsync(token);
                        await using var writer = new StreamWriter(server, new UTF8Encoding(false), bufferSize: 4096, leaveOpen: true)
                        {
                            AutoFlush = true
                        };

                        string? line;
                        try
                        {
                            line = await CraftEngine.ReadBoundedLineAsync(server, CraftEngine.MaxIpcLineBytes, token);
                        }
                        catch (InvalidDataException ex)
                        {
                            await writer.WriteLineAsync(System.Text.Json.JsonSerializer.Serialize(new { ok = false, error = ex.Message }));
                            continue;
                        }

                        if (!string.IsNullOrWhiteSpace(line))
                        {
                            string resp = await CraftEngine.ExecuteCliCommandJsonAsync(line);
                            await writer.WriteLineAsync(resp);

                            _ = Dispatcher.BeginInvoke(() =>
                            {
                                _currentProject = CraftEngine.LoadOrCreateProject(_currentProject.Name);
                                RefreshAllUi();
                            });
                        }
                    }
                    catch (OperationCanceledException) { break; }
                    catch { await Task.Delay(300, token); }
                }
            }, token);
        }
    }
}

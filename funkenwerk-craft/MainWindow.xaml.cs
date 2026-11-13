using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Text;
using System.Text.Json.Nodes;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Media.Imaging;

namespace FunkenwerkCraft
{
    public partial class MainWindow : Window
    {
        private CraftSettingsModel _settings;
        private CraftProjectModel _currentProject;
        private string _soundFilter = "all";
        private bool _isRendering;
        private bool _suppressUiEvents;
        private readonly MediaPlayer _sfxPreviewPlayer = new();
        private CancellationTokenSource? _ipcCts;

        public MainWindow(string? initialProject = null)
        {
            InitializeComponent();
            _settings = CraftEngine.LoadSettings();
            ApplyTheme(_settings.DarkMode);

            string targetProj = !string.IsNullOrWhiteSpace(initialProject)
                ? initialProject!
                : _settings.LastProjectName;

            _currentProject = CraftEngine.LoadOrCreateProject(targetProj);
            RefreshAllUi();

            Loaded += (_, _) => StartNamedPipeServer();
            Closed += (_, _) =>
            {
                _ipcCts?.Cancel();
                ReleasePreviewMediaLock();
                _sfxPreviewPlayer.Close();
            };
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
                    ? "Kein Musikbett gewählt"
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
            ReleasePreviewMediaLock();
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
                string fullAudio = s.FullPath;
                playBtn.Click += (_, _) =>
                {
                    try
                    {
                        _sfxPreviewPlayer.Stop();
                        _sfxPreviewPlayer.Open(new Uri(fullAudio));
                        _sfxPreviewPlayer.Volume = 0.75;
                        _sfxPreviewPlayer.Play();
                        StatusBannerTxt.Text = $"Sound-Vorschau: {s.FileName}";
                    }
                    catch { }
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
                    if (isBeat)
                    {
                        _currentProject.BackgroundMusic = capturedSound.RelativePath;
                        CraftEngine.SaveProject(_currentProject);
                        ActiveMusicLabelTxt.Text = capturedSound.FileName;
                        StatusBannerTxt.Text = $"Musikbett gesetzt: {capturedSound.FileName}";
                    }
                    else
                    {
                        foreach (var sc in _currentProject.Scenes)
                            sc.TransitionSfx = capturedSound.RelativePath;
                        CraftEngine.SaveProject(_currentProject);
                        StatusBannerTxt.Text = $"Übergangs-Sound gesetzt: {capturedSound.FileName}";
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
            if (_currentProject.Scenes.Count == 0)
            {
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

            for (int i = 0; i < _currentProject.Scenes.Count; i++)
            {
                int idx = i;
                var scene = _currentProject.Scenes[i];

                var card = new Border
                {
                    Background = (System.Windows.Media.Brush)Resources["BgCardBrush"],
                    BorderBrush = (System.Windows.Media.Brush)Resources["BorderSubtleBrush"],
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(12, 10, 12, 10),
                    Margin = new Thickness(0, 0, 0, 9)
                };

                var outer = new StackPanel();

                // Top Row: #Index + Filename + Preview / Move / Delete
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

                var fileLbl = new TextBlock
                {
                    Text = scene.FileName,
                    Foreground = (System.Windows.Media.Brush)Resources["TextPrimaryBrush"],
                    FontWeight = FontWeights.SemiBold,
                    FontSize = 12,
                    VerticalAlignment = VerticalAlignment.Center,
                    TextTrimming = TextTrimming.CharacterEllipsis
                };

                var actions = new StackPanel { Orientation = System.Windows.Controls.Orientation.Horizontal };
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
                    CraftEngine.SaveProject(_currentProject);
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

                // Row 2: Start, Duration, Transition, FitMode
                var timingGrid = new Grid { Margin = new Thickness(0, 0, 0, 7) };
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(95) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(95) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                timingGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(120) });

                var startBox = CreateLabeledInput("Start (s)", scene.StartSec.ToString("0.0", CultureInfo.InvariantCulture), val =>
                {
                    if (double.TryParse(val.Replace(',', '.'), NumberStyles.Float, CultureInfo.InvariantCulture, out double v))
                        scene.StartSec = Math.Max(0, v);
                });
                var durBox = CreateLabeledInput("Dauer (s)", scene.DurationSec.ToString("0.0", CultureInfo.InvariantCulture), val =>
                {
                    if (double.TryParse(val.Replace(',', '.'), NumberStyles.Float, CultureInfo.InvariantCulture, out double v))
                        scene.DurationSec = Math.Clamp(v, 0.8, 45.0);
                });
                var transBox = CreateLabeledInput("Übergang", scene.Transition, val => scene.Transition = val.Trim());
                var fitBox = CreateLabeledInput("Bildmodus", scene.FitMode, val => scene.FitMode = val.Trim());

                Grid.SetColumn(startBox, 0);
                Grid.SetColumn(durBox, 2);
                Grid.SetColumn(transBox, 4);
                Grid.SetColumn(fitBox, 6);
                timingGrid.Children.Add(startBox);
                timingGrid.Children.Add(durBox);
                timingGrid.Children.Add(transBox);
                timingGrid.Children.Add(fitBox);
                outer.Children.Add(timingGrid);

                // Row 3: Overlay Title & Subtitle
                var textGrid = new Grid();
                textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

                var titleBox = CreateLabeledInput("Titel im Video", scene.OverlayTitle, val => scene.OverlayTitle = CraftEngine.SanitizeCleanText(val));
                var subBox = CreateLabeledInput("Unterzeile", scene.OverlaySubtitle, val => scene.OverlaySubtitle = CraftEngine.SanitizeCleanText(val));

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
                BorderBrush = (System.Windows.Media.Brush)Resources["BorderSubtleBrush"],
                BorderThickness = new Thickness(1),
                Padding = new Thickness(7, 4, 7, 4),
                FontSize = 11.5
            };
            tb.TextChanged += (_, _) => onChanged(tb.Text);
            sp.Children.Add(tb);
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
            CraftEngine.SaveProject(_currentProject);
            RenderScenesList();
        }

        private void PreviewSingleScene(CraftSceneItem scene)
        {
            string full = CraftEngine.ResolveSceneMediaPath(_currentProject.Name, scene.FileName);
            if (!File.Exists(full))
            {
                StatusBannerTxt.Text = $"Datei nicht gefunden: {scene.FileName}";
                return;
            }
            PlayMediaInStage(full);
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
                string fcproj = await CraftEngine.ExportFilmCraftNativeProjectAsync(_currentProject);
                CraftEngine.LaunchStorytoldEngine("filmcraft", File.Exists(fcproj) && fcproj.EndsWith(".fcproj") ? fcproj : null, enableControlBridge: true);
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
            Process.Start(new ProcessStartInfo { FileName = "explorer.exe", Arguments = $"\"{target}\"", UseShellExecute = true });
        }

        private void Aspect916_Click(object sender, RoutedEventArgs e) => SetAspect("9:16");
        private void Aspect11_Click(object sender, RoutedEventArgs e) => SetAspect("1:1");
        private void Aspect169_Click(object sender, RoutedEventArgs e) => SetAspect("16:9");

        private void SetAspect(string ar)
        {
            _currentProject.AspectRatio = ar;
            CraftEngine.SaveProject(_currentProject);
            UpdateAspectButtonsUi();
        }

        private void NewProject_Click(object sender, RoutedEventArgs e)
        {
            int nextNum = CraftEngine.ListProjectNames().Count + 1;
            string newName = $"Projekt {nextNum}";
            while (Directory.Exists(Path.Combine(CraftEngine.VideosRoot, newName))) nextNum++;
            newName = $"Projekt {nextNum}";
            SelectProject(newName);
            StatusBannerTxt.Text = $"Neues Projekt angelegt: {newName}";
        }

        private void ImportMedia_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog
            {
                Title = "Medien für Projekt auswählen",
                Multiselect = true,
                InitialDirectory = Directory.Exists(@"Y:\Medien") ? @"Y:\Medien" : CraftEngine.VideosRoot,
                Filter = "Videos & Bilder|*.mp4;*.mov;*.mkv;*.jpg;*.jpeg;*.png;*.webp"
            };
            if (dlg.ShowDialog() == true)
            {
                ImportFilesIntoCurrentProject(dlg.FileNames);
            }
        }

        private void ImportFilesIntoCurrentProject(IEnumerable<string> files)
        {
            string projDir = CraftEngine.GetProjectDirectory(_currentProject.Name);
            int added = 0;
            foreach (string f in files)
            {
                if (!File.Exists(f)) continue;
                string ext = Path.GetExtension(f);
                if (!CraftEngine.VideoExts.Contains(ext) && !CraftEngine.ImageExts.Contains(ext)) continue;

                string dest = Path.Combine(projDir, Path.GetFileName(f));
                if (!string.Equals(Path.GetFullPath(f), Path.GetFullPath(dest), StringComparison.OrdinalIgnoreCase))
                    File.Copy(f, dest, overwrite: true);

                bool isImg = CraftEngine.ImageExts.Contains(ext);
                _currentProject.Scenes.Add(new CraftSceneItem
                {
                    FileName = Path.GetFileName(dest),
                    MediaType = isImg ? "image" : "video",
                    StartSec = 0,
                    DurationSec = isImg ? 2.8 : 3.4,
                    FitMode = "fill",
                    Transition = "smoothleft"
                });
                added++;
            }
            if (added > 0)
            {
                CraftEngine.SaveProject(_currentProject);
                RefreshAllUi();
                StatusBannerTxt.Text = $"{added} Datei(en) in {_currentProject.Name} importiert";
            }
        }

        private void RefreshWorkspace_Click(object sender, RoutedEventArgs e)
        {
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
        }

        private void AddSceneFromProjectMedia_Click(object sender, RoutedEventArgs e) => ImportMedia_Click(sender, e);

        private void SaveProjectBtn_Click(object sender, RoutedEventArgs e)
        {
            CraftEngine.SaveProject(_currentProject);
            StatusBannerTxt.Text = $"Projekt '{_currentProject.Name}' gespeichert";
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
                PreviewSingleScene(_currentProject.Scenes[0]);
            }
        }

        private void StopPreview_Click(object sender, RoutedEventArgs e) => ReleasePreviewMediaLock();

        private void PreviewPlayer_MediaEnded(object sender, RoutedEventArgs e)
        {
            PreviewPlayer.Position = TimeSpan.Zero;
            PreviewPlayer.Play();
        }

        private void OpenProjectFolder_Click(object sender, RoutedEventArgs e)
        {
            string dir = CraftEngine.GetProjectDirectory(_currentProject.Name);
            Process.Start(new ProcessStartInfo { FileName = "explorer.exe", Arguments = $"\"{dir}\"", UseShellExecute = true });
        }

        private async void ExportFilmCraftBtn_Click(object sender, RoutedEventArgs e)
        {
            try
            {
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
        // NAMED PIPE IPC FOR LIVE MCP CONTROL
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
                        using var reader = new StreamReader(server, Encoding.UTF8);
                        await using var writer = new StreamWriter(server, new UTF8Encoding(false)) { AutoFlush = true };

                        string? line = await reader.ReadLineAsync(token);
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

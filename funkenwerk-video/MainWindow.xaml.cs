using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Shapes;
using System.Windows.Threading;
using Microsoft.Win32;

namespace FunkenwerkVideo
{
    public partial class MainWindow : Window
    {
        private readonly string _baseDir;
        private readonly string _rawFolderDir;
        private readonly string _outputFolderDir;
        private readonly string _thumbCacheDir;
        private readonly string _musicAssetsDir;

        private FileSystemWatcher? _rawWatcher;
        private readonly DispatcherTimer _watcherDebounceTimer = new DispatcherTimer();
        private readonly DispatcherTimer _playerTickTimer = new DispatcherTimer();

        private readonly List<RawVideoClipInfo> _rawClips = new();
        private List<CutDecisionItem> _cutDecisions = new();
        private List<SeelenfunkeMusicTrackItem> _musicCatalog = new();

        private string _activeDirectorMode = "packaging_pov";
        private bool _isDarkMode = true;
        private bool _isUiReady = false;
        private bool _suppressComboEvents = false;
        private bool _isAnalyzingFolder = false;
        private bool _isRenderingVideo = false;

        // Player & Sequence Preview State
        private bool _isPlayerPlaying = false;
        private bool _isSequencePreviewActive = false;
        private int _sequencePreviewIndex = 0;
        private double? _activePreviewSegmentStopSec = null;
        private readonly MediaPlayer _musicPreviewPlayer = new MediaPlayer();
        private bool _isMusicPreviewPlaying = false;

        private static readonly string[] SupportedVideoExtensions = { ".mp4", ".mov", ".mkv", ".avi", ".webm", ".m4v" };

        public MainWindow()
        {
            InitializeComponent();

            _baseDir = AppDomain.CurrentDomain.BaseDirectory;
            _rawFolderDir = System.IO.Path.Combine(_baseDir, "Rohmaterial");
            _outputFolderDir = System.IO.Path.Combine(_baseDir, "Fertige Videos");
            _thumbCacheDir = System.IO.Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkVideo", "ThumbCache");
            _musicAssetsDir = System.IO.Path.Combine(_baseDir, "Assets", "MarketingMusic");

            Directory.CreateDirectory(_rawFolderDir);
            Directory.CreateDirectory(_outputFolderDir);
            Directory.CreateDirectory(_thumbCacheDir);

            _watcherDebounceTimer.Interval = TimeSpan.FromMilliseconds(550);
            _watcherDebounceTimer.Tick += async (s, e) =>
            {
                _watcherDebounceTimer.Stop();
                await ScanAndAnalyzeRawFolderAsync();
            };

            _playerTickTimer.Interval = TimeSpan.FromMilliseconds(50);
            _playerTickTimer.Tick += PlayerTickTimer_Tick;

            _musicPreviewPlayer.MediaEnded += (s, e) =>
            {
                _isMusicPreviewPlaying = false;
                if (PreviewMusicBtnTxt != null) PreviewMusicBtnTxt.Text = "▶ Anhören";
            };

            Loaded += async (s, e) =>
            {
                _isUiReady = true;
                InitMusicCatalogCombo();
                UpdateDirectorModeButtonsUI();
                SetupRawFolderFileWatcher();
                RefreshFinishedVideosList();
                await ScanAndAnalyzeRawFolderAsync();
            };

            Closing += (s, e) =>
            {
                try { _rawWatcher?.Dispose(); } catch { }
                try { _musicPreviewPlayer.Close(); } catch { }
                try { PreviewPlayer.Close(); } catch { }
            };
        }

        // =========================================================================
        // WINDOW CHROME & THEME (Darkmode Tiefschwarz #000000 <-> Lightmode)
        // =========================================================================
        private void TitleBar_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (e.ClickCount == 2)
            {
                MaximizeRestoreWindow_Click(sender, e);
                return;
            }
            if (e.LeftButton == MouseButtonState.Pressed)
            {
                try { DragMove(); } catch { }
            }
        }

        private void MinimizeWindow_Click(object sender, RoutedEventArgs e) => WindowState = WindowState.Minimized;

        private void MaximizeRestoreWindow_Click(object sender, RoutedEventArgs e)
        {
            WindowState = WindowState == WindowState.Maximized ? WindowState.Normal : WindowState.Maximized;
        }

        private void CloseWindow_Click(object sender, RoutedEventArgs e) => Close();

        private void ToggleTheme_Click(object sender, RoutedEventArgs e)
        {
            _isDarkMode = !_isDarkMode;
            ApplyThemeColors();
        }

        private void ApplyThemeColors()
        {
            string bgMain = _isDarkMode ? "#000000" : "#F9FAFB";
            string bgPanel = _isDarkMode ? "#050505" : "#FFFFFF";
            string borderHex = _isDarkMode ? "#1F1F1F" : "#E5E7EB";
            string textPrimary = _isDarkMode ? "#FFFFFF" : "#111827";

            RootBorder.Background = BrushFromHex(bgMain);
            HeaderBar.Background = BrushFromHex(bgPanel);
            HeaderBar.BorderBrush = BrushFromHex(borderHex);
            LeftSidebarBorder.Background = BrushFromHex(bgPanel);
            CenterWorkspaceBorder.Background = BrushFromHex(bgMain);
            RightInspectorBorder.Background = BrushFromHex(bgPanel);
            BrandTitleTxt.Foreground = BrushFromHex(textPrimary);
            RawClipsCountTitle.Foreground = BrushFromHex(textPrimary);

            if (ThemeToggleBtn.Template?.FindName("ThemeIconTxt", ThemeToggleBtn) is TextBlock iconTxt)
            {
                iconTxt.Text = _isDarkMode ? "☀" : "🌙";
            }

            UpdateDirectorModeButtonsUI();
            RenderRawClipsList();
            RenderCutDecisionsTimeline();
            RefreshFinishedVideosList();
        }

        private static SolidColorBrush BrushFromHex(string hex)
        {
            return new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
        }

        // =========================================================================
        // AUTOMATED SELF-TEST & BENCHMARK RUNNER (UI BUTTON)
        // =========================================================================
        private async void RunSelfTest_Click(object sender, RoutedEventArgs e)
        {
            if (_isRenderingVideo || _isAnalyzingFolder) return;
            RunSelfTestHeaderBtn.IsEnabled = false;
            string prevStatus = WatcherStatusTxt.Text;

            try
            {
                var report = await VideoEngineSelfTestSuite.RunFullVerificationSuiteAsync(
                    _musicAssetsDir,
                    stepMsg => Dispatcher.InvokeAsync(() => WatcherStatusTxt.Text = $"🧪 Test: {stepMsg}"));

                WatcherStatusTxt.Text = $"✓ System-Test: {report.SummaryText}";

                var sb = new StringBuilder();
                sb.AppendLine($"Ergebnis: {report.SummaryText}");
                sb.AppendLine();
                foreach (var line in report.LogLines)
                {
                    sb.AppendLine(line);
                }

                MessageBox.Show(
                    sb.ToString(),
                    report.AllPassed ? "✓ Funkenwerk Video · 100 % System- & Sicherheits-Test bestanden" : "System-Test Bericht",
                    MessageBoxButton.OK,
                    report.AllPassed ? MessageBoxImage.Information : MessageBoxImage.Warning);
            }
            catch (Exception ex)
            {
                WatcherStatusTxt.Text = prevStatus;
                MessageBox.Show("Fehler beim Selbsttest: " + ex.Message, "System-Test", MessageBoxButton.OK, MessageBoxImage.Warning);
            }
            finally
            {
                RunSelfTestHeaderBtn.IsEnabled = true;
            }
        }

        // =========================================================================
        // WATCHED FOLDER ("Rohmaterial") & DRAG-AND-DROP
        // =========================================================================
        private void SetupRawFolderFileWatcher()
        {
            try
            {
                _rawWatcher = new FileSystemWatcher(_rawFolderDir)
                {
                    NotifyFilter = NotifyFilters.FileName | NotifyFilters.Size | NotifyFilters.LastWrite,
                    IncludeSubdirectories = false,
                    EnableRaisingEvents = true
                };

                void OnFsEvent(object s, FileSystemEventArgs e)
                {
                    string ext = System.IO.Path.GetExtension(e.FullPath).ToLowerInvariant();
                    if (!SupportedVideoExtensions.Contains(ext)) return;
                    Dispatcher.InvokeAsync(() =>
                    {
                        _watcherDebounceTimer.Stop();
                        _watcherDebounceTimer.Start();
                    });
                }

                _rawWatcher.Created += OnFsEvent;
                _rawWatcher.Deleted += OnFsEvent;
                _rawWatcher.Renamed += (s, e) => OnFsEvent(s, e);
            }
            catch { }
        }

        private void Window_DragOver(object sender, DragEventArgs e)
        {
            e.Effects = e.Data.GetDataPresent(DataFormats.FileDrop) ? DragDropEffects.Copy : DragDropEffects.None;
            e.Handled = true;
        }

        private async void Window_Drop(object sender, DragEventArgs e)
        {
            if (!e.Data.GetDataPresent(DataFormats.FileDrop)) return;
            var droppedPaths = e.Data.GetData(DataFormats.FileDrop) as string[];
            if (droppedPaths == null || droppedPaths.Length == 0) return;

            await CopyFilesOrFoldersToRawDirectoryAsync(droppedPaths);
        }

        private async void AddRawVideosDialog_Click(object sender, MouseButtonEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Eigene Kamera- / Handy-Aufnahmen auswählen",
                Filter = "Videodateien (*.mp4;*.mov;*.mkv;*.avi;*.webm)|*.mp4;*.mov;*.mkv;*.avi;*.webm|Alle Dateien (*.*)|*.*",
                Multiselect = true
            };

            if (dlg.ShowDialog() == true && dlg.FileNames.Length > 0)
            {
                await CopyFilesOrFoldersToRawDirectoryAsync(dlg.FileNames);
            }
        }

        private async void ImportSampleFromVideos_Click(object sender, RoutedEventArgs e)
        {
            string userVideosDir = Environment.GetFolderPath(Environment.SpecialFolder.MyVideos);
            var dlg = new OpenFileDialog
            {
                Title = "Aufnahme aus deinem Videos-Ordner in 'Rohmaterial' übernehmen",
                InitialDirectory = Directory.Exists(userVideosDir) ? userVideosDir : _baseDir,
                Filter = "Videodateien (*.mp4;*.mov;*.mkv;*.avi;*.webm)|*.mp4;*.mov;*.mkv;*.avi;*.webm",
                Multiselect = true
            };

            if (dlg.ShowDialog() == true && dlg.FileNames.Length > 0)
            {
                await CopyFilesOrFoldersToRawDirectoryAsync(dlg.FileNames);
            }
        }

        private async Task CopyFilesOrFoldersToRawDirectoryAsync(IEnumerable<string> paths)
        {
            var videoFilesToCopy = new List<string>();
            foreach (var p in paths)
            {
                if (Directory.Exists(p))
                {
                    var files = Directory.GetFiles(p, "*.*", SearchOption.AllDirectories)
                        .Where(f => SupportedVideoExtensions.Contains(System.IO.Path.GetExtension(f).ToLowerInvariant()));
                    videoFilesToCopy.AddRange(files);
                }
                else if (File.Exists(p))
                {
                    string ext = System.IO.Path.GetExtension(p).ToLowerInvariant();
                    if (SupportedVideoExtensions.Contains(ext))
                    {
                        videoFilesToCopy.Add(p);
                    }
                }
            }

            if (videoFilesToCopy.Count == 0) return;

            WatcherStatusTxt.Text = $"Kopiere {videoFilesToCopy.Count} Aufnahme(n) in 'Rohmaterial'...";
            await Task.Run(() =>
            {
                foreach (var src in videoFilesToCopy)
                {
                    try
                    {
                        string dest = System.IO.Path.Combine(_rawFolderDir, System.IO.Path.GetFileName(src));
                        if (!string.Equals(System.IO.Path.GetFullPath(src), System.IO.Path.GetFullPath(dest), StringComparison.OrdinalIgnoreCase))
                        {
                            File.Copy(src, dest, overwrite: true);
                        }
                    }
                    catch { }
                }
            });

            await ScanAndAnalyzeRawFolderAsync();
        }

        private void OpenRawFolder_Click(object sender, RoutedEventArgs e)
        {
            Directory.CreateDirectory(_rawFolderDir);
            Process.Start(new ProcessStartInfo("explorer.exe", $"\"{_rawFolderDir}\"") { UseShellExecute = true });
        }

        private void OpenOutputFolder_Click(object sender, RoutedEventArgs e)
        {
            Directory.CreateDirectory(_outputFolderDir);
            Process.Start(new ProcessStartInfo("explorer.exe", $"\"{_outputFolderDir}\"") { UseShellExecute = true });
        }

        private async void RefreshRawFolder_Click(object sender, RoutedEventArgs e)
        {
            await ScanAndAnalyzeRawFolderAsync(forceReanalyze: true);
        }

        // =========================================================================
        // PARALLEL MULTI-CORE AI SCAN & ANALYSIS OF REAL RAW CAMERA CLIPS
        // =========================================================================
        private async Task ScanAndAnalyzeRawFolderAsync(bool forceReanalyze = false)
        {
            if (_isAnalyzingFolder) return;
            _isAnalyzingFolder = true;

            try
            {
                Directory.CreateDirectory(_rawFolderDir);
                var filesOnDisk = Directory.GetFiles(_rawFolderDir)
                    .Where(f => SupportedVideoExtensions.Contains(System.IO.Path.GetExtension(f).ToLowerInvariant()))
                    .OrderBy(f => File.GetLastWriteTimeUtc(f))
                    .ToList();

                _rawClips.RemoveAll(c => !filesOnDisk.Any(f => string.Equals(f, c.FilePath, StringComparison.OrdinalIgnoreCase)));

                if (forceReanalyze)
                {
                    _rawClips.Clear();
                }

                var missingFiles = filesOnDisk
                    .Where(f => !_rawClips.Any(c => string.Equals(c.FilePath, f, StringComparison.OrdinalIgnoreCase)))
                    .ToList();

                if (missingFiles.Count > 0)
                {
                    var sw = Stopwatch.StartNew();
                    WatcherStatusTxt.Text = $"Parallele KI-Analyse für {missingFiles.Count} Aufnahme(n)...";
                    var newlyAnalyzed = await VideoAiCutEngine.AnalyzeMultipleClipsParallelAsync(
                        missingFiles,
                        _thumbCacheDir,
                        (done, total, name) =>
                        {
                            Dispatcher.InvokeAsync(() =>
                            {
                                WatcherStatusTxt.Text = $"KI-Analyse ({done}/{total}): {name}...";
                            });
                        });
                    sw.Stop();
                    _rawClips.AddRange(newlyAnalyzed);
                    WatcherStatusTxt.Text = $"✓ {_rawClips.Count} Aufnahmen bereit (Analyse in {sw.ElapsedMilliseconds} ms)";
                }
                else
                {
                    WatcherStatusTxt.Text = $"Ordner 'Rohmaterial' synchron ({_rawClips.Count} Dateien)";
                }

                RawClipsCountTitle.Text = _rawClips.Count == 1
                    ? "1 Kamera-Aufnahme bereit"
                    : $"{_rawClips.Count} Kamera-Aufnahmen bereit";

                double totalRawSec = _rawClips.Sum(c => c.DurationSeconds);
                RawClipsSummaryTxt.Text = _rawClips.Count > 0
                    ? $"✓ {_rawClips.Count(c => c.IsIncluded)} von {_rawClips.Count} Aufnahmen aktiv ({totalRawSec:0.0} Sek. echtes Rohmaterial)"
                    : "Ziehe deine selbst gedrehten Videos (MP4/MOV) hier hinein oder lege sie in den Ordner 'Rohmaterial'.";

                RenderRawClipsList();
                RecomputeSmartCuts();
            }
            catch (Exception ex)
            {
                WatcherStatusTxt.Text = "Analyse-Hinweis: " + ex.Message;
            }
            finally
            {
                _isAnalyzingFolder = false;
            }
        }

        private void RenderRawClipsList()
        {
            if (RawClipsStackPanel == null) return;
            RawClipsStackPanel.Children.Clear();

            string cardBg = _isDarkMode ? "#0B0B0B" : "#F3F4F6";
            string cardBorder = _isDarkMode ? "#222222" : "#D1D5DB";
            string textPrimary = _isDarkMode ? "#FFFFFF" : "#111827";
            string textMuted = _isDarkMode ? "#9CA3AF" : "#4B5563";

            if (_rawClips.Count == 0)
            {
                var emptyCard = new Border
                {
                    Background = BrushFromHex(cardBg),
                    BorderBrush = BrushFromHex(cardBorder),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(14, 18, 14, 18),
                    Margin = new Thickness(0, 4, 0, 0)
                };
                var sp = new StackPanel();
                sp.Children.Add(new TextBlock
                {
                    Text = "Noch keine Rohvideos im Ordner",
                    Foreground = BrushFromHex(textPrimary),
                    FontWeight = FontWeights.Bold,
                    FontSize = 12.5,
                    HorizontalAlignment = HorizontalAlignment.Center
                });
                sp.Children.Add(new TextBlock
                {
                    Text = "Ziehe deine eigenen Kamera-, Smartphone- oder Action-Cam-Aufnahmen einfach in dieses Fenster oder klicke auf '⚡ Aus Videos laden'.",
                    Foreground = BrushFromHex(textMuted),
                    FontSize = 11,
                    TextWrapping = TextWrapping.Wrap,
                    TextAlignment = TextAlignment.Center,
                    Margin = new Thickness(0, 5, 0, 0)
                });
                emptyCard.Child = sp;
                RawClipsStackPanel.Children.Add(emptyCard);
                return;
            }

            foreach (var clip in _rawClips)
            {
                var card = new Border
                {
                    Background = BrushFromHex(cardBg),
                    BorderBrush = BrushFromHex(clip.IsIncluded ? "#C5A059" : cardBorder),
                    BorderThickness = new Thickness(clip.IsIncluded ? 1.2 : 1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(10, 9, 10, 9),
                    Margin = new Thickness(0, 0, 0, 8),
                    Cursor = Cursors.Hand,
                    ToolTip = $"{clip.AiDetailSummary}\nKlicken, um dieses Originalvideo im Studio-Player anzusehen"
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(76) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(10) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var thumbBorder = new Border
                {
                    Width = 76,
                    Height = 52,
                    CornerRadius = new CornerRadius(6),
                    Background = BrushFromHex("#141414"),
                    ClipToBounds = true
                };
                if (!string.IsNullOrEmpty(clip.ThumbnailPath) && File.Exists(clip.ThumbnailPath))
                {
                    try
                    {
                        var bmp = new BitmapImage();
                        bmp.BeginInit();
                        bmp.CacheOption = BitmapCacheOption.OnLoad;
                        bmp.UriSource = new Uri(clip.ThumbnailPath, UriKind.Absolute);
                        bmp.EndInit();
                        thumbBorder.Child = new Image { Source = bmp, Stretch = Stretch.UniformToFill };
                    }
                    catch { }
                }
                Grid.SetColumn(thumbBorder, 0);
                grid.Children.Add(thumbBorder);

                var infoStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                infoStack.Children.Add(new TextBlock
                {
                    Text = clip.FileName,
                    Foreground = BrushFromHex(textPrimary),
                    FontWeight = FontWeights.Bold,
                    FontSize = 12,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                infoStack.Children.Add(new TextBlock
                {
                    Text = $"{clip.DurationLabel} · {clip.ResolutionLabel} · {clip.FileSizeLabel}",
                    Foreground = BrushFromHex(textMuted),
                    FontSize = 10.5,
                    Margin = new Thickness(0, 2, 0, 2)
                });
                infoStack.Children.Add(new TextBlock
                {
                    Text = clip.AiBadgeText,
                    Foreground = BrushFromHex(clip.IsBadTakeWarning ? "#F59E0B" : "#C5A059"),
                    FontSize = 10.5,
                    FontWeight = FontWeights.SemiBold,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                Grid.SetColumn(infoStack, 2);
                grid.Children.Add(infoStack);

                var rightStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(6, 0, 0, 0) };
                var chk = new CheckBox
                {
                    IsChecked = clip.IsIncluded,
                    ToolTip = "Im KI-Schnitt verwenden",
                    HorizontalAlignment = HorizontalAlignment.Center,
                    Margin = new Thickness(0, 0, 0, 6)
                };
                var capturedClip = clip;
                chk.Click += (s, e) =>
                {
                    e.Handled = true;
                    capturedClip.IsIncluded = chk.IsChecked == true;
                    RenderRawClipsList();
                    RecomputeSmartCuts();
                };
                rightStack.Children.Add(chk);

                var delBtn = new TextBlock
                {
                    Text = "🗑",
                    Foreground = BrushFromHex(textMuted),
                    FontSize = 11.5,
                    HorizontalAlignment = HorizontalAlignment.Center,
                    Cursor = Cursors.Hand,
                    ToolTip = "Aus Rohmaterial-Ordner entfernen"
                };
                delBtn.MouseLeftButtonUp += (s, e) =>
                {
                    e.Handled = true;
                    try
                    {
                        if (PreviewPlayer.Source != null && string.Equals(PreviewPlayer.Source.LocalPath, capturedClip.FilePath, StringComparison.OrdinalIgnoreCase))
                        {
                            StopVideoPreview();
                        }
                        File.Delete(capturedClip.FilePath);
                    }
                    catch { }
                    _rawClips.Remove(capturedClip);
                    RenderRawClipsList();
                    RecomputeSmartCuts();
                };
                rightStack.Children.Add(delBtn);

                Grid.SetColumn(rightStack, 3);
                grid.Children.Add(rightStack);

                card.Child = grid;
                card.MouseLeftButtonUp += (s, e) =>
                {
                    PlaySingleClipSegmentInPreview(capturedClip.FilePath, 0, capturedClip.DurationSeconds, $"Original-Rohvideo: {capturedClip.FileName}", "📷 ROHVIDEO (UNGESCHNITTEN)");
                };

                RawClipsStackPanel.Children.Add(card);
            }
        }

        // =========================================================================
        // 6 VIRALE KI-REGIE-MODI, MUSIK-KATALOG & SCHNITT-BERECHNUNG
        // =========================================================================
        private void InitMusicCatalogCombo()
        {
            _suppressComboEvents = true;
            _musicCatalog = VideoAiCutEngine.GetStudioMusicCatalog(_musicAssetsDir);
            MusicTrackCombo.Items.Clear();
            foreach (var track in _musicCatalog)
            {
                MusicTrackCombo.Items.Add(new ComboBoxItem
                {
                    Tag = track,
                    Content = track.DisplayLabel
                });
            }
            if (MusicTrackCombo.Items.Count > 1)
            {
                MusicTrackCombo.SelectedIndex = 1; // Default: Pack With Me in Gifhorn (sf_09)
            }
            _suppressComboEvents = false;
            UpdateSelectedMusicHint();
        }

        private SeelenfunkeMusicTrackItem? GetSelectedMusicTrack()
        {
            if (MusicTrackCombo?.SelectedItem is ComboBoxItem item && item.Tag is SeelenfunkeMusicTrackItem tr)
            {
                return tr;
            }
            return _musicCatalog.FirstOrDefault();
        }

        private void UpdateSelectedMusicHint()
        {
            var tr = GetSelectedMusicTrack();
            if (tr == null || MusicTrackHintTxt == null) return;
            MusicTrackHintTxt.Text = tr.Description;
        }

        private void DirectorMode_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is not Border b || b.Tag is not string modeKey) return;
            _activeDirectorMode = modeKey;
            UpdateDirectorModeButtonsUI();

            _suppressComboEvents = true;
            if (modeKey == "packaging_pov")
            {
                SelectMusicById("sf_09"); // Pack With Me in Gifhorn
                TargetDurationSlider.Value = 15;
                MusicVolSlider.Value = 72;
                OrigAudioVolSlider.Value = 38;
                ActiveModeRuleHintTxt.Text = "4-Phasen Packaging: Teaser (1,4s) → Hero-Unikat → Micro-ASMR (2-Cam Zoom) → Samt-Finale";
            }
            else if (modeKey == "asmr_laser")
            {
                SelectMusicById("sf_07"); // Action-Cam White Glove
                TargetDurationSlider.Value = 14;
                MusicVolSlider.Value = 80;
                OrigAudioVolSlider.Value = 30;
                ActiveModeRuleHintTxt.Text = "Laser-Dramaturgie: Action-Hook (0–1,8s) → Takt-Schnitte → Edler Reveal";
            }
            else if (modeKey == "viral_drop")
            {
                SelectMusicById("sf_01"); // Golden Shimmer Drop
                TargetDurationSlider.Value = 9;
                MusicVolSlider.Value = 90;
                OrigAudioVolSlider.Value = 20;
                ActiveModeRuleHintTxt.Text = "Drop-Dramaturgie: Spannungsaufbau (0–4s) → Licht-Impuls exakt bei Sek. 4,0";
            }
            else if (modeKey == "unboxing_reveal")
            {
                SelectMusicById("sf_03"); // Velvet Impulse Beat
                TargetDurationSlider.Value = 12;
                MusicVolSlider.Value = 82;
                OrigAudioVolSlider.Value = 28;
                ActiveModeRuleHintTxt.Text = "Transformation: 1. Vorher/Rohling → 2. Whip-Flow Veredelung → 3. Nachher-Reveal";
            }
            else if (modeKey == "story_speech")
            {
                SelectMusicById("sf_04"); // Founder Trust Piano
                TargetDurationSlider.Value = 28;
                MusicVolSlider.Value = 15;
                OrigAudioVolSlider.Value = 100;
                ActiveModeRuleHintTxt.Text = "Gling/Opus-Stil: Sprechpausen > 0,38s entfernt + 2-Kamera-Zoom gegen Jump-Cuts";
            }
            else if (modeKey == "clean_all")
            {
                SelectMusicById("sf_08"); // Schiefer & Gold Manufaktur
                TargetDurationSlider.Value = 18;
                MusicVolSlider.Value = 80;
                OrigAudioVolSlider.Value = 25;
                ActiveModeRuleHintTxt.Text = "Chronologisch: Alle Roh-Clips ohne Anfangs-/End-Wackler im Takt verbunden";
            }
            _suppressComboEvents = false;
            UpdateSelectedMusicHint();
            RecomputeSmartCuts();
        }

        private void SelectMusicById(string id)
        {
            foreach (ComboBoxItem item in MusicTrackCombo.Items)
            {
                if (item.Tag is SeelenfunkeMusicTrackItem tr && tr.Id == id)
                {
                    MusicTrackCombo.SelectedItem = item;
                    break;
                }
            }
        }

        private void UpdateDirectorModeButtonsUI()
        {
            var buttons = new[] { ModeBtnPackaging, ModeBtnAsmr, ModeBtnViralDrop, ModeBtnUnboxing, ModeBtnSpeech, ModeBtnCleanAll };
            foreach (var btn in buttons)
            {
                if (btn == null) continue;
                bool active = (btn.Tag as string) == _activeDirectorMode;
                if (_isDarkMode)
                {
                    btn.Background = BrushFromHex(active ? "#1A150B" : "#0A0A0A");
                    btn.BorderBrush = BrushFromHex(active ? "#C5A059" : "#262626");
                    btn.BorderThickness = new Thickness(active ? 1.5 : 1);
                }
                else
                {
                    btn.Background = BrushFromHex(active ? "#FEF3C7" : "#F3F4F6");
                    btn.BorderBrush = BrushFromHex(active ? "#C5A059" : "#D1D5DB");
                    btn.BorderThickness = new Thickness(active ? 1.5 : 1);
                }

                if (btn.Child is StackPanel sp && sp.Children.Count > 0 && sp.Children[0] is TextBlock titleTxt)
                {
                    titleTxt.Foreground = BrushFromHex(active ? "#C5A059" : (_isDarkMode ? "#FFFFFF" : "#111827"));
                }
            }
        }

        private void RecalculateAiCuts_Click(object sender, RoutedEventArgs e)
        {
            RecomputeSmartCuts();
        }

        private void RecomputeSmartCuts()
        {
            if (!_isUiReady) return;
            var music = GetSelectedMusicTrack();
            double targetSec = TargetDurationSlider?.Value ?? 15.0;
            bool snapBeat = SnapToBeatCheck?.IsChecked == true;
            bool trimShake = AutoTrimShakeCheck?.IsChecked == true;
            string transPref = (TransitionCombo?.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "auto_director";

            _cutDecisions = VideoAiCutEngine.BuildSmartCutDecisionList(
                _rawClips,
                _activeDirectorMode,
                music,
                targetSec,
                snapBeat,
                trimShake,
                transPref);

            RenderCutDecisionsTimeline();
        }

        private void RenderCutDecisionsTimeline()
        {
            if (CutDecisionsStackPanel == null) return;
            CutDecisionsStackPanel.Children.Clear();

            double totalDur = _cutDecisions.Sum(c => c.DurationSec);
            CutListSummaryTxt.Text = $" ({_cutDecisions.Count} Schnitte · {totalDur:0.0} Sek.)";

            // Update OpusClip-style Virality & Pacing Score
            var music = GetSelectedMusicTrack();
            bool hasMusic = music != null && !string.IsNullOrEmpty(music.FileName) && (MusicVolSlider?.Value ?? 75) > 1;
            var scoreReport = VideoAiCutEngine.CalculateViralScoreReport(_cutDecisions, _activeDirectorMode, hasMusic, OrigAudioVolSlider?.Value ?? 35);
            if (ViralScoreBadgeTxt != null) ViralScoreBadgeTxt.Text = scoreReport.VerdictBadge;
            if (ViralPacingHintTxt != null) ViralPacingHintTxt.Text = scoreReport.PacingSummary;

            string cardBg = _isDarkMode ? "#0D0D0D" : "#FFFFFF";
            string cardBorder = _isDarkMode ? "#262626" : "#D1D5DB";
            string textPrimary = _isDarkMode ? "#FFFFFF" : "#111827";
            string textMuted = _isDarkMode ? "#9CA3AF" : "#4B5563";
            string btnBg = _isDarkMode ? "#171717" : "#F3F4F6";

            if (_cutDecisions.Count == 0)
            {
                CutDecisionsStackPanel.Children.Add(new TextBlock
                {
                    Text = "Sobald links mindestens ein eigenes Rohvideo geladen ist, berechnet die KI hier automatisch die virale Schnittfolge inkl. Übergängen.",
                    Foreground = BrushFromHex(textMuted),
                    FontSize = 11.5,
                    Margin = new Thickness(8, 14, 8, 14),
                    TextAlignment = TextAlignment.Center
                });
                return;
            }

            string[] transitionCycle = { "hard_action", "punch_zoom", "velvet_dip", "whip_flow", "flash_luma" };
            double[] speedCycle = { 1.00, 1.15, 0.88 };

            for (int i = 0; i < _cutDecisions.Count; i++)
            {
                var cut = _cutDecisions[i];
                int capturedIdx = i;

                var card = new Border
                {
                    Background = BrushFromHex(cardBg),
                    BorderBrush = BrushFromHex(cut.RoleKey is "teaser_hook" or "hook" or "drop" or "reveal" ? "#C5A059" : cardBorder),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(10, 8, 10, 8),
                    Margin = new Thickness(0, 0, 0, 7),
                    Cursor = Cursors.Hand,
                    ToolTip = "Klicken, um exakt diesen Schnitt-Ausschnitt im Player vorzuhören"
                };

                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(68) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(10) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var thumbBorder = new Border
                {
                    Width = 68,
                    Height = 48,
                    CornerRadius = new CornerRadius(6),
                    Background = BrushFromHex("#141414"),
                    ClipToBounds = true
                };
                if (!string.IsNullOrEmpty(cut.ThumbnailPath) && File.Exists(cut.ThumbnailPath))
                {
                    try
                    {
                        var bmp = new BitmapImage();
                        bmp.BeginInit();
                        bmp.CacheOption = BitmapCacheOption.OnLoad;
                        bmp.UriSource = new Uri(cut.ThumbnailPath, UriKind.Absolute);
                        bmp.EndInit();
                        thumbBorder.Child = new Image { Source = bmp, Stretch = Stretch.UniformToFill };
                    }
                    catch { }
                }
                Grid.SetColumn(thumbBorder, 0);
                g.Children.Add(thumbBorder);

                // Middle Details (Role + Transition Badge + Speed Badge + Reason)
                var midStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };

                var topRow = new StackPanel { Orientation = Orientation.Horizontal };
                var rolePill = new Border
                {
                    Background = BrushFromHex("#24C5A059"),
                    BorderBrush = BrushFromHex("#C5A059"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(5),
                    Padding = new Thickness(6, 1, 6, 1),
                    Margin = new Thickness(0, 0, 6, 0)
                };
                rolePill.Child = new TextBlock
                {
                    Text = $"#{cut.Index} · {cut.RoleBadge}",
                    Foreground = BrushFromHex("#C5A059"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold
                };
                topRow.Children.Add(rolePill);

                // Clickable Transition & 2-Cam Zoom Badge
                var transPill = new Border
                {
                    Background = BrushFromHex("#1410B981"),
                    BorderBrush = BrushFromHex("#10B981"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(5),
                    Padding = new Thickness(6, 1, 6, 1),
                    Margin = new Thickness(0, 0, 6, 0),
                    Cursor = Cursors.Hand,
                    ToolTip = "Klicken, um den Übergang / 2-Kamera-Zoom für diesen Schnitt zu wechseln"
                };
                transPill.Child = new TextBlock
                {
                    Text = cut.TransitionBadgeLabel,
                    Foreground = BrushFromHex("#10B981"),
                    FontSize = 9.5,
                    FontWeight = FontWeights.SemiBold
                };
                transPill.MouseLeftButtonUp += (s, e) =>
                {
                    e.Handled = true;
                    int curIdx = Array.IndexOf(transitionCycle, cut.TransitionType);
                    cut.TransitionType = transitionCycle[(curIdx + 1) % transitionCycle.Length];
                    cut.ZoomFactor = cut.TransitionType == "punch_zoom" ? 1.08 : 1.00;
                    RenderCutDecisionsTimeline();
                };
                topRow.Children.Add(transPill);

                // Clickable Speed Ramp Badge
                var speedPill = new Border
                {
                    Background = BrushFromHex(btnBg),
                    BorderBrush = BrushFromHex(cardBorder),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(5),
                    Padding = new Thickness(6, 1, 6, 1),
                    Margin = new Thickness(0, 0, 8, 0),
                    Cursor = Cursors.Hand,
                    ToolTip = "Klicken, um das Tempo für diesen Schnitt zu wechseln (1.0x Echtzeit / 1.15x Dynamik / 0.88x Smooth)"
                };
                speedPill.Child = new TextBlock
                {
                    Text = cut.SpeedBadgeLabel,
                    Foreground = BrushFromHex(textPrimary),
                    FontSize = 9.5
                };
                speedPill.MouseLeftButtonUp += (s, e) =>
                {
                    e.Handled = true;
                    int curS = Array.FindIndex(speedCycle, v => Math.Abs(v - cut.SpeedFactor) < 0.04);
                    cut.SpeedFactor = speedCycle[(curS + 1) % speedCycle.Length];
                    RenderCutDecisionsTimeline();
                };
                topRow.Children.Add(speedPill);

                topRow.Children.Add(new TextBlock
                {
                    Text = $"{cut.SourceFileName} ({cut.TimeRangeLabel})",
                    Foreground = BrushFromHex(textPrimary),
                    FontSize = 11,
                    FontWeight = FontWeights.Bold,
                    VerticalAlignment = VerticalAlignment.Center
                });
                midStack.Children.Add(topRow);

                midStack.Children.Add(new TextBlock
                {
                    Text = $"💡 {cut.AiCutReason}  ·  {cut.TimelinePositionLabel}",
                    Foreground = BrushFromHex(textMuted),
                    FontSize = 10.5,
                    Margin = new Thickness(0, 3, 0, 0),
                    TextTrimming = TextTrimming.CharacterEllipsis
                });

                Grid.SetColumn(midStack, 2);
                g.Children.Add(midStack);

                // Right Quick-Adjust Controls
                var ctrlRow = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };

                Button MakeMiniBtn(string label, string tooltip, Action onClick)
                {
                    var b = new Button
                    {
                        Content = label,
                        ToolTip = tooltip,
                        Background = BrushFromHex(btnBg),
                        Foreground = BrushFromHex(textPrimary),
                        BorderBrush = BrushFromHex(cardBorder),
                        BorderThickness = new Thickness(1),
                        Padding = new Thickness(6, 3, 6, 3),
                        Margin = new Thickness(3, 0, 0, 0),
                        FontSize = 10.5,
                        Cursor = Cursors.Hand
                    };
                    b.Click += (s, e) =>
                    {
                        e.Handled = true;
                        onClick();
                    };
                    return b;
                }

                ctrlRow.Children.Add(MakeMiniBtn("◀ -0.5s", "Startpunkt im Rohvideo 0,5 Sek. früher setzen", () =>
                {
                    cut.InPointSec = Math.Max(0, Math.Round(cut.InPointSec - 0.5, 2));
                    RenderCutDecisionsTimeline();
                    PreviewCutDecisionItem(cut);
                }));

                ctrlRow.Children.Add(MakeMiniBtn("+0.5s ▶", "Startpunkt im Rohvideo 0,5 Sek. später setzen", () =>
                {
                    double maxIn = Math.Max(0, cut.SourceClipTotalDuration - cut.DurationSec);
                    cut.InPointSec = Math.Min(maxIn, Math.Round(cut.InPointSec + 0.5, 2));
                    RenderCutDecisionsTimeline();
                    PreviewCutDecisionItem(cut);
                }));

                ctrlRow.Children.Add(MakeMiniBtn("Dauer -", "Schnitt um 0,4 Sek. kürzen", () =>
                {
                    cut.DurationSec = Math.Max(0.7, Math.Round(cut.DurationSec - 0.4, 2));
                    VideoAiCutEngine.RecalculateTimelinePositions(_cutDecisions);
                    RenderCutDecisionsTimeline();
                    PreviewCutDecisionItem(cut);
                }));

                ctrlRow.Children.Add(MakeMiniBtn("Dauer +", "Schnitt um 0,4 Sek. verlängern", () =>
                {
                    double maxDur = Math.Max(0.7, cut.SourceClipTotalDuration - cut.InPointSec);
                    cut.DurationSec = Math.Min(maxDur, Math.Round(cut.DurationSec + 0.4, 2));
                    VideoAiCutEngine.RecalculateTimelinePositions(_cutDecisions);
                    RenderCutDecisionsTimeline();
                    PreviewCutDecisionItem(cut);
                }));

                ctrlRow.Children.Add(MakeMiniBtn("🔄 Stelle", "Andere Stelle aus diesem Rohvideo wählen", () =>
                {
                    double step = Math.Max(1.4, cut.SourceClipTotalDuration * 0.25);
                    double nextIn = cut.InPointSec + step;
                    if (nextIn + cut.DurationSec > cut.SourceClipTotalDuration)
                        nextIn = 0.2;
                    cut.InPointSec = Math.Round(Math.Max(0, Math.Min(nextIn, cut.SourceClipTotalDuration - cut.DurationSec)), 2);
                    RenderCutDecisionsTimeline();
                    PreviewCutDecisionItem(cut);
                }));

                if (capturedIdx > 0)
                {
                    ctrlRow.Children.Add(MakeMiniBtn("▲", "Nach oben verschieben", () =>
                    {
                        (_cutDecisions[capturedIdx - 1], _cutDecisions[capturedIdx]) = (_cutDecisions[capturedIdx], _cutDecisions[capturedIdx - 1]);
                        VideoAiCutEngine.RecalculateTimelinePositions(_cutDecisions);
                        RenderCutDecisionsTimeline();
                    }));
                }

                if (capturedIdx < _cutDecisions.Count - 1)
                {
                    ctrlRow.Children.Add(MakeMiniBtn("▼", "Nach unten verschieben", () =>
                    {
                        (_cutDecisions[capturedIdx + 1], _cutDecisions[capturedIdx]) = (_cutDecisions[capturedIdx], _cutDecisions[capturedIdx + 1]);
                        VideoAiCutEngine.RecalculateTimelinePositions(_cutDecisions);
                        RenderCutDecisionsTimeline();
                    }));
                }

                ctrlRow.Children.Add(MakeMiniBtn("✕", "Diesen Schnitt aus der Timeline entfernen", () =>
                {
                    _cutDecisions.RemoveAt(capturedIdx);
                    VideoAiCutEngine.RecalculateTimelinePositions(_cutDecisions);
                    RenderCutDecisionsTimeline();
                }));

                Grid.SetColumn(ctrlRow, 3);
                g.Children.Add(ctrlRow);

                card.Child = g;
                card.MouseLeftButtonUp += (s, e) =>
                {
                    PreviewCutDecisionItem(cut);
                };

                CutDecisionsStackPanel.Children.Add(card);
            }
        }

        // =========================================================================
        // LIVE STUDIO VIDEO PLAYER & MULTI-SEGMENT SEQUENCE PREVIEW
        // =========================================================================
        private void PreviewCutDecisionItem(CutDecisionItem cut)
        {
            _isSequencePreviewActive = false;
            StopMusicPreview();
            PlaySingleClipSegmentInPreview(
                cut.SourceFilePath,
                cut.InPointSec,
                cut.OutPointSec,
                $"Schnitt #{cut.Index}: {cut.SourceFileName} ({cut.TimeRangeLabel} · {cut.TransitionBadgeLabel})",
                cut.RoleBadge);
        }

        private void PlaySingleClipSegmentInPreview(string filePath, double startSec, double stopSec, string titleLabel, string badgeLabel)
        {
            if (!File.Exists(filePath)) return;
            try
            {
                PlayerEmptyOverlay.Visibility = Visibility.Collapsed;
                PlayerActiveRoleBadge.Visibility = Visibility.Visible;
                PlayerActiveRoleTxt.Text = badgeLabel;
                PreviewTitleTxt.Text = titleLabel;
                PlayerClipNameTxt.Text = System.IO.Path.GetFileName(filePath);

                _activePreviewSegmentStopSec = stopSec;
                PreviewPlayer.Source = new Uri(filePath, UriKind.Absolute);
                PreviewPlayer.Volume = (OrigAudioVolSlider?.Value ?? 35.0) / 100.0;
                PreviewPlayer.Position = TimeSpan.FromSeconds(Math.Max(0, startSec));
                PreviewPlayer.Play();
                _isPlayerPlaying = true;
                PlayerPlayPauseBtn.Content = "❚❚";
                _playerTickTimer.Start();
            }
            catch { }
        }

        private void PlayFullCutSequencePreview_Click(object sender, RoutedEventArgs e)
        {
            if (_cutDecisions.Count == 0) return;

            if (_isSequencePreviewActive)
            {
                StopVideoPreview();
                StopMusicPreview();
                return;
            }

            _isSequencePreviewActive = true;
            _sequencePreviewIndex = 0;
            PlayFullCutPreviewBtn.Content = "⏹ Vorschau stoppen";

            var music = GetSelectedMusicTrack();
            if (music != null && !string.IsNullOrEmpty(music.FullPath) && File.Exists(music.FullPath))
            {
                try
                {
                    _musicPreviewPlayer.Open(new Uri(music.FullPath, UriKind.Absolute));
                    _musicPreviewPlayer.Volume = (MusicVolSlider?.Value ?? 75.0) / 100.0;
                    _musicPreviewPlayer.Position = TimeSpan.Zero;
                    _musicPreviewPlayer.Play();
                    _isMusicPreviewPlaying = true;
                }
                catch { }
            }

            StartCurrentSequenceStep();
        }

        private void StartCurrentSequenceStep()
        {
            if (!_isSequencePreviewActive || _sequencePreviewIndex < 0 || _sequencePreviewIndex >= _cutDecisions.Count)
            {
                StopVideoPreview();
                StopMusicPreview();
                return;
            }

            var cut = _cutDecisions[_sequencePreviewIndex];
            PlaySingleClipSegmentInPreview(
                cut.SourceFilePath,
                cut.InPointSec,
                cut.OutPointSec,
                $"KI-Gesamtvorschau ({_sequencePreviewIndex + 1}/{_cutDecisions.Count}): {cut.SourceFileName}",
                $"{cut.RoleBadge} · {cut.TransitionBadgeLabel}");
        }

        private void PlayerTickTimer_Tick(object? sender, EventArgs e)
        {
            if (!_isPlayerPlaying || PreviewPlayer.Source == null) return;

            double curSec = PreviewPlayer.Position.TotalSeconds;
            double totalSec = PreviewPlayer.NaturalDuration.HasTimeSpan ? PreviewPlayer.NaturalDuration.TimeSpan.TotalSeconds : 10.0;

            PlayerTimelineSlider.Maximum = Math.Max(1.0, totalSec);
            PlayerTimelineSlider.Value = Math.Clamp(curSec, 0, totalSec);
            PlayerTimeTxt.Text = $"{RawVideoClipInfo.FormatSeconds(curSec)} / {RawVideoClipInfo.FormatSeconds(totalSec)}";

            if (_activePreviewSegmentStopSec.HasValue && curSec >= _activePreviewSegmentStopSec.Value - 0.04)
            {
                if (_isSequencePreviewActive)
                {
                    _sequencePreviewIndex++;
                    if (_sequencePreviewIndex < _cutDecisions.Count)
                    {
                        StartCurrentSequenceStep();
                    }
                    else
                    {
                        StopVideoPreview();
                        StopMusicPreview();
                    }
                }
                else
                {
                    PreviewPlayer.Pause();
                    _isPlayerPlaying = false;
                    PlayerPlayPauseBtn.Content = "▶";
                    _playerTickTimer.Stop();
                }
            }
        }

        private void PreviewPlayer_MediaOpened(object sender, RoutedEventArgs e)
        {
            if (PreviewPlayer.NaturalDuration.HasTimeSpan)
            {
                PlayerTimelineSlider.Maximum = Math.Max(1.0, PreviewPlayer.NaturalDuration.TimeSpan.TotalSeconds);
            }
        }

        private void PreviewPlayer_MediaEnded(object sender, RoutedEventArgs e)
        {
            if (_isSequencePreviewActive)
            {
                _sequencePreviewIndex++;
                if (_sequencePreviewIndex < _cutDecisions.Count)
                {
                    StartCurrentSequenceStep();
                    return;
                }
            }
            StopVideoPreview();
            StopMusicPreview();
        }

        private void PlayerPlayPause_Click(object sender, RoutedEventArgs e)
        {
            if (PreviewPlayer.Source == null)
            {
                if (_cutDecisions.Count > 0)
                {
                    PreviewCutDecisionItem(_cutDecisions[0]);
                }
                return;
            }

            if (_isPlayerPlaying)
            {
                PreviewPlayer.Pause();
                _isPlayerPlaying = false;
                PlayerPlayPauseBtn.Content = "▶";
                StopMusicPreview();
            }
            else
            {
                _activePreviewSegmentStopSec = null;
                PreviewPlayer.Play();
                _isPlayerPlaying = true;
                PlayerPlayPauseBtn.Content = "❚❚";
                _playerTickTimer.Start();
            }
        }

        private void PlayerTimelineSlider_MouseUp(object sender, MouseButtonEventArgs e)
        {
            if (PreviewPlayer.Source == null) return;
            _activePreviewSegmentStopSec = null;
            PreviewPlayer.Position = TimeSpan.FromSeconds(PlayerTimelineSlider.Value);
        }

        private void StopVideoPreview()
        {
            _isSequencePreviewActive = false;
            _isPlayerPlaying = false;
            _activePreviewSegmentStopSec = null;
            _playerTickTimer.Stop();
            try { PreviewPlayer.Pause(); } catch { }
            PlayerPlayPauseBtn.Content = "▶";
            PlayFullCutPreviewBtn.Content = "▶ Komplette KI-Schnitt-Vorschau abspielen";
        }

        // =========================================================================
        // RIGHT COLUMN SETTINGS & MUSIC PREVIEW
        // =========================================================================
        private void SettingsCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (!_isUiReady || _suppressComboEvents) return;
            RecomputeSmartCuts();
        }

        private void CriteriaCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (!_isUiReady || _suppressComboEvents) return;
            RecomputeSmartCuts();
        }

        private void TargetDurationSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (TargetDurationLabelTxt != null)
            {
                TargetDurationLabelTxt.Text = $"{(int)Math.Round(e.NewValue)} Sek.";
            }
            if (!_isUiReady || _suppressComboEvents) return;
            RecomputeSmartCuts();
        }

        private void MusicTrackCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (!_isUiReady || _suppressComboEvents) return;
            StopMusicPreview();
            UpdateSelectedMusicHint();
            RecomputeSmartCuts();
        }

        private void AudioMixSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (MusicVolLabelTxt != null && MusicVolSlider != null)
            {
                MusicVolLabelTxt.Text = $"{(int)Math.Round(MusicVolSlider.Value)} %";
                _musicPreviewPlayer.Volume = MusicVolSlider.Value / 100.0;
            }
            if (OrigAudioVolLabelTxt != null && OrigAudioVolSlider != null)
            {
                OrigAudioVolLabelTxt.Text = $"{(int)Math.Round(OrigAudioVolSlider.Value)} %";
                if (PreviewPlayer != null)
                    PreviewPlayer.Volume = OrigAudioVolSlider.Value / 100.0;
            }
        }

        private void ToggleMusicPreview_Click(object sender, RoutedEventArgs e)
        {
            if (_isMusicPreviewPlaying)
            {
                StopMusicPreview();
                return;
            }

            var tr = GetSelectedMusicTrack();
            if (tr == null || string.IsNullOrEmpty(tr.FullPath) || !File.Exists(tr.FullPath)) return;

            try
            {
                _musicPreviewPlayer.Open(new Uri(tr.FullPath, UriKind.Absolute));
                _musicPreviewPlayer.Volume = (MusicVolSlider?.Value ?? 75.0) / 100.0;
                _musicPreviewPlayer.Play();
                _isMusicPreviewPlaying = true;
                PreviewMusicBtnTxt.Text = "⏹ Stopp";
            }
            catch { }
        }

        private void StopMusicPreview()
        {
            _isMusicPreviewPlaying = false;
            try { _musicPreviewPlayer.Stop(); } catch { }
            if (PreviewMusicBtnTxt != null) PreviewMusicBtnTxt.Text = "▶ Anhören";
        }

        private void SelectCustomMusicFile_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Eigene Musikdatei auswählen",
                Filter = "Audiodateien (*.mp3;*.m4a;*.wav;*.aac)|*.mp3;*.m4a;*.wav;*.aac"
            };
            if (dlg.ShowDialog() == true && File.Exists(dlg.FileName))
            {
                var customItem = new SeelenfunkeMusicTrackItem
                {
                    Id = "custom_" + Guid.NewGuid().ToString("N")[..6],
                    Title = System.IO.Path.GetFileNameWithoutExtension(dlg.FileName),
                    CategoryLabel = "Eigene Datei",
                    Bpm = 90,
                    ShimmerDropSec = 4.0,
                    FileName = System.IO.Path.GetFileName(dlg.FileName),
                    FullPath = dlg.FileName,
                    Description = $"Eigene Audiodatei: {System.IO.Path.GetFileName(dlg.FileName)}"
                };
                _musicCatalog.Add(customItem);
                var cbi = new ComboBoxItem { Tag = customItem, Content = customItem.DisplayLabel };
                MusicTrackCombo.Items.Add(cbi);
                MusicTrackCombo.SelectedItem = cbi;
            }
        }

        // =========================================================================
        // 1-CLICK MASTER MP4 RENDER & EXPORT
        // =========================================================================
        private async void RenderExportMaster_Click(object sender, RoutedEventArgs e)
        {
            if (_isRenderingVideo) return;
            if (_cutDecisions.Count == 0)
            {
                MessageBox.Show(
                    "Bitte lege zuerst mindestens ein eigenes Rohvideo in den Ordner 'Rohmaterial' (oder ziehe es links in das Fenster).",
                    "Kein Rohmaterial geladen",
                    MessageBoxButton.OK,
                    MessageBoxImage.Information);
                return;
            }

            StopVideoPreview();
            StopMusicPreview();

            _isRenderingVideo = true;
            RenderExportMasterBtn.IsEnabled = false;
            RenderProgressCard.Visibility = Visibility.Visible;
            RenderProgressBar.Value = 2;
            RenderPercentTxt.Text = "2 %";
            RenderStatusTxt.Text = "Starte paralleles FFmpeg Studio-Rendering & ASMR-Mastering...";

            string aspectTag = (AspectRatioCombo.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "9_16_crop";
            string colorTag = (ColorGradingCombo.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "atelier_gold";
            string transTag = (TransitionCombo.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "auto_director";
            var music = GetSelectedMusicTrack();

            string timestamp = DateTime.Now.ToString("yyyy-MM-dd_HH-mm-ss");
            string modeLabel = _activeDirectorMode switch
            {
                "packaging_pov" => "PackagingViral",
                "viral_drop" => "ShimmerDrop",
                "unboxing_reveal" => "VorherNachher",
                "story_speech" => "StorySprache",
                "clean_all" => "Chronologisch",
                _ => "LaserASMR"
            };
            string outFileName = $"Seelenfunke_{modeLabel}_{timestamp}.mp4";
            string outFullPath = System.IO.Path.Combine(_outputFolderDir, outFileName);

            var renderOpts = new VideoRenderOptions
            {
                DirectorModeKey = _activeDirectorMode,
                AspectRatioMode = aspectTag,
                ColorGradingMode = colorTag,
                TransitionMode = transTag,
                TargetTotalDurationSec = TargetDurationSlider.Value,
                MusicFilePath = music?.FullPath,
                MusicVolumePercent = MusicVolSlider.Value,
                OriginalAudioVolumePercent = OrigAudioVolSlider.Value,
                SnapCutsToBeat = SnapToBeatCheck.IsChecked == true,
                AutoTrimCameraShake = AutoTrimShakeCheck.IsChecked == true,
                EnableAsmrAudioMastering = AsmrMasteringCheck?.IsChecked == true,
                OutputFilePath = outFullPath
            };

            var cutsSnapshot = _cutDecisions.ToList();

            var (success, savedPath, errorMsg) = await VideoAiCutEngine.RenderMasterVideoAsync(
                cutsSnapshot,
                renderOpts,
                (pct, msg) =>
                {
                    Dispatcher.InvokeAsync(() =>
                    {
                        RenderProgressBar.Value = pct;
                        RenderPercentTxt.Text = $"{pct} %";
                        RenderStatusTxt.Text = msg;
                    });
                });

            _isRenderingVideo = false;
            RenderExportMasterBtn.IsEnabled = true;

            if (success && File.Exists(savedPath))
            {
                RenderProgressBar.Value = 100;
                RenderPercentTxt.Text = "100 %";
                RenderStatusTxt.Text = $"✓ Gespeichert: {System.IO.Path.GetFileName(savedPath)}";
                RefreshFinishedVideosList();

                var fi = new FileInfo(savedPath);
                PlaySingleClipSegmentInPreview(
                    savedPath,
                    0,
                    9999,
                    $"🎬 FERTIGES MASTER-VIDEO: {fi.Name}",
                    "✓ FERTIG GESCHNITTENES MP4");
            }
            else
            {
                RenderStatusTxt.Text = "✕ Fehler: " + errorMsg;
                MessageBox.Show("Fehler beim Video-Export:\n" + errorMsg, "Funkenwerk Video", MessageBoxButton.OK, MessageBoxImage.Warning);
            }
        }

        private void RefreshFinishedVideosList()
        {
            if (FinishedVideosStackPanel == null) return;
            FinishedVideosStackPanel.Children.Clear();

            Directory.CreateDirectory(_outputFolderDir);
            var files = Directory.GetFiles(_outputFolderDir, "*.mp4")
                .OrderByDescending(f => File.GetLastWriteTimeUtc(f))
                .Take(6)
                .ToList();

            string textPrimary = _isDarkMode ? "#FFFFFF" : "#111827";
            string textMuted = _isDarkMode ? "#9CA3AF" : "#4B5563";
            string rowBg = _isDarkMode ? "#121212" : "#F3F4F6";

            if (files.Count == 0)
            {
                FinishedVideosStackPanel.Children.Add(new TextBlock
                {
                    Text = "Noch keine geschnittenen Videos im Ordner 'Fertige Videos'.",
                    Foreground = BrushFromHex(textMuted),
                    FontSize = 11,
                    Margin = new Thickness(0, 4, 0, 4)
                });
                return;
            }

            foreach (var f in files)
            {
                var fi = new FileInfo(f);
                var row = new Border
                {
                    Background = BrushFromHex(rowBg),
                    CornerRadius = new CornerRadius(7),
                    Padding = new Thickness(8, 6, 8, 6),
                    Margin = new Thickness(0, 0, 0, 5),
                    Cursor = Cursors.Hand,
                    ToolTip = "Klicken, um das fertige Video im Studio-Player abzuspielen"
                };

                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var st = new StackPanel();
                st.Children.Add(new TextBlock
                {
                    Text = fi.Name,
                    Foreground = BrushFromHex(textPrimary),
                    FontSize = 11,
                    FontWeight = FontWeights.SemiBold,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                st.Children.Add(new TextBlock
                {
                    Text = $"{(fi.Length / (1024.0 * 1024.0)):0.1} MB · {fi.LastWriteTime:dd.MM. HH:mm}",
                    Foreground = BrushFromHex(textMuted),
                    FontSize = 10
                });
                Grid.SetColumn(st, 0);
                g.Children.Add(st);

                var openBtn = new TextBlock
                {
                    Text = "📂",
                    Foreground = BrushFromHex("#C5A059"),
                    FontSize = 13,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(8, 0, 0, 0),
                    ToolTip = "Im Windows Explorer markieren"
                };
                string capturedPath = f;
                openBtn.MouseLeftButtonUp += (s, e) =>
                {
                    e.Handled = true;
                    Process.Start(new ProcessStartInfo("explorer.exe", $"/select,\"{capturedPath}\"") { UseShellExecute = true });
                };
                Grid.SetColumn(openBtn, 1);
                g.Children.Add(openBtn);

                row.Child = g;
                row.MouseLeftButtonUp += (s, e) =>
                {
                    PlaySingleClipSegmentInPreview(
                        capturedPath,
                        0,
                        9999,
                        $"🎬 FERTIGES MASTER-VIDEO: {fi.Name}",
                        "✓ FERTIG GESCHNITTENES MP4");
                };

                FinishedVideosStackPanel.Children.Add(row);
            }
        }
    }
}

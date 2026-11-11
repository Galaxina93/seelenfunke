using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Win32;

namespace FunkenwerkVideo
{
    public sealed class ProjectFolderItem
    {
        public string Name { get; set; } = "";
        public string FolderPath { get; set; } = "";
        public bool IsSelected { get; set; }
        public List<RawClipInfo> Clips { get; set; } = new();
    }

    public partial class MainWindow : Window
    {
        private bool _isDarkMode = true;
        private bool _isPlaying = false;
        private bool _isRendering = false;
        private string _currentVideoPath = "";
        private readonly List<ProjectFolderItem> _projects = new();
        private readonly HashSet<string> _customProjectFolders = new(StringComparer.OrdinalIgnoreCase);
        private readonly HashSet<string> _selectedFolderPaths = new(StringComparer.OrdinalIgnoreCase);
        private readonly HashSet<string> _pendingDeletedPaths = new(StringComparer.OrdinalIgnoreCase);
        private FileSystemWatcher? _rawTourWatcher;
        private readonly Dictionary<(DependencyObject Obj, DependencyProperty Prop), string> _originalDarkBrushes = new();

        private static readonly Dictionary<string, string> _darkToLightHexMap = new(StringComparer.OrdinalIgnoreCase)
        {
            ["#000000"] = "#F8FAFC",
            ["#07090D"] = "#EEF2F6",
            ["#0F1117"] = "#F3F4F6",
            ["#11131A"] = "#FFFFFF",
            ["#181B24"] = "#F8FAFC",
            ["#232734"] = "#E2E8F0",
            ["#242936"] = "#E2E8F0",
            ["#262A36"] = "#CBD5E1",
            ["#26231F"] = "#FDF8ED",
            ["#F9FAFB"] = "#111827",
            ["#D1D5DB"] = "#374151",
            ["#9CA3AF"] = "#4B5563"
        };

        public MainWindow()
        {
            InitializeComponent();
            Loaded += MainWindow_Loaded;
        }

        private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            CaptureOriginalDarkBrushes(this);
            ApplyThemeMode(_isDarkMode);
            StartRawTourWatcher();
            await ReloadProjectsAsync();
            LoadLatestVideoIntoPreview();
        }

        private void StartRawTourWatcher()
        {
            try
            {
                string rawDir = ViralVideographerEngine.GetRawTourDir();
                _rawTourWatcher = new FileSystemWatcher(rawDir)
                {
                    NotifyFilter = NotifyFilters.FileName | NotifyFilters.Size | NotifyFilters.LastWrite,
                    EnableRaisingEvents = true
                };
                FileSystemEventHandler handler = (_, _) =>
                {
                    Dispatcher.InvokeAsync(async () =>
                    {
                        if (!_isRendering)
                            await ReloadProjectsAsync();
                    });
                };
                _rawTourWatcher.Created += handler;
                _rawTourWatcher.Deleted += handler;
                _rawTourWatcher.Renamed += (_, _) => handler(this, null!);
            }
            catch { }
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
            var key = (obj, prop);
            if (_originalDarkBrushes.ContainsKey(key)) return;
            if (obj.ReadLocalValue(prop) is SolidColorBrush scb && scb.Color.A > 0)
            {
                string hex = $"#{scb.Color.R:X2}{scb.Color.G:X2}{scb.Color.B:X2}";
                _originalDarkBrushes[key] = hex;
            }
        }

        private void ApplyThemeMode(bool isDark)
        {
            _isDarkMode = isDark;
            Background = BrushFromHex("#0F1117");
            Foreground = BrushFromHex("#F9FAFB");

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
                ThemeToggleBtn.Content = _isDarkMode ? "🌙 Darkmode" : "☀ Hell";
            }

            RenderProjectsUI();
        }

        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            ApplyThemeMode(!_isDarkMode);
        }

        private async Task ReloadProjectsAsync()
        {
            var folderCandidates = new List<string>();

            // 1. Default Rohmaterial_Tour folder
            string rawTourDir = ViralVideographerEngine.GetRawTourDir();
            folderCandidates.Add(rawTourDir);

            // 2. Subfolders inside 'Projekte'
            string projectsRoot = ViralVideographerEngine.GetProjectsDir();
            if (Directory.Exists(projectsRoot))
            {
                foreach (var dir in Directory.GetDirectories(projectsRoot).OrderBy(d => d, StringComparer.OrdinalIgnoreCase))
                {
                    folderCandidates.Add(dir);
                }
            }

            // 3. Any custom added project folders
            foreach (var customDir in _customProjectFolders)
            {
                if (Directory.Exists(customDir))
                    folderCandidates.Add(customDir);
            }

            var distinctFolders = folderCandidates
                .Select(f => System.IO.Path.GetFullPath(f))
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .ToList();

            var newProjects = new List<ProjectFolderItem>();
            foreach (var folder in distinctFolders)
            {
                if (!Directory.Exists(folder)) continue;

                var files = Directory.GetFiles(folder)
                    .Where(f => ViralVideographerEngine.AllowedVideoExtensions.Contains(System.IO.Path.GetExtension(f)))
                    .Where(f => !_pendingDeletedPaths.Contains(System.IO.Path.GetFullPath(f)))
                    .OrderBy(f => f, StringComparer.OrdinalIgnoreCase)
                    .ToList();

                var clips = new List<RawClipInfo>();
                foreach (var f in files)
                {
                    if (_pendingDeletedPaths.Contains(System.IO.Path.GetFullPath(f))) continue;
                    var info = await ViralVideographerEngine.AnalyzeRawClipAsync(f);
                    if (info != null && !_pendingDeletedPaths.Contains(info.FilePath))
                        clips.Add(info);
                }

                string folderName = new DirectoryInfo(folder).Name;
                newProjects.Add(new ProjectFolderItem
                {
                    Name = folderName,
                    FolderPath = folder,
                    IsSelected = _selectedFolderPaths.Contains(folder),
                    Clips = clips
                });
            }

            // Auto-select first project with clips if none selected yet
            if (_selectedFolderPaths.Count == 0 && newProjects.Count > 0)
            {
                var best = newProjects.FirstOrDefault(p => p.Clips.Count > 0) ?? newProjects[0];
                best.IsSelected = true;
                _selectedFolderPaths.Add(best.FolderPath);
            }

            _projects.Clear();
            _projects.AddRange(newProjects);
            RenderProjectsUI();
        }

        private void RenderProjectsUI()
        {
            if (ProjectsStackPanel == null) return;
            ProjectsStackPanel.Children.Clear();

            foreach (var proj in _projects)
            {
                string projPath = proj.FolderPath;
                bool isChecked = proj.IsSelected;

                var card = new Border
                {
                    Background = BrushFromHex("#181B24"),
                    BorderBrush = isChecked ? BrushFromHex("#C5A059") : BrushFromHex("#232734"),
                    BorderThickness = new Thickness(isChecked ? 1.5 : 1),
                    CornerRadius = new CornerRadius(12),
                    Padding = new Thickness(14, 12, 14, 12),
                    Margin = new Thickness(0, 0, 0, 10)
                };

                var cardStack = new StackPanel();

                // Header Row: Checkbox + Folder Name + Video Count Badge + Quick Buttons
                var headerGrid = new Grid { Cursor = Cursors.Hand };
                headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var chk = new CheckBox
                {
                    IsChecked = isChecked,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 12, 0),
                    Cursor = Cursors.Hand,
                    LayoutTransform = new ScaleTransform(1.2, 1.2)
                };
                chk.Click += (s, e) =>
                {
                    e.Handled = true;
                    ToggleProjectSelection(projPath, chk.IsChecked == true);
                };
                Grid.SetColumn(chk, 0);
                headerGrid.Children.Add(chk);

                var titlePanel = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                titlePanel.Children.Add(new TextBlock
                {
                    Text = $"📁 {proj.Name}",
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 14,
                    FontWeight = FontWeights.Bold,
                    VerticalAlignment = VerticalAlignment.Center
                });
                Grid.SetColumn(titlePanel, 1);
                headerGrid.Children.Add(titlePanel);

                var countBadge = new Border
                {
                    Background = BrushFromHex("#26231F"),
                    BorderBrush = BrushFromHex("#C5A059"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(6),
                    Padding = new Thickness(8, 3, 8, 3),
                    Margin = new Thickness(8, 0, 10, 0),
                    VerticalAlignment = VerticalAlignment.Center
                };
                countBadge.Child = new TextBlock
                {
                    Text = $"{proj.Clips.Count} Videos",
                    Foreground = BrushFromHex("#C5A059"),
                    FontSize = 11,
                    FontWeight = FontWeights.Bold
                };
                Grid.SetColumn(countBadge, 2);
                headerGrid.Children.Add(countBadge);

                var actionsPanel = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };

                var addVidBtn = new Button
                {
                    Content = "＋",
                    Width = 28,
                    Height = 26,
                    Background = BrushFromHex("#11131A"),
                    Foreground = BrushFromHex("#C5A059"),
                    BorderBrush = BrushFromHex("#262A36"),
                    BorderThickness = new Thickness(1),
                    Margin = new Thickness(0, 0, 5, 0),
                    Cursor = Cursors.Hand,
                    FontWeight = FontWeights.Bold,
                    ToolTip = "Videos hinzufügen"
                };
                addVidBtn.Click += async (s, e) =>
                {
                    e.Handled = true;
                    await PickAndImportVideosToFolderAsync(projPath);
                };
                actionsPanel.Children.Add(addVidBtn);

                var openDirBtn = new Button
                {
                    Content = "📂",
                    Width = 28,
                    Height = 26,
                    Background = BrushFromHex("#11131A"),
                    Foreground = BrushFromHex("#D1D5DB"),
                    BorderBrush = BrushFromHex("#262A36"),
                    BorderThickness = new Thickness(1),
                    Margin = new Thickness(0, 0, 5, 0),
                    Cursor = Cursors.Hand,
                    FontSize = 11,
                    ToolTip = "Ordner öffnen"
                };
                openDirBtn.Click += (s, e) =>
                {
                    e.Handled = true;
                    OpenFolderInExplorer(projPath);
                };
                actionsPanel.Children.Add(openDirBtn);

                var clearBtn = new Button
                {
                    Content = "✕",
                    Width = 28,
                    Height = 26,
                    Background = BrushFromHex("#26231F"),
                    Foreground = BrushFromHex("#F87171"),
                    BorderBrush = BrushFromHex("#262A36"),
                    BorderThickness = new Thickness(1),
                    Cursor = Cursors.Hand,
                    FontWeight = FontWeights.Bold,
                    ToolTip = "Projekt leeren / entfernen"
                };
                clearBtn.Click += (s, e) =>
                {
                    e.Handled = true;
                    ClearOrRemoveProjectFolder(proj);
                };
                actionsPanel.Children.Add(clearBtn);

                Grid.SetColumn(actionsPanel, 3);
                headerGrid.Children.Add(actionsPanel);

                headerGrid.MouseLeftButtonUp += (_, _) =>
                {
                    ToggleProjectSelection(projPath, !isChecked);
                };

                cardStack.Children.Add(headerGrid);

                // Show Raw Video Files inside this Project Folder
                if (proj.Clips.Count > 0)
                {
                    var clipsContainer = new StackPanel { Margin = new Thickness(0, 10, 0, 0) };
                    foreach (var clip in proj.Clips.ToList())
                    {
                        string clipPath = clip.FilePath;
                        var clipRow = new Border
                        {
                            Background = BrushFromHex("#11131A"),
                            BorderBrush = BrushFromHex("#232734"),
                            BorderThickness = new Thickness(1),
                            CornerRadius = new CornerRadius(8),
                            Padding = new Thickness(10, 6, 10, 6),
                            Margin = new Thickness(0, 0, 0, 5),
                            Cursor = Cursors.Hand
                        };

                        var cg = new Grid();
                        cg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                        cg.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                        cg.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                        var nameTxt = new TextBlock
                        {
                            Text = $"🎬 {clip.FileName}",
                            Foreground = BrushFromHex("#F9FAFB"),
                            FontSize = 12,
                            FontWeight = FontWeights.SemiBold,
                            VerticalAlignment = VerticalAlignment.Center,
                            TextTrimming = TextTrimming.CharacterEllipsis
                        };
                        Grid.SetColumn(nameTxt, 0);
                        cg.Children.Add(nameTxt);

                        var sizeTxt = new TextBlock
                        {
                            Text = clip.SizeFormatted,
                            Foreground = BrushFromHex("#9CA3AF"),
                            FontSize = 11,
                            VerticalAlignment = VerticalAlignment.Center,
                            Margin = new Thickness(10, 0, 10, 0)
                        };
                        Grid.SetColumn(sizeTxt, 1);
                        cg.Children.Add(sizeTxt);

                        var delClipBtn = new Button
                        {
                            Content = "✕",
                            Width = 26,
                            Height = 24,
                            Background = BrushFromHex("#26231F"),
                            Foreground = BrushFromHex("#F87171"),
                            BorderBrush = BrushFromHex("#262A36"),
                            BorderThickness = new Thickness(1),
                            Cursor = Cursors.Hand,
                            FontSize = 11,
                            FontWeight = FontWeights.Bold,
                            VerticalAlignment = VerticalAlignment.Center,
                            ToolTip = "Video entfernen"
                        };
                        delClipBtn.Click += (s, e) =>
                        {
                            e.Handled = true;
                            RemoveSingleClipInstant(proj, clipPath);
                        };
                        Grid.SetColumn(delClipBtn, 2);
                        cg.Children.Add(delClipBtn);

                        clipRow.MouseLeftButtonUp += (_, _) => LoadVideoIntoPreviewPlayer(clipPath);
                        clipRow.Child = cg;
                        clipsContainer.Children.Add(clipRow);
                    }
                    cardStack.Children.Add(clipsContainer);
                }

                card.Child = cardStack;
                ProjectsStackPanel.Children.Add(card);
            }

            // Clean Drop Box at the bottom of Projects list
            var dropBox = new Border
            {
                Background = BrushFromHex("#26231F"),
                BorderBrush = BrushFromHex("#C5A059"),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(10),
                Padding = new Thickness(14, 12, 14, 12),
                Margin = new Thickness(0, 4, 0, 0),
                Cursor = Cursors.Hand
            };
            dropBox.MouseLeftButtonUp += async (_, _) =>
            {
                string targetDir = GetPrimarySelectedFolderPath();
                await PickAndImportVideosToFolderAsync(targetDir);
            };
            dropBox.Child = new TextBlock
            {
                Text = "📥 Projektordner oder Rohvideos hier reinwerfen (Drag & Drop)",
                Foreground = BrushFromHex("#C5A059"),
                FontSize = 12,
                FontWeight = FontWeights.Bold,
                HorizontalAlignment = HorizontalAlignment.Center
            };
            ProjectsStackPanel.Children.Add(dropBox);
        }

        private void ToggleProjectSelection(string folderPath, bool isSelected)
        {
            string full = System.IO.Path.GetFullPath(folderPath);
            if (isSelected)
                _selectedFolderPaths.Add(full);
            else
                _selectedFolderPaths.Remove(full);

            foreach (var p in _projects)
            {
                if (string.Equals(p.FolderPath, full, StringComparison.OrdinalIgnoreCase))
                    p.IsSelected = isSelected;
            }
            RenderProjectsUI();
        }

        private string GetPrimarySelectedFolderPath()
        {
            var selected = _projects.FirstOrDefault(p => p.IsSelected);
            if (selected != null && Directory.Exists(selected.FolderPath))
                return selected.FolderPath;
            return ViralVideographerEngine.GetRawTourDir();
        }

        private void RemoveSingleClipInstant(ProjectFolderItem proj, string clipFilePath)
        {
            string full = System.IO.Path.GetFullPath(clipFilePath);
            lock (_pendingDeletedPaths)
            {
                _pendingDeletedPaths.Add(full);
            }
            proj.Clips.RemoveAll(c => string.Equals(c.FilePath, full, StringComparison.OrdinalIgnoreCase));
            RenderProjectsUI();
            _ = DeleteFileWithRetryAsync(full);
        }

        private void ClearOrRemoveProjectFolder(ProjectFolderItem proj)
        {
            var pathsToDelete = proj.Clips.Select(c => c.FilePath).ToList();
            foreach (var p in pathsToDelete)
            {
                lock (_pendingDeletedPaths)
                {
                    _pendingDeletedPaths.Add(System.IO.Path.GetFullPath(p));
                }
            }
            proj.Clips.Clear();

            string rawTour = System.IO.Path.GetFullPath(ViralVideographerEngine.GetRawTourDir());
            if (!string.Equals(proj.FolderPath, rawTour, StringComparison.OrdinalIgnoreCase))
            {
                _customProjectFolders.Remove(proj.FolderPath);
                _selectedFolderPaths.Remove(proj.FolderPath);
                _projects.Remove(proj);
            }

            RenderProjectsUI();

            foreach (var p in pathsToDelete)
            {
                _ = DeleteFileWithRetryAsync(p);
            }
        }

        private async Task DeleteFileWithRetryAsync(string filePath)
        {
            string full = System.IO.Path.GetFullPath(filePath);
            ViralVideographerEngine.InvalidateClipCache(full);

            if (string.Equals(_currentVideoPath, full, StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    PreviewMediaElement.Stop();
                    PreviewMediaElement.Source = null;
                    _currentVideoPath = "";
                }
                catch { }
            }

            for (int attempt = 0; attempt < 12; attempt++)
            {
                try
                {
                    if (!File.Exists(full))
                        break;
                    File.SetAttributes(full, FileAttributes.Normal);
                    File.Delete(full);
                    if (!File.Exists(full))
                        break;
                }
                catch
                {
                    await Task.Delay(150);
                }
            }

            if (!File.Exists(full))
            {
                lock (_pendingDeletedPaths)
                {
                    _pendingDeletedPaths.Remove(full);
                }
            }
        }

        private async void AddProjectFolder_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFolderDialog
            {
                Title = "Projektordner mit Rohvideos auswählen",
                Multiselect = false
            };
            if (dlg.ShowDialog() == true && !string.IsNullOrWhiteSpace(dlg.FolderName))
            {
                string full = System.IO.Path.GetFullPath(dlg.FolderName);
                _customProjectFolders.Add(full);
                _selectedFolderPaths.Clear();
                _selectedFolderPaths.Add(full);
                await ReloadProjectsAsync();
            }
        }

        private async void AddRawFiles_Click(object sender, RoutedEventArgs e)
        {
            string targetDir = GetPrimarySelectedFolderPath();
            await PickAndImportVideosToFolderAsync(targetDir);
        }

        private async Task PickAndImportVideosToFolderAsync(string targetFolder)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Rohvideos auswählen",
                Filter = "Videodateien (*.mp4;*.mov;*.mkv;*.webm;*.avi)|*.mp4;*.mov;*.mkv;*.webm;*.avi",
                Multiselect = true
            };
            if (dlg.ShowDialog() == true && dlg.FileNames.Length > 0)
            {
                await ImportFilesToFolderAsync(dlg.FileNames, targetFolder);
            }
        }

        private async Task ImportFilesToFolderAsync(IEnumerable<string> paths, string targetFolder)
        {
            Directory.CreateDirectory(targetFolder);
            var itemList = paths.Where(p => !string.IsNullOrWhiteSpace(p)).ToList();
            if (itemList.Count == 0) return;

            var videoFilesToCopy = new List<string>();
            foreach (var item in itemList)
            {
                if (Directory.Exists(item))
                {
                    string fullDir = System.IO.Path.GetFullPath(item);
                    _customProjectFolders.Add(fullDir);
                    _selectedFolderPaths.Clear();
                    _selectedFolderPaths.Add(fullDir);
                }
                else if (File.Exists(item) && ViralVideographerEngine.AllowedVideoExtensions.Contains(System.IO.Path.GetExtension(item)))
                {
                    videoFilesToCopy.Add(item);
                }
            }

            if (videoFilesToCopy.Count > 0)
            {
                RenderProgressBorder.Visibility = Visibility.Visible;
                RenderStatusTxt.Text = $"Importiere {videoFilesToCopy.Count} Video(s)...";
                RenderProgressBar.Value = 50;

                await Task.Run(() =>
                {
                    foreach (var src in videoFilesToCopy)
                    {
                        try
                        {
                            string dest = System.IO.Path.Combine(targetFolder, System.IO.Path.GetFileName(src));
                            string fullDest = System.IO.Path.GetFullPath(dest);
                            lock (_pendingDeletedPaths)
                            {
                                _pendingDeletedPaths.Remove(fullDest);
                            }
                            ViralVideographerEngine.InvalidateClipCache(fullDest);

                            if (!string.Equals(System.IO.Path.GetFullPath(src), fullDest, StringComparison.OrdinalIgnoreCase))
                            {
                                File.Copy(src, dest, true);
                            }
                        }
                        catch { }
                    }
                });

                RenderProgressBorder.Visibility = Visibility.Collapsed;
            }

            await ReloadProjectsAsync();
        }

        private async void RefreshRawClips_Click(object sender, RoutedEventArgs e)
        {
            await ReloadProjectsAsync();
        }

        private void ProjectsTile_DragEnter(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && ProjectsTileBorder != null)
            {
                ProjectsTileBorder.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"));
                ProjectsTileBorder.BorderThickness = new Thickness(2);
            }
        }

        private void ProjectsTile_DragLeave(object sender, DragEventArgs e)
        {
            if (ProjectsTileBorder != null)
            {
                ProjectsTileBorder.BorderBrush = BrushFromHex("#232734");
                ProjectsTileBorder.BorderThickness = new Thickness(1);
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

        private async void Window_Drop(object sender, DragEventArgs e)
        {
            ProjectsTile_DragLeave(sender, e);
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] dropped && dropped.Length > 0)
            {
                e.Handled = true;
                string targetDir = GetPrimarySelectedFolderPath();
                await ImportFilesToFolderAsync(dropped, targetDir);
            }
        }

        private async void ProduceViralVideo_Click(object sender, RoutedEventArgs e)
        {
            if (_isRendering) return;

            var selectedProjects = _projects.Where(p => p.IsSelected && p.Clips.Count > 0).ToList();
            if (selectedProjects.Count == 0)
            {
                var fallback = _projects.FirstOrDefault(p => p.Clips.Count > 0);
                if (fallback != null)
                {
                    fallback.IsSelected = true;
                    _selectedFolderPaths.Add(fallback.FolderPath);
                    selectedProjects.Add(fallback);
                    RenderProjectsUI();
                }
            }

            var allSelectedClips = selectedProjects.SelectMany(p => p.Clips).ToList();
            if (allSelectedClips.Count == 0)
            {
                RenderProgressBorder.Visibility = Visibility.Visible;
                RenderProgressBar.Value = 0;
                RenderStatusTxt.Text = "Bitte zuerst einen Projektordner mit Rohvideos auswählen.";
                return;
            }

            string projectName = selectedProjects.Count == 1 ? selectedProjects[0].Name : "Kombi";

            _isRendering = true;
            ProduceViralVideoBtn.IsEnabled = false;
            RenderProgressBorder.Visibility = Visibility.Visible;
            RenderProgressBar.Value = 10;
            RenderStatusTxt.Text = $"Verarbeite {allSelectedClips.Count} Videos...";

            var opts = new VideographerJobOptions
            {
                ProjectName = projectName,
                SaveToDesktop = true,
                RecipeId = "auto_videographer",
                TransitionMode = "LightWipeZoom",
                EnableHookTextOverlay = false,
                EnableGoldParticlesVfx = true,
                EnableViralSoundEffects = true,
                EnableAsmrBoost = true,
                EnableBeatPulseBed = true,
                TargetFps = 60
            };

            var progress = new Progress<(int Percent, string Status)>(p =>
            {
                RenderProgressBar.Value = p.Percent;
                RenderStatusTxt.Text = p.Status;
            });

            var result = await ViralVideographerEngine.ProduceViralVideoAsync(allSelectedClips, opts, progress);

            _isRendering = false;
            ProduceViralVideoBtn.IsEnabled = true;

            if (result.Success)
            {
                LoadVideoIntoPreviewPlayer(result.OutputVideoPath);
                RenderProgressBar.Value = 100;
                RenderStatusTxt.Text = $"✓ Auf Desktop gespeichert: {System.IO.Path.GetFileName(result.OutputVideoPath)}";
            }
            else
            {
                RenderStatusTxt.Text = $"⚠ {result.ErrorMessage}";
            }
        }

        private void LoadLatestVideoIntoPreview()
        {
            try
            {
                string desktopDir = ViralVideographerEngine.GetDesktopDir();
                var desktopVids = Directory.GetFiles(desktopDir, "Virales_Video_*.mp4")
                    .Select(f => new FileInfo(f))
                    .OrderByDescending(f => f.LastWriteTimeUtc)
                    .ToList();

                if (desktopVids.Count > 0)
                {
                    LoadVideoIntoPreviewPlayer(desktopVids[0].FullName);
                    return;
                }

                string outDir = ViralVideographerEngine.GetOutputVideosDir();
                var outVids = Directory.GetFiles(outDir, "*.mp4")
                    .Select(f => new FileInfo(f))
                    .OrderByDescending(f => f.LastWriteTimeUtc)
                    .ToList();

                if (outVids.Count > 0)
                {
                    LoadVideoIntoPreviewPlayer(outVids[0].FullName);
                }
            }
            catch { }
        }

        private void LoadVideoIntoPreviewPlayer(string mp4Path)
        {
            if (string.IsNullOrWhiteSpace(mp4Path) || !File.Exists(mp4Path)) return;
            _currentVideoPath = mp4Path;

            try
            {
                PlayerEmptyOverlay.Visibility = Visibility.Collapsed;
                PreviewMediaElement.Stop();
                PreviewMediaElement.Source = new Uri(mp4Path, UriKind.Absolute);
                PreviewMediaElement.Play();
                _isPlaying = true;
                PlayPauseBtn.Content = "⏸ Pause";
            }
            catch { }
        }

        private void PreviewMediaElement_MediaEnded(object sender, RoutedEventArgs e)
        {
            try
            {
                PreviewMediaElement.Position = TimeSpan.Zero;
                PreviewMediaElement.Play();
            }
            catch { }
        }

        private void PlayPause_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_currentVideoPath)) return;
            try
            {
                if (_isPlaying)
                {
                    PreviewMediaElement.Pause();
                    _isPlaying = false;
                    PlayPauseBtn.Content = "▶ Play";
                }
                else
                {
                    PreviewMediaElement.Play();
                    _isPlaying = true;
                    PlayPauseBtn.Content = "⏸ Pause";
                }
            }
            catch { }
        }

        private void OpenFolderInExplorer(string folderPath)
        {
            try
            {
                if (Directory.Exists(folderPath))
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = "explorer.exe",
                        Arguments = $"\"{folderPath}\"",
                        UseShellExecute = true
                    });
                }
            }
            catch { }
        }

        private void RevealCurrentVideo_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                if (!string.IsNullOrWhiteSpace(_currentVideoPath) && File.Exists(_currentVideoPath))
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = "explorer.exe",
                        Arguments = $"/select,\"{_currentVideoPath}\"",
                        UseShellExecute = true
                    });
                }
                else
                {
                    OpenFolderInExplorer(ViralVideographerEngine.GetDesktopDir());
                }
            }
            catch { }
        }
    }
}

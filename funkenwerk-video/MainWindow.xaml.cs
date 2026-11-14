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
        public bool IsCustomFolder { get; set; }
        public bool IsSelected { get; set; }
        public List<RawClipInfo> Clips { get; set; } = new();
    }

    public partial class MainWindow : Window
    {
        private readonly bool _disableAutoLoad;
        private bool _isDarkMode = true;
        private bool _isPlaying = false;
        private bool _isRendering = false;
        private bool _isSyncingRecipeUi = false;
        private string _currentVideoPath = "";

        private readonly List<ProjectFolderItem> _projects = new();
        private readonly HashSet<string> _customProjectFolders = new(StringComparer.OrdinalIgnoreCase);
        private readonly HashSet<string> _untrackedSubfolders = new(StringComparer.OrdinalIgnoreCase);
        private readonly HashSet<string> _selectedFolderPaths = new(StringComparer.OrdinalIgnoreCase);
        private readonly HashSet<string> _pendingDeletedPaths = new(StringComparer.OrdinalIgnoreCase);
        private FileSystemWatcher? _rawTourWatcher;
        private bool _isWatcherDisposed = false;
        private readonly Dictionary<(DependencyObject Obj, DependencyProperty Prop), string> _originalDarkBrushes = new();

        private static readonly string[] TransitionModes =
        {
            "LightWipeZoom",
            "GoldenFlash",
            "WhipZoom",
            "SeamlessLoop"
        };

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

        public IReadOnlyList<ProjectFolderItem> Projects => _projects;
        public IReadOnlyCollection<string> CustomProjectFolders => _customProjectFolders;
        public bool IsDarkMode => _isDarkMode;
        public bool IsWatcherDisposed => _isWatcherDisposed;

        public MainWindow() : this(disableAutoLoad: false)
        {
        }

        public MainWindow(bool disableAutoLoad)
        {
            _disableAutoLoad = disableAutoLoad;
            InitializeComponent();
            PopulateRegieControls();
            Loaded += MainWindow_Loaded;
        }

        public void InitializeForHeadlessTest()
        {
            CaptureOriginalDarkBrushes(this);
            ApplyThemeMode(_isDarkMode);
            StartRawTourWatcher();
        }

        private void PopulateRegieControls()
        {
            _isSyncingRecipeUi = true;
            try
            {
                RecipeComboBox.Items.Clear();
                foreach (var recipe in ViralVideographerEngine.ViralRecipes)
                {
                    RecipeComboBox.Items.Add(new ComboBoxItem
                    {
                        Content = recipe.Name,
                        Tag = recipe.Id
                    });
                }

                TransitionComboBox.Items.Clear();
                foreach (var mode in TransitionModes)
                {
                    TransitionComboBox.Items.Add(new ComboBoxItem
                    {
                        Content = mode,
                        Tag = mode
                    });
                }

                if (RecipeComboBox.Items.Count > 0)
                    RecipeComboBox.SelectedIndex = 0;
                if (TransitionComboBox.Items.Count > 0)
                    TransitionComboBox.SelectedIndex = 0;

                if (ViralVideographerEngine.ViralRecipes.Count > 0)
                {
                    HookTextBox.Text = ViralVideographerEngine.ViralRecipes[0].DefaultHookText;
                }
            }
            finally
            {
                _isSyncingRecipeUi = false;
            }
        }

        private void RecipeComboBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_isSyncingRecipeUi) return;
            string selectedId = GetSelectedRecipeId();
            var recipe = ViralVideographerEngine.ViralRecipes.FirstOrDefault(
                r => string.Equals(r.Id, selectedId, StringComparison.OrdinalIgnoreCase));
            if (recipe == null) return;

            _isSyncingRecipeUi = true;
            try
            {
                for (int i = 0; i < TransitionComboBox.Items.Count; i++)
                {
                    if (TransitionComboBox.Items[i] is ComboBoxItem cbi &&
                        string.Equals(cbi.Tag as string, recipe.DefaultTransitionMode, StringComparison.OrdinalIgnoreCase))
                    {
                        TransitionComboBox.SelectedIndex = i;
                        break;
                    }
                }

                if (!string.IsNullOrWhiteSpace(recipe.DefaultHookText))
                {
                    HookTextBox.Text = recipe.DefaultHookText;
                }
            }
            finally
            {
                _isSyncingRecipeUi = false;
            }
        }

        private void HookOverlayCheckBox_Changed(object sender, RoutedEventArgs e)
        {
            if (HookTextBox == null || HookOverlayCheckBox == null) return;
            HookTextBox.Visibility = HookOverlayCheckBox.IsChecked == true ? Visibility.Visible : Visibility.Collapsed;
        }

        public string GetSelectedRecipeId()
        {
            if (RecipeComboBox.SelectedItem is ComboBoxItem item && item.Tag is string id && !string.IsNullOrWhiteSpace(id))
                return id;
            return "auto_videographer";
        }

        public string GetSelectedTransitionMode()
        {
            if (TransitionComboBox.SelectedItem is ComboBoxItem item && item.Tag is string mode && !string.IsNullOrWhiteSpace(mode))
                return mode;
            return "LightWipeZoom";
        }

        public VideographerJobOptions BuildJobOptionsFromUi(string projectName, bool saveToDesktop)
        {
            bool hookEnabled = HookOverlayCheckBox.IsChecked == true;
            string rawHook = HookTextBox.Text ?? "";
            string cleanHook = ViralVideographerEngine.SanitizeSeelenfunkeText(rawHook);

            return new VideographerJobOptions
            {
                ProjectName = projectName,
                SaveToDesktop = saveToDesktop,
                RecipeId = GetSelectedRecipeId(),
                TransitionMode = GetSelectedTransitionMode(),
                EnableHookTextOverlay = hookEnabled && !string.IsNullOrWhiteSpace(cleanHook),
                HookText = cleanHook,
                EnableGoldParticlesVfx = true,
                EnableViralSoundEffects = true,
                EnableAsmrBoost = true,
                EnableBeatPulseBed = true,
                TargetFps = 60
            };
        }

        private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            CaptureOriginalDarkBrushes(this);
            ApplyThemeMode(_isDarkMode);
            if (_disableAutoLoad) return;

            StartRawTourWatcher();
            await ReloadProjectsAsync();
            LoadLatestVideoIntoPreview();
        }

        private void Window_Closed(object? sender, EventArgs e)
        {
            DisposeWatcher();
            try
            {
                PreviewMediaElement.Stop();
                PreviewMediaElement.Close();
                PreviewMediaElement.Source = null;
            }
            catch { }
        }

        public void DisposeWatcher()
        {
            if (_rawTourWatcher != null)
            {
                try
                {
                    _rawTourWatcher.EnableRaisingEvents = false;
                    _rawTourWatcher.Dispose();
                }
                catch { }
                _rawTourWatcher = null;
            }
            _isWatcherDisposed = true;
        }

        private void StartRawTourWatcher()
        {
            DisposeWatcher();
            _isWatcherDisposed = false;

            try
            {
                string rawDir = ViralVideographerEngine.GetRawTourDir();
                _rawTourWatcher = new FileSystemWatcher(rawDir)
                {
                    NotifyFilter = NotifyFilters.FileName | NotifyFilters.Size | NotifyFilters.LastWrite,
                    EnableRaisingEvents = true
                };

                async Task HandleFsChangeAsync(string? fullPath)
                {
                    if (!string.IsNullOrWhiteSpace(fullPath) &&
                        ViralVideographerEngine.AllowedVideoExtensions.Contains(Path.GetExtension(fullPath)))
                    {
                        bool ready = await ViralVideographerEngine.WaitForFileReadyAsync(fullPath, maxWaitMs: 4000);
                        if (!ready) return;
                        ViralVideographerEngine.InvalidateClipCache(fullPath);
                    }

                    await Dispatcher.InvokeAsync(async () =>
                    {
                        if (!_isRendering && !_isWatcherDisposed)
                            await ReloadProjectsAsync();
                    });
                }

                _rawTourWatcher.Created += (_, ev) => _ = HandleFsChangeAsync(ev.FullPath);
                _rawTourWatcher.Deleted += (_, ev) =>
                {
                    ViralVideographerEngine.InvalidateClipCache(ev.FullPath);
                    _ = HandleFsChangeAsync(null);
                };
                _rawTourWatcher.Renamed += (_, ev) =>
                {
                    ViralVideographerEngine.InvalidateClipCache(ev.OldFullPath);
                    _ = HandleFsChangeAsync(ev.FullPath);
                };
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

        public void ApplyThemeMode(bool isDark)
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

            // Theme ComboBox controls & popup items for 100% Light/Dark legibility
            var comboBg = BrushFromHex("#181B24");
            var comboFg = BrushFromHex("#F9FAFB");
            var comboBorder = BrushFromHex("#262A36");
            foreach (var cb in new[] { RecipeComboBox, TransitionComboBox })
            {
                if (cb == null) continue;
                cb.Background = comboBg;
                cb.Foreground = comboFg;
                cb.BorderBrush = comboBorder;
                cb.Resources[SystemColors.WindowBrushKey] = comboBg;
                cb.Resources[SystemColors.ControlTextBrushKey] = comboFg;
                foreach (var item in cb.Items.OfType<ComboBoxItem>())
                {
                    item.Background = comboBg;
                    item.Foreground = comboFg;
                }
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

        public void RegisterCustomProjectFolder(string folderPath)
        {
            if (string.IsNullOrWhiteSpace(folderPath)) return;
            string full = Path.GetFullPath(folderPath);
            _untrackedSubfolders.Remove(full);
            _customProjectFolders.Add(full);
            _selectedFolderPaths.Clear();
            _selectedFolderPaths.Add(full);
        }

        public async Task ReloadProjectsAsync()
        {
            var folderCandidates = new List<string>();

            // 1. Default Rohmaterial_Tour folder
            string rawTourDir = Path.GetFullPath(ViralVideographerEngine.GetRawTourDir());
            folderCandidates.Add(rawTourDir);

            // 2. Subfolders inside 'Projekte'
            string projectsRoot = Path.GetFullPath(ViralVideographerEngine.GetProjectsDir());
            if (Directory.Exists(projectsRoot))
            {
                foreach (var dir in Directory.GetDirectories(projectsRoot).OrderBy(d => d, StringComparer.OrdinalIgnoreCase))
                {
                    string fullSub = Path.GetFullPath(dir);
                    if (!_untrackedSubfolders.Contains(fullSub))
                        folderCandidates.Add(fullSub);
                }
            }

            // 3. Any custom added project folders
            foreach (var customDir in _customProjectFolders)
            {
                if (Directory.Exists(customDir))
                    folderCandidates.Add(Path.GetFullPath(customDir));
            }

            var distinctFolders = folderCandidates
                .Select(Path.GetFullPath)
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .ToList();

            var newProjects = new List<ProjectFolderItem>();
            foreach (var folder in distinctFolders)
            {
                if (!Directory.Exists(folder)) continue;

                bool isManagedRawTour = ViralVideographerEngine.IsDirectoryManagedRawTour(folder);
                bool isCustom = !isManagedRawTour;

                var files = Directory.GetFiles(folder)
                    .Where(f => ViralVideographerEngine.AllowedVideoExtensions.Contains(Path.GetExtension(f)))
                    .Where(f => !_pendingDeletedPaths.Contains(Path.GetFullPath(f)))
                    .OrderBy(f => f, StringComparer.OrdinalIgnoreCase)
                    .ToList();

                var clips = new List<RawClipInfo>();
                foreach (var f in files)
                {
                    if (_pendingDeletedPaths.Contains(Path.GetFullPath(f))) continue;
                    var info = await ViralVideographerEngine.AnalyzeRawClipAsync(f);
                    if (info != null && !_pendingDeletedPaths.Contains(info.FilePath))
                        clips.Add(info);
                }

                string folderName = new DirectoryInfo(folder).Name;
                newProjects.Add(new ProjectFolderItem
                {
                    Name = folderName,
                    FolderPath = folder,
                    IsCustomFolder = isCustom,
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
                chk.Click += (_, e) =>
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
                addVidBtn.Click += async (_, e) =>
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
                openDirBtn.Click += (_, e) =>
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
                    ToolTip = proj.IsCustomFolder ? "Ordner aus Liste entfernen" : "Rohmaterial leeren"
                };
                clearBtn.Click += (_, e) =>
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
                            ToolTip = proj.IsCustomFolder ? "Aus Auswahl ausblenden" : "Rohvideo löschen"
                        };
                        delClipBtn.Click += (_, e) =>
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
            string full = Path.GetFullPath(folderPath);
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

        public void RemoveSingleClipInstant(ProjectFolderItem proj, string clipFilePath, Func<string, bool>? confirmCallback = null)
        {
            if (proj == null || string.IsNullOrWhiteSpace(clipFilePath)) return;
            string full = Path.GetFullPath(clipFilePath);

            // External / custom folders: NEVER delete user files on disk, only remove from session list
            if (proj.IsCustomFolder || !ViralVideographerEngine.IsPathInsideManagedRawTour(full))
            {
                proj.Clips.RemoveAll(c => string.Equals(c.FilePath, full, StringComparison.OrdinalIgnoreCase));
                RenderProjectsUI();
                return;
            }

            string prompt = $"Soll die Rohvideodatei '{Path.GetFileName(full)}' aus 'Rohmaterial_Tour' gelöscht werden?";
            bool confirmed = confirmCallback != null
                ? confirmCallback(prompt)
                : MessageBox.Show(this, prompt, "Rohvideo löschen", MessageBoxButton.YesNo, MessageBoxImage.Warning) == MessageBoxResult.Yes;

            if (!confirmed) return;

            lock (_pendingDeletedPaths)
            {
                _pendingDeletedPaths.Add(full);
            }
            proj.Clips.RemoveAll(c => string.Equals(c.FilePath, full, StringComparison.OrdinalIgnoreCase));
            RenderProjectsUI();
            _ = DeleteFileWithRetryAsync(full);
        }

        public void ClearOrRemoveProjectFolder(ProjectFolderItem proj, Func<string, bool>? confirmCallback = null)
        {
            if (proj == null) return;
            string fullFolder = Path.GetFullPath(proj.FolderPath);

            // CRITICAL SECURITY GUARANTEE:
            // Never delete ANY files when proj.IsCustomFolder is true OR when folder is outside managed Rohmaterial_Tour!
            // Removing an external/custom folder ONLY untracks it from UI state!
            if (proj.IsCustomFolder || !ViralVideographerEngine.IsDirectoryManagedRawTour(fullFolder))
            {
                _customProjectFolders.Remove(fullFolder);
                _selectedFolderPaths.Remove(fullFolder);
                _untrackedSubfolders.Add(fullFolder);
                _projects.Remove(proj);
                RenderProjectsUI();
                return;
            }

            // Internal managed Rohmaterial_Tour folder: require explicit user confirmation before deleting raw clips
            var safeInternalPaths = proj.Clips
                .Select(c => c.FilePath)
                .Where(ViralVideographerEngine.IsPathInsideManagedRawTour)
                .Select(Path.GetFullPath)
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .ToList();

            if (safeInternalPaths.Count == 0)
                return;

            string confirmMsg = $"Sollen alle {safeInternalPaths.Count} Rohvideos im internen Ordner 'Rohmaterial_Tour' unwiderruflich gelöscht werden?";
            bool confirmed = confirmCallback != null
                ? confirmCallback(confirmMsg)
                : MessageBox.Show(this, confirmMsg, "Rohmaterial_Tour leeren", MessageBoxButton.YesNo, MessageBoxImage.Warning) == MessageBoxResult.Yes;

            if (!confirmed)
                return;

            foreach (var p in safeInternalPaths)
            {
                lock (_pendingDeletedPaths)
                {
                    _pendingDeletedPaths.Add(p);
                }
            }

            proj.Clips.Clear();
            RenderProjectsUI();

            foreach (var p in safeInternalPaths)
            {
                _ = DeleteFileWithRetryAsync(p);
            }
        }

        private async Task DeleteFileWithRetryAsync(string filePath)
        {
            string full = Path.GetFullPath(filePath);

            // Defense-in-depth containment check: NEVER delete anything outside managed Rohmaterial_Tour
            if (!ViralVideographerEngine.IsPathInsideManagedRawTour(full))
                return;

            ViralVideographerEngine.InvalidateClipCache(full);

            if (string.Equals(_currentVideoPath, full, StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    PreviewMediaElement.Stop();
                    PreviewMediaElement.Close();
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
                    if (!ViralVideographerEngine.IsPathInsideManagedRawTour(full))
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
                RegisterCustomProjectFolder(dlg.FolderName);
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
                Filter = "Videodateien (*.mp4;*.mov;*.mkv;*.webm;*.avi;*.m4v)|*.mp4;*.mov;*.mkv;*.webm;*.avi;*.m4v",
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
                    RegisterCustomProjectFolder(item);
                }
                else if (ViralVideographerEngine.ValidateSafeVideoFilePath(item, out string validVideo, out _))
                {
                    videoFilesToCopy.Add(validVideo);
                }
            }

            if (videoFilesToCopy.Count > 0)
            {
                RenderProgressBorder.Visibility = Visibility.Visible;
                RenderStatusTxt.Text = $"Importiere {videoFilesToCopy.Count} Video(s)...";
                RenderProgressBar.Value = 50;

                string fullTargetFolder = Path.GetFullPath(targetFolder)
                    .TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);

                await Task.Run(() =>
                {
                    foreach (var src in videoFilesToCopy)
                    {
                        try
                        {
                            string safeFileName = Path.GetFileName(src);
                            if (string.IsNullOrWhiteSpace(safeFileName) || safeFileName.Contains(':')) continue;

                            string fullDest = Path.GetFullPath(Path.Combine(fullTargetFolder, safeFileName));
                            if (!fullDest.StartsWith(fullTargetFolder + Path.DirectorySeparatorChar, StringComparison.OrdinalIgnoreCase))
                                continue;

                            lock (_pendingDeletedPaths)
                            {
                                _pendingDeletedPaths.Remove(fullDest);
                            }
                            ViralVideographerEngine.InvalidateClipCache(fullDest);

                            if (!string.Equals(Path.GetFullPath(src), fullDest, StringComparison.OrdinalIgnoreCase))
                            {
                                File.Copy(src, fullDest, true);
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
            var opts = BuildJobOptionsFromUi(projectName, saveToDesktop: true);

            _isRendering = true;
            ProduceViralVideoBtn.IsEnabled = false;
            RenderProgressBorder.Visibility = Visibility.Visible;
            RenderProgressBar.Value = 10;
            RenderStatusTxt.Text = $"Verarbeite {allSelectedClips.Count} Videos...";

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
                RenderStatusTxt.Text = $"✓ Auf Desktop gespeichert: {Path.GetFileName(result.OutputVideoPath)}";
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
                if (Directory.Exists(desktopDir))
                {
                    var desktopVids = Directory.GetFiles(desktopDir, "Virales_Video_*.mp4")
                        .Select(f => new FileInfo(f))
                        .OrderByDescending(f => f.LastWriteTimeUtc)
                        .ToList();

                    if (desktopVids.Count > 0)
                    {
                        LoadVideoIntoPreviewPlayer(desktopVids[0].FullName);
                        return;
                    }
                }

                string outDir = ViralVideographerEngine.GetOutputVideosDir();
                if (Directory.Exists(outDir))
                {
                    var outVids = Directory.GetFiles(outDir, "*.mp4")
                        .Select(f => new FileInfo(f))
                        .OrderByDescending(f => f.LastWriteTimeUtc)
                        .ToList();

                    if (outVids.Count > 0)
                    {
                        LoadVideoIntoPreviewPlayer(outVids[0].FullName);
                    }
                }
            }
            catch { }
        }

        private void LoadVideoIntoPreviewPlayer(string mp4Path)
        {
            if (!ViralVideographerEngine.ValidateSafeVideoFilePath(mp4Path, out string fullMp4, out _))
                return;

            _currentVideoPath = fullMp4;

            try
            {
                PlayerEmptyOverlay.Visibility = Visibility.Collapsed;
                PreviewMediaElement.Stop();
                PreviewMediaElement.Source = new Uri(fullMp4, UriKind.Absolute);
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

        private void PreviewMediaElement_MediaFailed(object? sender, ExceptionRoutedEventArgs e)
        {
            _isPlaying = false;
            PlayPauseBtn.Content = "▶ Play";
            PlayerEmptyOverlay.Visibility = Visibility.Visible;
            PlayerEmptyStatusTxt.Text = $"Wiedergabefehler: {e.ErrorException?.Message ?? "Format nicht unterstützt"}";
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

        public static ProcessStartInfo BuildExplorerOpenFolderStartInfo(string folderPath)
        {
            string full = Path.GetFullPath(folderPath);
            string explorerExe = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows), "explorer.exe");
            var psi = new ProcessStartInfo
            {
                FileName = explorerExe,
                UseShellExecute = false
            };
            psi.ArgumentList.Add(full);
            return psi;
        }

        public static ProcessStartInfo BuildExplorerSelectFileStartInfo(string filePath)
        {
            string full = Path.GetFullPath(filePath);
            string explorerExe = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Windows), "explorer.exe");
            var psi = new ProcessStartInfo
            {
                FileName = explorerExe,
                UseShellExecute = false
            };
            psi.ArgumentList.Add("/select,");
            psi.ArgumentList.Add(full);
            return psi;
        }

        private void OpenFolderInExplorer(string folderPath)
        {
            try
            {
                if (Directory.Exists(folderPath))
                {
                    Process.Start(BuildExplorerOpenFolderStartInfo(folderPath));
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
                    Process.Start(BuildExplorerSelectFileStartInfo(_currentVideoPath));
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

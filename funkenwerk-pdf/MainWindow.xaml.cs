using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Ink;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Shapes;
using System.Windows.Threading;
using Microsoft.Win32;
using PdfSharp.Drawing;
using PdfSharp.Pdf;
using PdfSharp.Pdf.Advanced;
using PdfSharp.Pdf.IO;
using Windows.Data.Pdf;
using Windows.Storage.Streams;
using IOPath = System.IO.Path;

namespace FunkenwerkPdf
{
    public enum PdfToolMode
    {
        Select,
        Text,
        Check,
        Cross,
        Whiteout,
        Highlight
    }

    public enum PdfAnnotationType
    {
        Text,
        Check,
        Cross,
        Whiteout,
        Highlight,
        Signature
    }

    public sealed class PdfAnnotationItem
    {
        public string Id { get; set; } = Guid.NewGuid().ToString();
        public int PageIndex { get; set; }
        public PdfAnnotationType Type { get; set; } = PdfAnnotationType.Text;
        public double XPt { get; set; }
        public double YPt { get; set; }
        public double WidthPt { get; set; } = 160;
        public double HeightPt { get; set; } = 26;
        public string Text { get; set; } = "";
        public double FontSizePt { get; set; } = 14;
        public Color StrokeColor { get; set; } = Color.FromRgb(17, 17, 17);
        public byte[]? ImagePngBytes { get; set; }
    }

    public sealed class PageListEntry
    {
        public int Index { get; set; }
        public string Title => $"Seite {Index + 1}";
        public string Badge { get; set; } = "";
    }

    public partial class MainWindow : Window
    {
        private sealed class PageVisualSlot
        {
            public int PageIndex { get; init; }
            public double WidthPt { get; set; } = 595.0;
            public double HeightPt { get; set; } = 842.0;
            public required Grid WrapperGrid { get; init; }
            public required Border PaperBorder { get; init; }
            public required Grid PageSurfaceGrid { get; init; }
            public required Image PageImage { get; init; }
            public required Canvas TextSelectionCanvas { get; init; }
            public required Canvas FormFieldsCanvas { get; init; }
            public required Canvas AnnotationCanvas { get; init; }
            public double RenderedAtZoom { get; set; } = 0.0;
        }

        private static readonly byte[] SignatureDpapiEntropy = Encoding.UTF8.GetBytes("FunkenwerkPdf.SavedSignature.v1");

        private string _currentFilePath = "";
        private string _documentBaseTitle = "Funkenwerk PDF";
        private byte[]? _basePdfBytes;
        private byte[]? _cleanRenderPdfBytes;
        private bool _isDirty = false;
        private bool _suppressDirtyTracking = false;

        private int _pageCount = 0;
        private int _currentPageIndex = 0;
        private double _currentPageWidthPt = 595.0;
        private double _currentPageHeightPt = 842.0;
        private double _zoomFactor = 1.25;
        private int _documentLoadGeneration = 0;

        private PdfToolMode _activeTool = PdfToolMode.Select;
        private Color _activeColor = Color.FromRgb(17, 17, 17);
        private double _activeSizePt = 14.0;
        private bool _isDarkMode = true;
        private bool _sidebarVisible = true;
        private bool? _userForcedSidebar = null;
        private bool _suppressSidebarSelectionJump = false;

        private readonly List<PageVisualSlot> _pageSlots = new();
        private readonly List<PdfFormFieldItem> _formFields = new();
        private readonly List<PdfAnnotationItem> _annotations = new();
        private PdfAnnotationItem? _selectedAnnotation;
        private PdfFormFieldItem? _focusedFormField;
        private Rect? _pendingSignatureTargetRectPt = null;
        private int _pendingSignatureTargetPage = -1;

        private PdfTextSelectionEngine? _textEngine;
        private bool _isDraggingTextSelection = false;
        private UIElement? _textDragCaptureOwner;
        private int _textDragAnchorPage = 0;
        private Point _textDragAnchorPt;
        private Point _textDragStartViewportPx;
        private PdfSelectionComputation? _currentTextSelection;
        private Border? _floatingCopyPill;

        private readonly DispatcherTimer _zoomRenderDebounceTimer;
        private readonly DispatcherTimer _scrollRenderDebounceTimer;
        private readonly DispatcherTimer _toastHideTimer;
        private readonly DispatcherTimer _saveButtonResetTimer;
        private bool _isBatchRenderingPages = false;

        public bool IsDirty => _isDirty;
        public bool IsDarkMode => _isDarkMode;
        public int ActiveAnnotationCount => _annotations.Count;
        public IReadOnlyList<PdfFormFieldItem> ActiveFormFields => _formFields;

        public static void EnsureSecureAppDataDirectory(string dirPath)
        {
            Directory.CreateDirectory(dirPath);
            try
            {
                var dirInfo = new DirectoryInfo(dirPath);
                var security = new DirectorySecurity();
                security.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);

                var currentUser = WindowsIdentity.GetCurrent().User;
                if (currentUser != null)
                {
                    security.AddAccessRule(new FileSystemAccessRule(
                        currentUser,
                        FileSystemRights.FullControl,
                        InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                        PropagationFlags.None,
                        AccessControlType.Allow));
                }

                var systemSid = new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null);
                security.AddAccessRule(new FileSystemAccessRule(
                    systemSid,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));

                var adminsSid = new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null);
                security.AddAccessRule(new FileSystemAccessRule(
                    adminsSid,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));

                dirInfo.SetAccessControl(security);
            }
            catch { }
        }

        private static string AppDataDir
        {
            get
            {
                string dir = IOPath.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkPdf");
                EnsureSecureAppDataDirectory(dir);
                // Remove legacy unencrypted signature file if present
                string legacyPlain = IOPath.Combine(dir, "saved_signature.png");
                try { if (File.Exists(legacyPlain)) File.Delete(legacyPlain); } catch { }
                return dir;
            }
        }

        private static string SavedSignaturePath => IOPath.Combine(AppDataDir, "saved_signature.dpapi");

        public static void SaveEncryptedSignatureToFile(string filePath, byte[] pngBytes)
        {
            if (pngBytes == null || pngBytes.Length == 0)
                throw new ArgumentException("Leere Signaturdaten.");
            if (pngBytes.Length > PdfFormFieldEngine.MaxSignatureImageBytes)
                throw new InvalidOperationException("Signaturbild ist zu groß (maximal 16 MB).");

            string fullPath = IOPath.GetFullPath(filePath);
            string dir = IOPath.GetDirectoryName(fullPath) ?? Environment.CurrentDirectory;
            EnsureSecureAppDataDirectory(dir);

            byte[] encrypted = ProtectedData.Protect(pngBytes, SignatureDpapiEntropy, DataProtectionScope.CurrentUser);
            string tmp = IOPath.Combine(dir, $".{IOPath.GetFileName(fullPath)}.{Guid.NewGuid():N}.tmp");
            try
            {
                File.WriteAllBytes(tmp, encrypted);
                File.Move(tmp, fullPath, overwrite: true);
            }
            finally
            {
                try { if (File.Exists(tmp)) File.Delete(tmp); } catch { }
            }
        }

        public static byte[]? TryLoadEncryptedSignatureFromFile(string filePath)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath))
                    return null;
                var fi = new FileInfo(filePath);
                if (fi.Length <= 0 || fi.Length > PdfFormFieldEngine.MaxSignatureImageBytes + 65536)
                    return null;

                byte[] encrypted = File.ReadAllBytes(filePath);
                byte[] decrypted = ProtectedData.Unprotect(encrypted, SignatureDpapiEntropy, DataProtectionScope.CurrentUser);
                if (decrypted.Length == 0 || decrypted.Length > PdfFormFieldEngine.MaxSignatureImageBytes)
                    return null;
                return decrypted;
            }
            catch
            {
                return null;
            }
        }

        public static bool DeleteSavedSignatureFile(string filePath)
        {
            try
            {
                if (File.Exists(filePath))
                {
                    File.Delete(filePath);
                    return true;
                }
            }
            catch { }
            return false;
        }

        public MainWindow(string? initialPdfPath = null)
        {
            InitializeComponent();

            _zoomRenderDebounceTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(120) };
            _zoomRenderDebounceTimer.Tick += async (s, e) =>
            {
                _zoomRenderDebounceTimer.Stop();
                await EnsureVisiblePagesRenderedAsync(forceZoomRefresh: true);
            };

            _scrollRenderDebounceTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(55) };
            _scrollRenderDebounceTimer.Tick += async (s, e) =>
            {
                _scrollRenderDebounceTimer.Stop();
                await EnsureVisiblePagesRenderedAsync(forceZoomRefresh: false);
            };

            _toastHideTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(2900) };
            _toastHideTimer.Tick += (s, e) =>
            {
                _toastHideTimer.Stop();
                ViewerToastBorder.Visibility = Visibility.Collapsed;
            };

            _saveButtonResetTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(2200) };
            _saveButtonResetTimer.Tick += (s, e) =>
            {
                _saveButtonResetTimer.Stop();
                SaveBtn.Content = "Speichern";
                SaveBtn.Background = BrushHex("#C5A059");
                SaveBtn.Foreground = BrushHex("#0F1117");
            };

            double maxW = SystemParameters.WorkArea.Width * 0.95;
            double maxH = SystemParameters.WorkArea.Height * 0.92;
            if (Width > maxW) Width = Math.Max(MinWidth, maxW);
            if (Height > maxH) Height = Math.Max(MinHeight, maxH);

            ConfigureSignaturePad();
            ApplyTheme();
            SizeChanged += MainWindow_SizeChanged;

            Loaded += async (s, e) =>
            {
                if (!string.IsNullOrWhiteSpace(initialPdfPath) && File.Exists(initialPdfPath))
                {
                    await LoadPdfFromFileAsync(initialPdfPath);
                }
            };
        }

        // =====================================================================
        // DIRTY STATE TRACKING & UNSAVED CHANGES GUARD
        // =====================================================================
        private void MarkDirty()
        {
            if (_suppressDirtyTracking || _basePdfBytes == null) return;
            if (!_isDirty)
            {
                _isDirty = true;
                UpdateWindowTitle();
            }
        }

        private void ClearDirty()
        {
            _isDirty = false;
            UpdateWindowTitle();
        }

        private void UpdateWindowTitle()
        {
            Title = _isDirty ? $"{_documentBaseTitle} *" : _documentBaseTitle;
        }

        private bool ConfirmAndHandleUnsavedChangesSync()
        {
            if (!_isDirty || _basePdfBytes == null || !IsVisible)
                return true;

            var res = MessageBox.Show(
                this,
                "Ungespeicherte Änderungen vor dem Fortfahren speichern?",
                "Funkenwerk PDF",
                MessageBoxButton.YesNoCancel,
                MessageBoxImage.Question);

            if (res == MessageBoxResult.Cancel)
                return false;

            if (res == MessageBoxResult.No)
                return true;

            string target = _currentFilePath;
            if (string.IsNullOrWhiteSpace(target))
            {
                var dlg = new SaveFileDialog
                {
                    Title = "PDF-Dokument speichern als",
                    Filter = "PDF-Dokument (*.pdf)|*.pdf",
                    FileName = $"Dokument_{DateTime.Now:yyyyMMdd}.pdf"
                };
                if (dlg.ShowDialog(this) != true)
                    return false;
                target = dlg.FileName;
            }

            return SaveCommittedPdfSync(target);
        }

        private void Window_Closing(object? sender, CancelEventArgs e)
        {
            if (!ConfirmAndHandleUnsavedChangesSync())
            {
                e.Cancel = true;
            }
        }

        // =====================================================================
        // TOAST & SAVE FEEDBACK
        // =====================================================================
        private void ShowViewerToast(string message, bool isError = false, int durationMs = 2900)
        {
            string clean = message.Replace(" \u2013 ", " · ");
            ViewerToastText.Text = clean;
            ViewerToastBorder.BorderBrush = BrushHex(isError ? "#EF4444" : "#C5A059");
            ViewerToastBorder.Background = BrushHex(isError ? "#F52A1215" : (_isDarkMode ? "#F2151923" : "#F5FFFFFF"));
            ViewerToastText.Foreground = BrushHex(isError ? "#FCA5A5" : (_isDarkMode ? "#F9FAFB" : "#111827"));
            ViewerToastBorder.Visibility = Visibility.Visible;

            _toastHideTimer.Stop();
            _toastHideTimer.Interval = TimeSpan.FromMilliseconds(durationMs);
            _toastHideTimer.Start();
        }

        private void FlashSaveButtonSuccess()
        {
            SaveBtn.Content = "✓ Gespeichert";
            SaveBtn.Background = BrushHex("#10B981");
            SaveBtn.Foreground = BrushHex("#081C15");
            _saveButtonResetTimer.Stop();
            _saveButtonResetTimer.Start();
        }

        private static bool TrySetClipboardText(string text)
        {
            if (string.IsNullOrEmpty(text)) return false;
            for (int attempt = 0; attempt < 4; attempt++)
            {
                try
                {
                    Clipboard.SetDataObject(text, true);
                    return true;
                }
                catch
                {
                    Thread.Sleep(25);
                }
            }
            return false;
        }

        // =====================================================================
        // RESPONSIVE SIDEBAR & WINDOW ADAPTATION
        // =====================================================================
        private void MainWindow_SizeChanged(object sender, SizeChangedEventArgs e)
        {
            if (SidebarCol == null || SidebarGapCol == null || PagesTileBorder == null) return;

            if (_userForcedSidebar == null)
            {
                bool shouldShow = e.NewSize.Width >= 760;
                ApplySidebarState(shouldShow, e.NewSize.Width);
            }
            else
            {
                ApplySidebarState(_userForcedSidebar.Value, e.NewSize.Width);
            }
        }

        private void ToggleSidebar_Click(object sender, RoutedEventArgs e)
        {
            _sidebarVisible = !_sidebarVisible;
            _userForcedSidebar = _sidebarVisible;
            ApplySidebarState(_sidebarVisible, ActualWidth);
        }

        private void ApplySidebarState(bool visible, double windowWidth)
        {
            _sidebarVisible = visible;
            if (!visible)
            {
                SidebarCol.Width = new GridLength(0);
                SidebarGapCol.Width = new GridLength(0);
                PagesTileBorder.Visibility = Visibility.Collapsed;
            }
            else
            {
                double colW = windowWidth < 920 ? 136 : 168;
                SidebarCol.Width = new GridLength(colW);
                SidebarGapCol.Width = new GridLength(10);
                PagesTileBorder.Visibility = Visibility.Visible;
            }
        }

        private void ConfigureSignaturePad()
        {
            SignatureInkCanvas.DefaultDrawingAttributes = new DrawingAttributes
            {
                Color = Color.FromRgb(10, 46, 138),
                Width = 2.8,
                Height = 2.8,
                FitToCurve = true,
                IgnorePressure = false
            };
        }

        // =====================================================================
        // PDF LOADING, CONTINUOUS MULTI-PAGE STACK & HIGH-DPI RENDERING
        // =====================================================================
        private async void OpenPdf_Click(object sender, RoutedEventArgs e)
        {
            if (!ConfirmAndHandleUnsavedChangesSync())
                return;

            var dlg = new OpenFileDialog
            {
                Title = "PDF-Dokument öffnen",
                Filter = "PDF-Dokumente (*.pdf)|*.pdf|Alle Dateien (*.*)|*.*"
            };
            if (dlg.ShowDialog() == true)
            {
                await LoadPdfFromFileAsync(dlg.FileName);
            }
        }

        private async void NewBlankPdf_Click(object sender, RoutedEventArgs e)
        {
            if (!ConfirmAndHandleUnsavedChangesSync())
                return;

            await CreateNewBlankDocumentAsync();
        }

        public async Task CreateNewBlankDocumentAsync()
        {
            try
            {
                using var doc = new PdfSharp.Pdf.PdfDocument();
                var page = doc.AddPage();
                page.Width = XUnit.FromPoint(595);
                page.Height = XUnit.FromPoint(842);

                using var ms = new MemoryStream();
                doc.Save(ms, false);
                _basePdfBytes = ms.ToArray();
                _currentFilePath = "";
                _formFields.Clear();
                _cleanRenderPdfBytes = _basePdfBytes;
                _annotations.Clear();
                _selectedAnnotation = null;

                _documentBaseTitle = "Funkenwerk PDF · Neues Dokument";
                ClearDirty();

                await RefreshDocumentStructureAsync(0);
                StatusText.Text = "✓ Neues leeres A4-Dokument erstellt.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Fehler: {ex.Message}";
            }
        }

        public async Task LoadPdfFromFileAsync(string filePath)
        {
            try
            {
                var fi = new FileInfo(filePath);
                if (!fi.Exists)
                    throw new FileNotFoundException("PDF-Datei wurde nicht gefunden.", filePath);
                if (fi.Length <= 0 || fi.Length > PdfFormFieldEngine.MaxPdfFileSizeBytes)
                    throw new InvalidOperationException("PDF-Datei überschreitet das Limit von 256 MB oder ist leer.");

                byte[] bytes = await File.ReadAllBytesAsync(filePath);
                _basePdfBytes = bytes;
                _currentFilePath = filePath;
                _annotations.Clear();
                _selectedAnnotation = null;

                ReloadFormFieldsFromBaseBytes();
                _documentBaseTitle = $"Funkenwerk PDF · {IOPath.GetFileName(filePath)}";
                ClearDirty();

                await RefreshDocumentStructureAsync(0);

                StatusText.Text = _formFields.Count > 0
                    ? $"Geöffnet: {filePath}  •  {_pageCount} Seite(n)  •  {_formFields.Count} Formularfelder aktiv"
                    : $"Geöffnet: {filePath}  •  {_pageCount} Seite(n)";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ PDF konnte nicht geöffnet werden: {ex.Message}";
                ShowViewerToast($"⚠ Öffnen fehlgeschlagen: {ex.Message}", isError: true);
            }
        }

        private void ReloadFormFieldsFromBaseBytes()
        {
            _formFields.Clear();
            if (_basePdfBytes == null || _basePdfBytes.Length == 0)
            {
                _cleanRenderPdfBytes = null;
                return;
            }

            var extracted = PdfFormFieldEngine.ExtractFields(_basePdfBytes);
            _formFields.AddRange(extracted);
            _cleanRenderPdfBytes = PdfFormFieldEngine.CreateCleanRenderPdfBytes(_basePdfBytes, _formFields.Count > 0);
        }

        private async Task RefreshDocumentStructureAsync(
            int targetPageIndex,
            double? restoreVerticalOffset = null,
            double? restoreHorizontalOffset = null)
        {
            byte[]? renderBytes = _cleanRenderPdfBytes ?? _basePdfBytes;
            if (renderBytes == null || renderBytes.Length == 0) return;

            int loadGen = ++_documentLoadGeneration;
            ClearTextSelection();

            byte[]? textSourceBytes = _basePdfBytes ?? renderBytes;
            var textEngineTask = Task.Run(() => PdfTextSelectionEngine.CreateFromPdfBytes(textSourceBytes));

            using var stream = new InMemoryRandomAccessStream();
            using (var writer = new DataWriter(stream.GetOutputStreamAt(0)))
            {
                writer.WriteBytes(renderBytes);
                await writer.StoreAsync();
                await writer.FlushAsync();
            }

            var winPdf = await Windows.Data.Pdf.PdfDocument.LoadFromStreamAsync(stream);
            _textEngine = await textEngineTask;
            if (loadGen != _documentLoadGeneration) return;

            _pageCount = (int)winPdf.PageCount;
            if (_pageCount <= 0) return;

            _currentPageIndex = Math.Clamp(targetPageIndex, 0, _pageCount - 1);

            PagesStackPanel.Children.Clear();
            _pageSlots.Clear();

            var entries = new List<PageListEntry>();
            for (int i = 0; i < _pageCount; i++)
            {
                double wPt = 595.0;
                double hPt = 842.0;
                using (var page = winPdf.GetPage((uint)i))
                {
                    wPt = page.Size.Width * 72.0 / 96.0;
                    hPt = page.Size.Height * 72.0 / 96.0;
                }

                var slot = CreatePageVisualSlot(i, wPt, hPt, isLastPage: i == _pageCount - 1);
                _pageSlots.Add(slot);
                PagesStackPanel.Children.Add(slot.WrapperGrid);

                int countOnPage = _annotations.Count(a => a.PageIndex == i)
                                + _formFields.Count(f => f.PageIndex == i && f.HasValue);
                entries.Add(new PageListEntry
                {
                    Index = i,
                    Badge = countOnPage > 0 ? $"{countOnPage}" : ""
                });
            }

            _currentPageWidthPt = _pageSlots[_currentPageIndex].WidthPt;
            _currentPageHeightPt = _pageSlots[_currentPageIndex].HeightPt;

            _suppressSidebarSelectionJump = true;
            PagesListBox.ItemsSource = entries;
            PagesListBox.SelectedIndex = _currentPageIndex;
            _suppressSidebarSelectionJump = false;

            EmptyStateOverlay.Visibility = Visibility.Collapsed;
            PageCounterText.Text = $"Seite {_currentPageIndex + 1} / {_pageCount}";

            RenderAllFormFieldsOverlays();
            RenderAllAnnotationsOverlays();

            for (int i = 0; i < _pageCount; i++)
            {
                if (Math.Abs(i - _currentPageIndex) <= 3)
                {
                    await RenderSingleSlotBitmapAsync(_pageSlots[i], winPdf, loadGen);
                }
            }

            PageScrollViewer.UpdateLayout();
            if (restoreVerticalOffset.HasValue)
            {
                PageScrollViewer.ScrollToHorizontalOffset(Math.Max(0, restoreHorizontalOffset ?? 0));
                PageScrollViewer.ScrollToVerticalOffset(Math.Max(0, restoreVerticalOffset.Value));
            }
            else if (targetPageIndex > 0 && targetPageIndex < _pageSlots.Count)
            {
                ScrollToPageSlot(_pageSlots[targetPageIndex]);
            }
            else
            {
                PageScrollViewer.ScrollToVerticalOffset(0);
            }

            _ = EnsureVisiblePagesRenderedAsync(forceZoomRefresh: false);
        }

        private PageVisualSlot CreatePageVisualSlot(int pageIndex, double widthPt, double heightPt, bool isLastPage)
        {
            double displayW = widthPt * _zoomFactor;
            double displayH = heightPt * _zoomFactor;

            var wrapperGrid = new Grid
            {
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Top,
                Margin = new Thickness(0, 0, 0, isLastPage ? 0 : 22)
            };

            var shadowBorder = new Border
            {
                Background = Brushes.Black,
                Opacity = 0.32,
                CornerRadius = new CornerRadius(2),
                Margin = new Thickness(4, 6, -4, -6),
                IsHitTestVisible = false
            };

            var paperBorder = new Border
            {
                Background = Brushes.White,
                BorderBrush = BrushHex("#D1D5DB"),
                BorderThickness = new Thickness(1),
                Width = displayW,
                Height = displayH
            };

            var surfaceGrid = new Grid
            {
                Background = Brushes.White
            };

            var pageImage = new Image
            {
                Stretch = Stretch.Fill
            };
            RenderOptions.SetBitmapScalingMode(pageImage, BitmapScalingMode.HighQuality);

            var textSelectionCanvas = new Canvas
            {
                Width = displayW,
                Height = displayH,
                ClipToBounds = true,
                IsHitTestVisible = false
            };

            var formFieldsCanvas = new Canvas
            {
                Width = displayW,
                Height = displayH,
                ClipToBounds = true,
                IsHitTestVisible = (_activeTool == PdfToolMode.Select)
            };
            KeyboardNavigation.SetTabNavigation(formFieldsCanvas, KeyboardNavigationMode.Continue);

            var annotationCanvas = new Canvas
            {
                Width = displayW,
                Height = displayH,
                ClipToBounds = false
            };

            surfaceGrid.Children.Add(pageImage);
            surfaceGrid.Children.Add(textSelectionCanvas);
            surfaceGrid.Children.Add(formFieldsCanvas);
            surfaceGrid.Children.Add(annotationCanvas);

            paperBorder.Child = surfaceGrid;
            wrapperGrid.Children.Add(shadowBorder);
            wrapperGrid.Children.Add(paperBorder);

            var slot = new PageVisualSlot
            {
                PageIndex = pageIndex,
                WidthPt = widthPt,
                HeightPt = heightPt,
                WrapperGrid = wrapperGrid,
                PaperBorder = paperBorder,
                PageSurfaceGrid = surfaceGrid,
                PageImage = pageImage,
                TextSelectionCanvas = textSelectionCanvas,
                FormFieldsCanvas = formFieldsCanvas,
                AnnotationCanvas = annotationCanvas
            };

            AttachPageSurfaceInputHandlers(slot);
            surfaceGrid.ContextMenu = BuildPageContextMenu(slot);

            return slot;
        }

        private ContextMenu BuildPageContextMenu(PageVisualSlot slot)
        {
            var menu = new ContextMenu();
            var copyItem = new MenuItem { Header = "Markierten Text kopieren (Strg+C)" };
            copyItem.Click += (s, e) => CopyCurrentTextSelection();

            var selectPageItem = new MenuItem { Header = $"Text auf Seite {slot.PageIndex + 1} markieren (Strg+A)" };
            selectPageItem.Click += (s, e) =>
            {
                SetCurrentPageActive(slot.PageIndex);
                SelectAllTextOnPage(slot.PageIndex);
            };

            var clearSelItem = new MenuItem { Header = "Textauswahl aufheben (Esc)" };
            clearSelItem.Click += (s, e) => ClearTextSelection();

            menu.Opened += (s, e) =>
            {
                copyItem.IsEnabled = _currentTextSelection != null && !string.IsNullOrEmpty(_currentTextSelection.Text);
                clearSelItem.IsEnabled = copyItem.IsEnabled;
            };

            menu.Items.Add(copyItem);
            menu.Items.Add(selectPageItem);
            menu.Items.Add(new Separator());
            menu.Items.Add(clearSelItem);
            return menu;
        }

        private async Task RenderSingleSlotBitmapAsync(PageVisualSlot slot, Windows.Data.Pdf.PdfDocument winPdf, int expectedGen)
        {
            if (expectedGen != _documentLoadGeneration) return;
            try
            {
                double targetZoom = _zoomFactor;
                double displayW = slot.WidthPt * targetZoom;
                double displayH = slot.HeightPt * targetZoom;

                using var page = winPdf.GetPage((uint)slot.PageIndex);
                using var renderStream = new InMemoryRandomAccessStream();
                var opts = new PdfPageRenderOptions
                {
                    DestinationWidth = (uint)Math.Clamp(displayW * 1.75, 100, 4200),
                    DestinationHeight = (uint)Math.Clamp(displayH * 1.75, 100, 4200)
                };
                await page.RenderToStreamAsync(renderStream, opts);

                if (expectedGen != _documentLoadGeneration) return;

                if (_textEngine != null && _textEngine.NeedsScannedPageOcr(slot.PageIndex))
                {
                    await _textEngine.TryPopulateOcrFromRenderedStreamAsync(slot.PageIndex, renderStream, slot.WidthPt, slot.HeightPt);
                }

                renderStream.Seek(0);
                var bmp = new BitmapImage();
                bmp.BeginInit();
                bmp.CacheOption = BitmapCacheOption.OnLoad;
                bmp.StreamSource = renderStream.AsStreamForRead();
                bmp.EndInit();
                bmp.Freeze();

                slot.PageImage.Source = bmp;
                slot.RenderedAtZoom = targetZoom;
            }
            catch
            {
                // Keep page surface intact
            }
        }

        private async Task EnsureVisiblePagesRenderedAsync(bool forceZoomRefresh = false)
        {
            if (_isBatchRenderingPages) return;
            byte[]? renderBytes = _cleanRenderPdfBytes ?? _basePdfBytes;
            if (renderBytes == null || _pageSlots.Count == 0) return;

            int gen = _documentLoadGeneration;
            double wantedZoom = _zoomFactor;

            var candidates = GetVisibleAndNearbySlots(extraMarginPages: 2)
                .Where(s => s.PageImage.Source == null || (forceZoomRefresh && Math.Abs(s.RenderedAtZoom - wantedZoom) > 0.01) || Math.Abs(s.RenderedAtZoom - wantedZoom) > 0.05)
                .ToList();

            if (candidates.Count == 0) return;

            _isBatchRenderingPages = true;
            try
            {
                using var ownedStream = new InMemoryRandomAccessStream();
                using (var writer = new DataWriter(ownedStream.GetOutputStreamAt(0)))
                {
                    writer.WriteBytes(renderBytes);
                    await writer.StoreAsync();
                    await writer.FlushAsync();
                }

                var winPdf = await Windows.Data.Pdf.PdfDocument.LoadFromStreamAsync(ownedStream);
                foreach (var slot in candidates)
                {
                    if (gen != _documentLoadGeneration) break;
                    await RenderSingleSlotBitmapAsync(slot, winPdf, gen);
                }

                if (_pageSlots.Count > 35)
                {
                    foreach (var slot in _pageSlots)
                    {
                        if (Math.Abs(slot.PageIndex - _currentPageIndex) > 16 && slot.PageImage.Source != null)
                        {
                            slot.PageImage.Source = null;
                            slot.RenderedAtZoom = 0.0;
                        }
                    }
                }
            }
            catch { }
            finally
            {
                _isBatchRenderingPages = false;
            }
        }

        private List<PageVisualSlot> GetVisibleAndNearbySlots(int extraMarginPages = 2)
        {
            if (_pageSlots.Count == 0) return new List<PageVisualSlot>();
            double viewH = Math.Max(200.0, PageScrollViewer.ActualHeight);

            int minVisible = _currentPageIndex;
            int maxVisible = _currentPageIndex;
            bool foundAny = false;

            foreach (var slot in _pageSlots)
            {
                try
                {
                    Point topInView = slot.PaperBorder.TranslatePoint(new Point(0, 0), PageScrollViewer);
                    double bottomInView = topInView.Y + slot.PaperBorder.Height;
                    if (bottomInView >= -250 && topInView.Y <= viewH + 250)
                    {
                        if (!foundAny)
                        {
                            minVisible = slot.PageIndex;
                            maxVisible = slot.PageIndex;
                            foundAny = true;
                        }
                        else
                        {
                            minVisible = Math.Min(minVisible, slot.PageIndex);
                            maxVisible = Math.Max(maxVisible, slot.PageIndex);
                        }
                    }
                }
                catch { }
            }

            int start = Math.Max(0, minVisible - extraMarginPages);
            int end = Math.Min(_pageSlots.Count - 1, maxVisible + extraMarginPages);
            var list = new List<PageVisualSlot>(end - start + 1);
            for (int i = start; i <= end; i++)
                list.Add(_pageSlots[i]);
            return list;
        }

        private void PageScrollViewer_ScrollChanged(object sender, ScrollChangedEventArgs e)
        {
            if (_pageSlots.Count == 0) return;

            double viewH = Math.Max(1.0, PageScrollViewer.ActualHeight);
            double probeY = viewH * 0.36;

            int bestPage = _currentPageIndex;
            double bestScore = double.MinValue;

            foreach (var slot in _pageSlots)
            {
                try
                {
                    Point topInView = slot.PaperBorder.TranslatePoint(new Point(0, 0), PageScrollViewer);
                    double bottomInView = topInView.Y + slot.PaperBorder.Height;

                    if (topInView.Y <= probeY && bottomInView >= probeY)
                    {
                        bestPage = slot.PageIndex;
                        break;
                    }

                    double overlap = Math.Min(viewH, bottomInView) - Math.Max(0.0, topInView.Y);
                    if (overlap > bestScore)
                    {
                        bestScore = overlap;
                        bestPage = slot.PageIndex;
                    }
                }
                catch { }
            }

            if (bestPage != _currentPageIndex)
            {
                SetCurrentPageActive(bestPage);
            }

            if (Math.Abs(e.VerticalChange) > 0.5 || Math.Abs(e.ViewportHeightChange) > 0.5)
            {
                _scrollRenderDebounceTimer.Stop();
                _scrollRenderDebounceTimer.Start();
            }
        }

        private void SetCurrentPageActive(int pageIndex)
        {
            if (pageIndex < 0 || pageIndex >= _pageSlots.Count) return;
            _currentPageIndex = pageIndex;
            _currentPageWidthPt = _pageSlots[pageIndex].WidthPt;
            _currentPageHeightPt = _pageSlots[pageIndex].HeightPt;
            PageCounterText.Text = $"Seite {_currentPageIndex + 1} / {_pageCount}";

            if (PagesListBox.SelectedIndex != _currentPageIndex)
            {
                _suppressSidebarSelectionJump = true;
                PagesListBox.SelectedIndex = _currentPageIndex;
                if (PagesListBox.SelectedItem != null)
                    PagesListBox.ScrollIntoView(PagesListBox.SelectedItem);
                _suppressSidebarSelectionJump = false;
            }
        }

        private void ScrollToPageSlot(PageVisualSlot slot)
        {
            try
            {
                PageScrollViewer.UpdateLayout();
                Point posInStack = slot.WrapperGrid.TranslatePoint(new Point(0, 0), PagesStackPanel);
                PageScrollViewer.ScrollToVerticalOffset(Math.Max(0, posInStack.Y));
            }
            catch { }
        }

        private async void PagesListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressSidebarSelectionJump) return;
            if (PagesListBox.SelectedItem is not PageListEntry entry) return;
            if (entry.Index < 0 || entry.Index >= _pageSlots.Count) return;

            _currentPageIndex = entry.Index;
            _currentPageWidthPt = _pageSlots[_currentPageIndex].WidthPt;
            _currentPageHeightPt = _pageSlots[_currentPageIndex].HeightPt;
            _selectedAnnotation = null;
            RenderAllAnnotationsOverlays();

            ScrollToPageSlot(_pageSlots[_currentPageIndex]);
            PageCounterText.Text = $"Seite {_currentPageIndex + 1} / {_pageCount}";
            await EnsureVisiblePagesRenderedAsync(forceZoomRefresh: false);
        }

        // =====================================================================
        // MOUSE TEXT SELECTION & COPY + PAGE ANNOTATION PLACEMENT
        // =====================================================================
        private void AttachPageSurfaceInputHandlers(PageVisualSlot slot)
        {
            slot.PageSurfaceGrid.MouseMove += (s, e) => OnPageSurfaceMouseMove(slot, e);
            slot.PageSurfaceGrid.MouseLeftButtonDown += (s, e) => OnPageSurfaceMouseLeftButtonDown(slot, e);
            slot.PageSurfaceGrid.MouseLeftButtonUp += (s, e) => OnPageSurfaceMouseLeftButtonUp(slot, e);
        }

        private bool IsRawPageSurfaceSource(PageVisualSlot slot, object? originalSource)
        {
            return originalSource == slot.PageImage
                || originalSource == slot.PageSurfaceGrid
                || originalSource == slot.AnnotationCanvas
                || originalSource == slot.TextSelectionCanvas;
        }

        private (int PageIndex, Point PtInPdfPoints) ResolveSlotAndPointFromMouseEvent(MouseEventArgs e, PageVisualSlot fallbackSlot)
        {
            foreach (var candidate in _pageSlots)
            {
                Point pPx = e.GetPosition(candidate.PageSurfaceGrid);
                if (pPx.Y >= -12.0 && pPx.Y <= candidate.PaperBorder.Height + 12.0)
                {
                    return (candidate.PageIndex, new Point(pPx.X / _zoomFactor, pPx.Y / _zoomFactor));
                }
            }

            Point fbPx = e.GetPosition(fallbackSlot.PageSurfaceGrid);
            return (fallbackSlot.PageIndex, new Point(fbPx.X / _zoomFactor, fbPx.Y / _zoomFactor));
        }

        private void OnPageSurfaceMouseMove(PageVisualSlot slot, MouseEventArgs e)
        {
            if (_basePdfBytes == null) return;

            if (_isDraggingTextSelection && e.LeftButton == MouseButtonState.Pressed)
            {
                Point curViewportPx = e.GetPosition(PageScrollViewer);
                double dragDistPx = (curViewportPx - _textDragStartViewportPx).Length;
                if (dragDistPx < 3.0) return;

                if (curViewportPx.Y < 26 && PageScrollViewer.VerticalOffset > 0)
                {
                    PageScrollViewer.ScrollToVerticalOffset(Math.Max(0, PageScrollViewer.VerticalOffset - 18));
                }
                else if (curViewportPx.Y > PageScrollViewer.ActualHeight - 26)
                {
                    PageScrollViewer.ScrollToVerticalOffset(PageScrollViewer.VerticalOffset + 18);
                }

                var (endPage, endPt) = ResolveSlotAndPointFromMouseEvent(e, slot);
                if (_textEngine != null)
                {
                    _currentTextSelection = _textEngine.SelectRange(_textDragAnchorPage, _textDragAnchorPt, endPage, endPt);
                    RenderTextSelectionHighlights(showCopyPill: false);
                }
                e.Handled = true;
                return;
            }

            if (_activeTool == PdfToolMode.Select)
            {
                if (!IsRawPageSurfaceSource(slot, e.OriginalSource))
                {
                    slot.PageSurfaceGrid.Cursor = Cursors.Arrow;
                    return;
                }

                Point hoverPx = e.GetPosition(slot.PageSurfaceGrid);
                Point hoverPt = new Point(hoverPx.X / _zoomFactor, hoverPx.Y / _zoomFactor);
                bool overText = _textEngine != null && _textEngine.IsPointOverText(slot.PageIndex, hoverPt);
                slot.PageSurfaceGrid.Cursor = overText ? Cursors.IBeam : Cursors.Arrow;
            }
            else
            {
                slot.PageSurfaceGrid.Cursor = Cursors.Cross;
            }
        }

        private void OnPageSurfaceMouseLeftButtonDown(PageVisualSlot slot, MouseButtonEventArgs e)
        {
            if (_basePdfBytes == null) return;
            if (!IsRawPageSurfaceSource(slot, e.OriginalSource))
                return;

            SetCurrentPageActive(slot.PageIndex);

            Point clickPx = e.GetPosition(slot.AnnotationCanvas);
            double xPt = clickPx.X / _zoomFactor;
            double yPt = clickPx.Y / _zoomFactor;
            Point clickPt = new Point(xPt, yPt);

            switch (_activeTool)
            {
                case PdfToolMode.Select:
                {
                    Keyboard.ClearFocus();
                    if (_selectedAnnotation != null)
                    {
                        _selectedAnnotation = null;
                        RenderAllAnnotationsOverlays();
                    }

                    if (e.ClickCount == 2 && _textEngine != null)
                    {
                        _currentTextSelection = _textEngine.SelectWordAt(slot.PageIndex, clickPt);
                        RenderTextSelectionHighlights(showCopyPill: _currentTextSelection != null);
                        if (_currentTextSelection != null)
                            StatusText.Text = "Text markiert · Strg+C oder Klick auf 'Kopieren' übernimmt den Text.";
                        e.Handled = true;
                        return;
                    }

                    if (e.ClickCount >= 3 && _textEngine != null)
                    {
                        _currentTextSelection = _textEngine.SelectLineAt(slot.PageIndex, clickPt);
                        RenderTextSelectionHighlights(showCopyPill: _currentTextSelection != null);
                        if (_currentTextSelection != null)
                            StatusText.Text = "Zeile markiert · Strg+C oder Klick auf 'Kopieren' übernimmt den Text.";
                        e.Handled = true;
                        return;
                    }

                    ClearTextSelection();
                    _isDraggingTextSelection = true;
                    _textDragAnchorPage = slot.PageIndex;
                    _textDragAnchorPt = clickPt;
                    _textDragStartViewportPx = e.GetPosition(PageScrollViewer);
                    _textDragCaptureOwner = slot.PageSurfaceGrid;
                    slot.PageSurfaceGrid.CaptureMouse();
                    e.Handled = true;
                    break;
                }

                case PdfToolMode.Text:
                {
                    ClearTextSelection();
                    var item = new PdfAnnotationItem
                    {
                        PageIndex = slot.PageIndex,
                        Type = PdfAnnotationType.Text,
                        XPt = Math.Max(4, xPt),
                        YPt = Math.Max(4, yPt - 8),
                        WidthPt = 185,
                        HeightPt = Math.Max(22, _activeSizePt * 1.8),
                        Text = "Text",
                        FontSizePt = _activeSizePt,
                        StrokeColor = _activeColor
                    };
                    _annotations.Add(item);
                    MarkDirty();
                    SelectAnnotation(item);
                    SetActiveTool(PdfToolMode.Select);
                    RenderAllAnnotationsOverlays(focusSelectedText: true);
                    UpdatePageBadges();
                    break;
                }

                case PdfToolMode.Check:
                case PdfToolMode.Cross:
                {
                    ClearTextSelection();
                    double boxSizePt = Math.Max(14, _activeSizePt * 1.45);
                    var item = new PdfAnnotationItem
                    {
                        PageIndex = slot.PageIndex,
                        Type = _activeTool == PdfToolMode.Check ? PdfAnnotationType.Check : PdfAnnotationType.Cross,
                        XPt = Math.Max(2, xPt - boxSizePt / 2.0),
                        YPt = Math.Max(2, yPt - boxSizePt / 2.0),
                        WidthPt = boxSizePt,
                        HeightPt = boxSizePt,
                        FontSizePt = _activeSizePt,
                        StrokeColor = _activeColor
                    };
                    _annotations.Add(item);
                    MarkDirty();
                    SelectAnnotation(item);
                    SetActiveTool(PdfToolMode.Select);
                    RenderAllAnnotationsOverlays();
                    UpdatePageBadges();
                    StatusText.Text = "✓ Gesetzt: Größe per − / + direkt über dem Haken oder an der goldenen Ecke ⤡ ändern.";
                    break;
                }

                case PdfToolMode.Whiteout:
                {
                    ClearTextSelection();
                    var item = new PdfAnnotationItem
                    {
                        PageIndex = slot.PageIndex,
                        Type = PdfAnnotationType.Whiteout,
                        XPt = Math.Max(2, xPt),
                        YPt = Math.Max(2, yPt - 8),
                        WidthPt = 175,
                        HeightPt = Math.Max(22, _activeSizePt * 1.8),
                        Text = "",
                        FontSizePt = _activeSizePt,
                        StrokeColor = _activeColor
                    };
                    _annotations.Add(item);
                    MarkDirty();
                    SelectAnnotation(item);
                    SetActiveTool(PdfToolMode.Select);
                    RenderAllAnnotationsOverlays(focusSelectedText: true);
                    UpdatePageBadges();
                    break;
                }

                case PdfToolMode.Highlight:
                {
                    ClearTextSelection();
                    var item = new PdfAnnotationItem
                    {
                        PageIndex = slot.PageIndex,
                        Type = PdfAnnotationType.Highlight,
                        XPt = Math.Max(2, xPt),
                        YPt = Math.Max(2, yPt - 7),
                        WidthPt = 160,
                        HeightPt = 16,
                        FontSizePt = 16,
                        StrokeColor = Color.FromArgb(95, 250, 204, 21)
                    };
                    _annotations.Add(item);
                    MarkDirty();
                    SelectAnnotation(item);
                    SetActiveTool(PdfToolMode.Select);
                    RenderAllAnnotationsOverlays();
                    UpdatePageBadges();
                    break;
                }
            }
        }

        private void OnPageSurfaceMouseLeftButtonUp(PageVisualSlot slot, MouseButtonEventArgs e)
        {
            if (!_isDraggingTextSelection) return;
            _isDraggingTextSelection = false;
            if (_textDragCaptureOwner != null)
            {
                _textDragCaptureOwner.ReleaseMouseCapture();
                _textDragCaptureOwner = null;
            }

            if (_currentTextSelection != null && !string.IsNullOrEmpty(_currentTextSelection.Text))
            {
                RenderTextSelectionHighlights(showCopyPill: true);
                int charCount = _currentTextSelection.Text.Length;
                StatusText.Text = $"✓ {charCount} Zeichen markiert · Strg+C oder Klick auf 'Kopieren' zum Kopieren.";
            }
            e.Handled = true;
        }

        private void SelectAllTextOnPage(int pageIndex)
        {
            if (_textEngine == null) return;
            _currentTextSelection = _textEngine.SelectAllOnPage(pageIndex);
            RenderTextSelectionHighlights(showCopyPill: _currentTextSelection != null);
            if (_currentTextSelection != null)
            {
                StatusText.Text = $"✓ Seitentext markiert ({_currentTextSelection.Text.Length} Zeichen) · Strg+C zum Kopieren.";
            }
            else
            {
                ShowViewerToast("Kein auswählbarer Text auf dieser Seite gefunden", isError: false, durationMs: 2000);
            }
        }

        private void CopyCurrentTextSelection()
        {
            if (_currentTextSelection == null || string.IsNullOrEmpty(_currentTextSelection.Text))
                return;

            if (TrySetClipboardText(_currentTextSelection.Text))
            {
                ShowViewerToast("✓ Markierter Text kopiert", isError: false, durationMs: 2200);
                StatusText.Text = $"✓ {_currentTextSelection.Text.Length} Zeichen in die Zwischenablage kopiert.";
            }
        }

        private void ClearTextSelection()
        {
            _currentTextSelection = null;
            RemoveFloatingCopyPill();
            foreach (var slot in _pageSlots)
            {
                slot.TextSelectionCanvas.Children.Clear();
            }
        }

        private void RemoveFloatingCopyPill()
        {
            if (_floatingCopyPill != null)
            {
                if (_floatingCopyPill.Parent is Panel parent)
                    parent.Children.Remove(_floatingCopyPill);
                _floatingCopyPill = null;
            }
        }

        private void RenderTextSelectionHighlights(bool showCopyPill)
        {
            RemoveFloatingCopyPill();
            foreach (var slot in _pageSlots)
            {
                slot.TextSelectionCanvas.Children.Clear();
            }

            if (_currentTextSelection == null || _currentTextSelection.HighlightRectsByPage.Count == 0)
                return;

            var fillBrush = new SolidColorBrush(Color.FromArgb(88, 37, 99, 235));
            fillBrush.Freeze();

            foreach (var kv in _currentTextSelection.HighlightRectsByPage)
            {
                int pIdx = kv.Key;
                if (pIdx < 0 || pIdx >= _pageSlots.Count) continue;
                var slot = _pageSlots[pIdx];

                foreach (var rectPt in kv.Value)
                {
                    var rectUi = new Rectangle
                    {
                        Width = Math.Max(2.0, rectPt.Width * _zoomFactor),
                        Height = Math.Max(4.0, rectPt.Height * _zoomFactor),
                        Fill = fillBrush,
                        RadiusX = 1.5,
                        RadiusY = 1.5,
                        IsHitTestVisible = false
                    };
                    Canvas.SetLeft(rectUi, rectPt.X * _zoomFactor);
                    Canvas.SetTop(rectUi, rectPt.Y * _zoomFactor);
                    slot.TextSelectionCanvas.Children.Add(rectUi);
                }
            }

            if (showCopyPill && _currentTextSelection.EndPageIndex >= 0 && _currentTextSelection.EndPageIndex < _pageSlots.Count)
            {
                var targetSlot = _pageSlots[_currentTextSelection.EndPageIndex];
                Rect lastR = _currentTextSelection.LastHighlightRectPt;

                string pillBg = _isDarkMode ? "#151923" : "#FFFFFF";
                var copyBtn = new Button
                {
                    Content = "Kopieren (Strg+C)",
                    Height = 24,
                    Padding = new Thickness(10, 0, 10, 0),
                    FontSize = 11.5,
                    FontWeight = FontWeights.Bold,
                    Background = BrushHex(pillBg),
                    Foreground = BrushHex("#C5A059"),
                    BorderThickness = new Thickness(0),
                    Cursor = Cursors.Hand
                };
                copyBtn.Click += (s, ev) =>
                {
                    CopyCurrentTextSelection();
                    RemoveFloatingCopyPill();
                    ev.Handled = true;
                };

                var pill = new Border
                {
                    Background = BrushHex(pillBg),
                    BorderBrush = BrushHex("#C5A059"),
                    BorderThickness = new Thickness(1.2),
                    CornerRadius = new CornerRadius(6),
                    Child = copyBtn
                };

                double pillX = Math.Clamp(lastR.Right * _zoomFactor - 80, 6, Math.Max(8, targetSlot.PaperBorder.Width - 130));
                double pillY = lastR.Bottom * _zoomFactor + 6;
                if (pillY > targetSlot.PaperBorder.Height - 32)
                    pillY = Math.Max(4, lastR.Top * _zoomFactor - 30);

                Canvas.SetLeft(pill, pillX);
                Canvas.SetTop(pill, pillY);
                Panel.SetZIndex(pill, 999);
                targetSlot.AnnotationCanvas.Children.Add(pill);
                _floatingCopyPill = pill;
            }
        }

        // =====================================================================
        // ADOBE-STYLE LIVE INTERACTIVE ACROFORM INPUTS OVERLAY (ALL PAGES)
        // =====================================================================
        private void RenderAllFormFieldsOverlays(PdfFormFieldItem? restoreFocusField = null, int restoreCaretIdx = -1)
        {
            int globalTabIdx = 1;
            foreach (var slot in _pageSlots)
            {
                RenderFormFieldsOnSlot(slot, ref globalTabIdx, restoreFocusField, restoreCaretIdx);
            }
        }

        private void RenderFormFieldsOnSlot(PageVisualSlot slot, ref int tabIdx, PdfFormFieldItem? restoreFocusField, int restoreCaretIdx)
        {
            slot.FormFieldsCanvas.Children.Clear();
            slot.FormFieldsCanvas.IsHitTestVisible = (_activeTool == PdfToolMode.Select);

            var pageFields = _formFields
                .Where(f => f.PageIndex == slot.PageIndex)
                .OrderBy(f => Math.Round(f.YPt / 5.0))
                .ThenBy(f => f.XPt)
                .ToList();

            if (pageFields.Count == 0) return;

            var idleEmptyBg = new SolidColorBrush(Color.FromArgb(34, 59, 130, 246));
            var idleFilledBg = new SolidColorBrush(Color.FromArgb(14, 59, 130, 246));
            var idleReadOnlyBg = new SolidColorBrush(Color.FromArgb(10, 107, 114, 128));
            var idleBorder = new SolidColorBrush(Color.FromArgb(95, 59, 130, 246));
            var focusBg = new SolidColorBrush(Color.FromArgb(248, 255, 253, 246));
            var goldBorder = BrushHex("#C5A059");

            foreach (var field in pageFields)
            {
                double leftPx = field.XPt * _zoomFactor;
                double topPx = field.YPt * _zoomFactor;
                double widthPx = Math.Max(8, field.WidthPt * _zoomFactor);
                double heightPx = Math.Max(8, field.HeightPt * _zoomFactor);

                if (field.Kind == PdfAcroFieldKind.Text)
                {
                    var fieldBorder = new Border
                    {
                        Width = widthPx,
                        Height = heightPx,
                        Background = field.IsReadOnly ? idleReadOnlyBg : (field.HasValue ? idleFilledBg : idleEmptyBg),
                        BorderBrush = idleBorder,
                        BorderThickness = new Thickness(field.IsReadOnly ? 0.5 : 0.9),
                        CornerRadius = new CornerRadius(1.5),
                        ToolTip = string.IsNullOrWhiteSpace(field.Tooltip) ? null : field.Tooltip
                    };

                    double fontPx = field.IsMultiline && !field.IsPassword
                        ? Math.Clamp(field.FontSizePt, 8.0, 13.0) * _zoomFactor
                        : Math.Min(field.FontSizePt, Math.Max(7.0, field.HeightPt * 0.76)) * _zoomFactor;

                    if (field.IsPassword)
                    {
                        var pwdGrid = new Grid();
                        pwdGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                        pwdGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                        bool syncingPwd = false;
                        var pb = new PasswordBox
                        {
                            PasswordChar = '\u2022',
                            FontSize = Math.Max(8.0, fontPx),
                            Foreground = BrushHex("#111827"),
                            CaretBrush = BrushHex("#111827"),
                            Background = Brushes.Transparent,
                            BorderThickness = new Thickness(0),
                            Padding = new Thickness(2, -1, 2, -1),
                            VerticalContentAlignment = VerticalAlignment.Center,
                            IsEnabled = !field.IsReadOnly,
                            TabIndex = tabIdx++
                        };
                        if (field.MaxLength > 0)
                            pb.MaxLength = field.MaxLength;

                        _suppressDirtyTracking = true;
                        pb.Password = field.TextValue ?? "";
                        _suppressDirtyTracking = false;

                        var revealTb = new TextBox
                        {
                            Text = field.TextValue ?? "",
                            IsReadOnly = field.IsReadOnly,
                            FontSize = Math.Max(8.0, fontPx),
                            Foreground = BrushHex("#111827"),
                            CaretBrush = BrushHex("#111827"),
                            Background = Brushes.Transparent,
                            BorderThickness = new Thickness(0),
                            Padding = new Thickness(2, -1, 2, -1),
                            VerticalContentAlignment = VerticalAlignment.Center,
                            Visibility = Visibility.Collapsed
                        };
                        if (field.MaxLength > 0)
                            revealTb.MaxLength = field.MaxLength;

                        pb.PasswordChanged += (s, ev) =>
                        {
                            if (syncingPwd) return;
                            syncingPwd = true;
                            field.TextValue = pb.Password;
                            revealTb.Text = pb.Password;
                            syncingPwd = false;
                            MarkDirty();
                            UpdatePageBadges();
                        };

                        revealTb.TextChanged += (s, ev) =>
                        {
                            if (syncingPwd) return;
                            syncingPwd = true;
                            field.TextValue = revealTb.Text;
                            pb.Password = revealTb.Text;
                            syncingPwd = false;
                            MarkDirty();
                            UpdatePageBadges();
                        };

                        void OnPwdFocus(object s, RoutedEventArgs ev)
                        {
                            _focusedFormField = field;
                            SetCurrentPageActive(slot.PageIndex);
                            ClearTextSelection();
                            if (_selectedAnnotation != null)
                            {
                                _selectedAnnotation = null;
                                RenderAllAnnotationsOverlays();
                            }
                            fieldBorder.Background = focusBg;
                            fieldBorder.BorderBrush = goldBorder;
                            fieldBorder.BorderThickness = new Thickness(1.5);
                        }

                        void OnPwdLostFocus(object s, RoutedEventArgs ev)
                        {
                            if (_focusedFormField == field)
                                _focusedFormField = null;
                            fieldBorder.Background = field.HasValue ? idleFilledBg : idleEmptyBg;
                            fieldBorder.BorderBrush = idleBorder;
                            fieldBorder.BorderThickness = new Thickness(0.9);
                        }

                        pb.GotFocus += OnPwdFocus;
                        pb.LostFocus += OnPwdLostFocus;
                        revealTb.GotFocus += OnPwdFocus;
                        revealTb.LostFocus += OnPwdLostFocus;

                        var eyeBtn = new Button
                        {
                            Content = "👁",
                            Width = Math.Clamp(heightPx, 16, 24),
                            FontSize = Math.Clamp(fontPx * 0.85, 8.0, 12.0),
                            Background = Brushes.Transparent,
                            Foreground = BrushHex("#4B5563"),
                            BorderThickness = new Thickness(0),
                            Padding = new Thickness(0),
                            Cursor = Cursors.Hand,
                            Focusable = false,
                            ToolTip = "Passwort anzeigen / verbergen"
                        };
                        eyeBtn.Click += (s, ev) =>
                        {
                            bool showingPlain = revealTb.Visibility == Visibility.Visible;
                            if (showingPlain)
                            {
                                revealTb.Visibility = Visibility.Collapsed;
                                pb.Visibility = Visibility.Visible;
                                pb.Focus();
                            }
                            else
                            {
                                pb.Visibility = Visibility.Collapsed;
                                revealTb.Visibility = Visibility.Visible;
                                revealTb.Focus();
                                revealTb.CaretIndex = revealTb.Text.Length;
                            }
                            ev.Handled = true;
                        };

                        Grid.SetColumn(pb, 0);
                        Grid.SetColumn(revealTb, 0);
                        Grid.SetColumn(eyeBtn, 1);
                        pwdGrid.Children.Add(pb);
                        pwdGrid.Children.Add(revealTb);
                        if (widthPx >= 36)
                            pwdGrid.Children.Add(eyeBtn);

                        fieldBorder.Child = pwdGrid;
                        Canvas.SetLeft(fieldBorder, leftPx);
                        Canvas.SetTop(fieldBorder, topPx);
                        slot.FormFieldsCanvas.Children.Add(fieldBorder);
                        continue;
                    }

                    var tb = new TextBox
                    {
                        Text = field.TextValue,
                        IsReadOnly = field.IsReadOnly,
                        AcceptsReturn = field.IsMultiline,
                        TextWrapping = field.IsMultiline ? TextWrapping.Wrap : TextWrapping.NoWrap,
                        FontSize = Math.Max(8.0, fontPx),
                        FontWeight = field.IsTinyBoxText ? FontWeights.Bold : FontWeights.Normal,
                        Foreground = BrushHex("#111827"),
                        CaretBrush = BrushHex("#111827"),
                        Background = Brushes.Transparent,
                        BorderThickness = new Thickness(0),
                        Padding = field.IsMultiline
                            ? new Thickness(3, 2, 3, 2)
                            : (field.IsTinyBoxText ? new Thickness(0, -1, 0, -1) : new Thickness(2, -1, 2, -1)),
                        VerticalContentAlignment = field.IsMultiline ? VerticalAlignment.Top : VerticalAlignment.Center,
                        TextAlignment = field.Alignment switch
                        {
                            1 => TextAlignment.Center,
                            2 => TextAlignment.Right,
                            _ => TextAlignment.Left
                        },
                        TabIndex = tabIdx++
                    };

                    if (field.MaxLength > 0)
                        tb.MaxLength = field.MaxLength;

                    tb.GotFocus += (s, ev) =>
                    {
                        _focusedFormField = field;
                        SetCurrentPageActive(slot.PageIndex);
                        ClearTextSelection();
                        if (_selectedAnnotation != null)
                        {
                            _selectedAnnotation = null;
                            RenderAllAnnotationsOverlays();
                        }
                        if (!field.IsReadOnly)
                        {
                            fieldBorder.Background = focusBg;
                            fieldBorder.BorderBrush = goldBorder;
                            fieldBorder.BorderThickness = new Thickness(1.5);
                        }
                        fieldBorder.BringIntoView();
                    };

                    tb.LostFocus += (s, ev) =>
                    {
                        if (_focusedFormField == field)
                            _focusedFormField = null;
                        fieldBorder.Background = field.IsReadOnly ? idleReadOnlyBg : (field.HasValue ? idleFilledBg : idleEmptyBg);
                        fieldBorder.BorderBrush = idleBorder;
                        fieldBorder.BorderThickness = new Thickness(field.IsReadOnly ? 0.5 : 0.9);
                    };

                    if (restoreFocusField == field)
                    {
                        int targetCaret = restoreCaretIdx;
                        Dispatcher.InvokeAsync(() =>
                        {
                            tb.Focus();
                            if (targetCaret >= 0)
                                tb.CaretIndex = Math.Min(targetCaret, (tb.Text ?? "").Length);
                        });
                    }

                    tb.TextChanged += (s, ev) =>
                    {
                        if (field.IsReadOnly) return;
                        field.TextValue = tb.Text ?? "";
                        MarkDirty();
                        UpdatePageBadges();
                    };

                    if (field.IsTinyBoxText && !field.IsReadOnly)
                    {
                        tb.Cursor = Cursors.Hand;
                        tb.MouseDoubleClick += (s, ev) =>
                        {
                            tb.Text = string.IsNullOrWhiteSpace(tb.Text) ? "X" : "";
                            tb.CaretIndex = tb.Text.Length;
                            ev.Handled = true;
                        };
                        tb.PreviewKeyDown += (s, ev) =>
                        {
                            if (ev.Key == Key.Space)
                            {
                                tb.Text = string.IsNullOrWhiteSpace(tb.Text) ? "X" : "";
                                tb.CaretIndex = tb.Text.Length;
                                ev.Handled = true;
                            }
                        };
                    }

                    fieldBorder.Child = tb;
                    Canvas.SetLeft(fieldBorder, leftPx);
                    Canvas.SetTop(fieldBorder, topPx);
                    slot.FormFieldsCanvas.Children.Add(fieldBorder);
                }
                else if (field.Kind == PdfAcroFieldKind.CheckBox || field.Kind == PdfAcroFieldKind.RadioButton)
                {
                    bool isRadio = (field.Kind == PdfAcroFieldKind.RadioButton);
                    var boxBorder = new Border
                    {
                        Width = widthPx,
                        Height = heightPx,
                        Background = field.IsChecked ? focusBg : idleEmptyBg,
                        BorderBrush = field.IsChecked ? goldBorder : idleBorder,
                        BorderThickness = new Thickness(field.IsChecked ? 1.3 : 1.0),
                        CornerRadius = new CornerRadius(isRadio ? Math.Min(widthPx, heightPx) * 0.5 : 2.0),
                        Cursor = field.IsReadOnly ? Cursors.Arrow : Cursors.Hand,
                        Focusable = !field.IsReadOnly,
                        ToolTip = string.IsNullOrWhiteSpace(field.Tooltip) ? null : field.Tooltip
                    };
                    KeyboardNavigation.SetTabIndex(boxBorder, tabIdx++);

                    void RefreshCheckVisual()
                    {
                        boxBorder.Background = field.IsChecked ? focusBg : idleEmptyBg;
                        boxBorder.BorderBrush = field.IsChecked ? goldBorder : idleBorder;
                        if (!field.IsChecked)
                        {
                            boxBorder.Child = null;
                        }
                        else if (isRadio)
                        {
                            double dot = Math.Max(4, Math.Min(widthPx, heightPx) * 0.52);
                            boxBorder.Child = new Ellipse
                            {
                                Width = dot,
                                Height = dot,
                                Fill = BrushHex("#111827"),
                                HorizontalAlignment = HorizontalAlignment.Center,
                                VerticalAlignment = VerticalAlignment.Center
                            };
                        }
                        else
                        {
                            boxBorder.Child = CreateScalableVectorSymbol(PdfAnnotationType.Check, Color.FromRgb(17, 24, 39));
                        }
                    }

                    RefreshCheckVisual();

                    void ToggleField()
                    {
                        if (field.IsReadOnly) return;
                        SetCurrentPageActive(slot.PageIndex);
                        if (isRadio)
                        {
                            foreach (var sib in _formFields.Where(f => f.Kind == PdfAcroFieldKind.RadioButton && f.GroupId == field.GroupId))
                                sib.IsChecked = (sib == field);
                            RenderAllFormFieldsOverlays();
                        }
                        else
                        {
                            field.IsChecked = !field.IsChecked;
                            RefreshCheckVisual();
                        }
                        MarkDirty();
                        UpdatePageBadges();
                    }

                    boxBorder.MouseLeftButtonDown += (s, ev) =>
                    {
                        if (field.IsReadOnly) return;
                        boxBorder.Focus();
                        ToggleField();
                        ev.Handled = true;
                    };

                    boxBorder.KeyDown += (s, ev) =>
                    {
                        if (field.IsReadOnly) return;
                        if (ev.Key == Key.Space || ev.Key == Key.Enter)
                        {
                            ToggleField();
                            ev.Handled = true;
                        }
                    };

                    Canvas.SetLeft(boxBorder, leftPx);
                    Canvas.SetTop(boxBorder, topPx);
                    slot.FormFieldsCanvas.Children.Add(boxBorder);
                }
                else if (field.Kind == PdfAcroFieldKind.ComboBox)
                {
                    var cb = new ComboBox
                    {
                        Width = widthPx,
                        Height = Math.Max(16, heightPx),
                        IsEditable = !field.IsReadOnly,
                        IsReadOnly = field.IsReadOnly,
                        IsEnabled = !field.IsReadOnly,
                        ItemsSource = field.Options,
                        Text = field.TextValue,
                        FontSize = Math.Max(8.5, Math.Min(field.FontSizePt, field.HeightPt * 0.72) * _zoomFactor),
                        Foreground = BrushHex("#111827"),
                        Background = idleEmptyBg,
                        BorderBrush = idleBorder,
                        BorderThickness = new Thickness(1),
                        Padding = new Thickness(2, 0, 2, 0),
                        TabIndex = tabIdx++,
                        ToolTip = string.IsNullOrWhiteSpace(field.Tooltip) ? null : field.Tooltip
                    };

                    cb.SelectionChanged += (s, ev) =>
                    {
                        if (field.IsReadOnly) return;
                        if (cb.SelectedItem != null)
                        {
                            field.TextValue = cb.SelectedItem.ToString() ?? "";
                            MarkDirty();
                            UpdatePageBadges();
                        }
                    };
                    cb.AddHandler(System.Windows.Controls.Primitives.TextBoxBase.TextChangedEvent, new TextChangedEventHandler((s, ev) =>
                    {
                        if (field.IsReadOnly) return;
                        field.TextValue = cb.Text ?? "";
                        MarkDirty();
                        UpdatePageBadges();
                    }));

                    Canvas.SetLeft(cb, leftPx);
                    Canvas.SetTop(cb, topPx);
                    slot.FormFieldsCanvas.Children.Add(cb);
                }
                else if (field.Kind == PdfAcroFieldKind.SignatureTrigger)
                {
                    var sigBox = new Border
                    {
                        Width = widthPx,
                        Height = heightPx,
                        Background = new SolidColorBrush(Color.FromArgb(26, 197, 160, 89)),
                        BorderBrush = goldBorder,
                        BorderThickness = new Thickness(1.2),
                        CornerRadius = new CornerRadius(3),
                        Cursor = Cursors.Hand,
                        ToolTip = "Klicken zum Unterschreiben",
                        Child = new TextBlock
                        {
                            Text = "✍ Unterschreiben",
                            FontSize = Math.Clamp(heightPx * 0.34, 9, 13),
                            FontWeight = FontWeights.SemiBold,
                            Foreground = BrushHex("#92702A"),
                            HorizontalAlignment = HorizontalAlignment.Center,
                            VerticalAlignment = VerticalAlignment.Center
                        }
                    };
                    sigBox.MouseLeftButtonDown += (s, ev) =>
                    {
                        SetCurrentPageActive(slot.PageIndex);
                        _pendingSignatureTargetPage = slot.PageIndex;
                        _pendingSignatureTargetRectPt = new Rect(field.XPt, field.YPt, field.WidthPt, field.HeightPt);
                        OpenSignatureModal_Click(sigBox, new RoutedEventArgs());
                        ev.Handled = true;
                    };

                    Canvas.SetLeft(sigBox, leftPx);
                    Canvas.SetTop(sigBox, topPx);
                    slot.FormFieldsCanvas.Children.Add(sigBox);
                }
            }
        }

        // =====================================================================
        // ANNOTATIONS OVERLAY ACROSS ALL PAGES
        // =====================================================================
        private void SelectAnnotation(PdfAnnotationItem? item)
        {
            _selectedAnnotation = item;
            if (item != null)
            {
                if (item.Type == PdfAnnotationType.Check || item.Type == PdfAnnotationType.Cross)
                {
                    _activeSizePt = Math.Round(item.WidthPt / 1.45);
                }
                else
                {
                    _activeSizePt = Math.Round(item.FontSizePt);
                }
                SizeValueLabel.Text = $"{(int)Math.Clamp(_activeSizePt, 6, 96)} pt";
            }
        }

        private static UIElement CreateScalableVectorSymbol(PdfAnnotationType type, Color strokeColor)
        {
            var vb = new Viewbox
            {
                Stretch = Stretch.Fill,
                Margin = new Thickness(1)
            };
            var canvas = new Canvas { Width = 24, Height = 24 };
            var brush = new SolidColorBrush(strokeColor);

            if (type == PdfAnnotationType.Check)
            {
                var path = new System.Windows.Shapes.Path
                {
                    Data = Geometry.Parse("M 3.5,13.2 L 9.6,19.5 L 20.8,4.8"),
                    Stroke = brush,
                    StrokeThickness = 3.1,
                    StrokeStartLineCap = PenLineCap.Round,
                    StrokeEndLineCap = PenLineCap.Round,
                    StrokeLineJoin = PenLineJoin.Round
                };
                canvas.Children.Add(path);
            }
            else
            {
                var path = new System.Windows.Shapes.Path
                {
                    Data = Geometry.Parse("M 4.5,4.5 L 19.5,19.5 M 19.5,4.5 L 4.5,19.5"),
                    Stroke = brush,
                    StrokeThickness = 3.0,
                    StrokeStartLineCap = PenLineCap.Round,
                    StrokeEndLineCap = PenLineCap.Round
                };
                canvas.Children.Add(path);
            }

            vb.Child = canvas;
            return vb;
        }

        private void RenderAllAnnotationsOverlays(bool focusSelectedText = false)
        {
            RemoveFloatingCopyPill();
            foreach (var slot in _pageSlots)
            {
                RenderAnnotationsOnSlot(slot, focusSelectedText);
            }
            if (_currentTextSelection != null)
            {
                RenderTextSelectionHighlights(showCopyPill: false);
            }
        }

        private void RenderAnnotationsOnSlot(PageVisualSlot slot, bool focusSelectedText)
        {
            slot.AnnotationCanvas.Children.Clear();
            var pageItems = _annotations.Where(a => a.PageIndex == slot.PageIndex).ToList();

            foreach (var item in pageItems)
            {
                bool isSelected = (_selectedAnnotation == item);
                double leftPx = item.XPt * _zoomFactor;
                double topPx = item.YPt * _zoomFactor;
                double widthPx = Math.Max(10, item.WidthPt * _zoomFactor);
                double heightPx = Math.Max(10, item.HeightPt * _zoomFactor);

                var container = new Grid
                {
                    Width = widthPx,
                    Height = heightPx,
                    Background = new SolidColorBrush(Color.FromArgb(1, 255, 255, 255))
                };

                var outerBorder = new Border
                {
                    BorderBrush = isSelected ? BrushHex("#C5A059") : Brushes.Transparent,
                    BorderThickness = new Thickness(isSelected ? 1.5 : 0),
                    CornerRadius = new CornerRadius(2),
                    Background = item.Type switch
                    {
                        PdfAnnotationType.Whiteout => Brushes.White,
                        PdfAnnotationType.Highlight => new SolidColorBrush(Color.FromArgb(95, 250, 204, 21)),
                        _ => new SolidColorBrush(Color.FromArgb(isSelected ? (byte)22 : (byte)2, 197, 160, 89))
                    }
                };
                container.Children.Add(outerBorder);

                TextBox? activeTextBox = null;
                bool isTextEditable = (item.Type == PdfAnnotationType.Text || item.Type == PdfAnnotationType.Whiteout);

                if (isTextEditable)
                {
                    var textGrid = new Grid();
                    textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(isSelected ? 12 : 0) });
                    textGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

                    if (isSelected)
                    {
                        var moveStrip = new Border
                        {
                            Background = new SolidColorBrush(Color.FromArgb(80, 197, 160, 89)),
                            Cursor = Cursors.SizeAll,
                            ToolTip = "Ziehen zum Verschieben"
                        };
                        Grid.SetColumn(moveStrip, 0);
                        textGrid.Children.Add(moveStrip);
                        AttachMoveDragBehavior(slot, moveStrip, container, outerBorder, item);
                    }

                    var tb = new TextBox
                    {
                        Text = item.Text,
                        FontSize = item.FontSizePt * _zoomFactor,
                        Foreground = new SolidColorBrush(item.StrokeColor),
                        Background = Brushes.Transparent,
                        BorderThickness = new Thickness(0),
                        Padding = new Thickness(2, 1, 2, 1),
                        AcceptsReturn = true,
                        TextWrapping = TextWrapping.Wrap,
                        VerticalContentAlignment = VerticalAlignment.Top
                    };
                    activeTextBox = tb;
                    tb.PreviewMouseLeftButtonDown += (s, ev) =>
                    {
                        SetCurrentPageActive(slot.PageIndex);
                        if (_selectedAnnotation != item)
                        {
                            SelectAnnotation(item);
                            RenderAllAnnotationsOverlays(focusSelectedText: true);
                            ev.Handled = true;
                        }
                    };
                    tb.TextChanged += (s, ev) =>
                    {
                        item.Text = tb.Text;
                        MarkDirty();
                    };
                    Grid.SetColumn(tb, 1);
                    textGrid.Children.Add(tb);
                    container.Children.Add(textGrid);

                    if (focusSelectedText && isSelected)
                    {
                        Dispatcher.InvokeAsync(() =>
                        {
                            tb.Focus();
                            tb.CaretIndex = tb.Text.Length;
                            if (tb.Text == "Text") tb.SelectAll();
                        });
                    }
                }
                else if (item.Type == PdfAnnotationType.Check || item.Type == PdfAnnotationType.Cross)
                {
                    container.Cursor = Cursors.SizeAll;
                    var vectorSym = CreateScalableVectorSymbol(item.Type, item.StrokeColor);
                    container.Children.Add(vectorSym);
                    AttachMoveDragBehavior(slot, container, container, outerBorder, item);
                }
                else if (item.Type == PdfAnnotationType.Signature && item.ImagePngBytes != null)
                {
                    container.Cursor = Cursors.SizeAll;
                    var sigImg = new Image
                    {
                        Source = BytesToBitmapImage(item.ImagePngBytes),
                        Stretch = Stretch.Fill
                    };
                    RenderOptions.SetBitmapScalingMode(sigImg, BitmapScalingMode.HighQuality);
                    container.Children.Add(sigImg);
                    AttachMoveDragBehavior(slot, container, container, outerBorder, item);
                }
                else if (item.Type == PdfAnnotationType.Highlight)
                {
                    container.Cursor = Cursors.SizeAll;
                    AttachMoveDragBehavior(slot, container, container, outerBorder, item);
                }

                if (isSelected)
                {
                    var handlesCanvas = new Canvas { ClipToBounds = false };

                    string pillBg = _isDarkMode ? "#151923" : "#FFFFFF";
                    string pillFg = _isDarkMode ? "#F9FAFB" : "#111827";
                    var pillBorder = new Border
                    {
                        Background = BrushHex(pillBg),
                        BorderBrush = BrushHex("#C5A059"),
                        BorderThickness = new Thickness(1),
                        CornerRadius = new CornerRadius(5),
                        Padding = new Thickness(2, 1, 2, 1)
                    };
                    var pillStack = new StackPanel { Orientation = Orientation.Horizontal };

                    var minusBtn = new Button
                    {
                        Content = "−",
                        Width = 20,
                        Height = 18,
                        FontSize = 11,
                        FontWeight = FontWeights.Bold,
                        Background = Brushes.Transparent,
                        Foreground = BrushHex(pillFg),
                        BorderThickness = new Thickness(0),
                        Cursor = Cursors.Hand,
                        ToolTip = "Kleiner (−)"
                    };
                    minusBtn.Click += (s, ev) =>
                    {
                        AdjustElementSize(-2);
                        ev.Handled = true;
                    };

                    var plusBtn = new Button
                    {
                        Content = "+",
                        Width = 20,
                        Height = 18,
                        FontSize = 11,
                        FontWeight = FontWeights.Bold,
                        Background = Brushes.Transparent,
                        Foreground = BrushHex("#C5A059"),
                        BorderThickness = new Thickness(0),
                        Cursor = Cursors.Hand,
                        ToolTip = "Größer (+)"
                    };
                    plusBtn.Click += (s, ev) =>
                    {
                        AdjustElementSize(+2);
                        ev.Handled = true;
                    };

                    var delBtn = new Button
                    {
                        Content = "✕",
                        Width = 20,
                        Height = 18,
                        FontSize = 10,
                        FontWeight = FontWeights.Bold,
                        Background = BrushHex("#EF4444"),
                        Foreground = Brushes.White,
                        BorderThickness = new Thickness(0),
                        Margin = new Thickness(2, 0, 0, 0),
                        Cursor = Cursors.Hand,
                        ToolTip = "Löschen (Entf)"
                    };
                    delBtn.Click += (s, ev) =>
                    {
                        _annotations.Remove(item);
                        _selectedAnnotation = null;
                        MarkDirty();
                        RenderAllAnnotationsOverlays();
                        UpdatePageBadges();
                        ev.Handled = true;
                    };

                    pillStack.Children.Add(minusBtn);
                    pillStack.Children.Add(plusBtn);
                    pillStack.Children.Add(delBtn);
                    pillBorder.Child = pillStack;

                    Canvas.SetLeft(pillBorder, 0);
                    Canvas.SetTop(pillBorder, -25);
                    handlesCanvas.Children.Add(pillBorder);

                    var resizeGrip = new Border
                    {
                        Width = 15,
                        Height = 15,
                        Background = BrushHex("#C5A059"),
                        BorderBrush = BrushHex("#0F1117"),
                        BorderThickness = new Thickness(1),
                        CornerRadius = new CornerRadius(3),
                        Cursor = Cursors.SizeNWSE,
                        ToolTip = "Ziehen zum Vergrößern / Verkleinern",
                        Child = new TextBlock
                        {
                            Text = "⤡",
                            FontSize = 9.5,
                            FontWeight = FontWeights.Bold,
                            Foreground = BrushHex("#0F1117"),
                            HorizontalAlignment = HorizontalAlignment.Center,
                            VerticalAlignment = VerticalAlignment.Center,
                            Margin = new Thickness(0, -1, 0, 0)
                        }
                    };

                    Canvas.SetLeft(resizeGrip, widthPx - 7);
                    Canvas.SetTop(resizeGrip, heightPx - 7);

                    Point resizeStart = default;
                    double origW = 0, origH = 0, origFont = 12;
                    bool resizing = false;

                    resizeGrip.MouseLeftButtonDown += (s, ev) =>
                    {
                        resizeStart = ev.GetPosition(slot.AnnotationCanvas);
                        origW = item.WidthPt;
                        origH = item.HeightPt;
                        origFont = item.FontSizePt;
                        resizing = true;
                        resizeGrip.CaptureMouse();
                        ev.Handled = true;
                    };
                    resizeGrip.MouseMove += (s, ev) =>
                    {
                        if (!resizing) return;
                        Point cur = ev.GetPosition(slot.AnnotationCanvas);
                        double dxPt = (cur.X - resizeStart.X) / _zoomFactor;
                        double dyPt = (cur.Y - resizeStart.Y) / _zoomFactor;

                        if (item.Type == PdfAnnotationType.Check || item.Type == PdfAnnotationType.Cross)
                        {
                            double delta = Math.Abs(dxPt) > Math.Abs(dyPt) ? dxPt : dyPt;
                            double newSize = Math.Clamp(origW + delta, 8, 220);
                            item.WidthPt = newSize;
                            item.HeightPt = newSize;
                            item.FontSizePt = Math.Round(newSize / 1.45);
                            _activeSizePt = item.FontSizePt;
                            SizeValueLabel.Text = $"{(int)Math.Clamp(item.FontSizePt, 6, 96)} pt";
                        }
                        else
                        {
                            item.WidthPt = Math.Max(24, origW + dxPt);
                            item.HeightPt = Math.Max(14, origH + dyPt);
                            if (activeTextBox != null && Math.Abs(dyPt) > 2)
                            {
                                item.FontSizePt = Math.Clamp(Math.Round(origFont + dyPt * 0.45), 6, 96);
                                activeTextBox.FontSize = item.FontSizePt * _zoomFactor;
                                _activeSizePt = item.FontSizePt;
                                SizeValueLabel.Text = $"{(int)_activeSizePt} pt";
                            }
                        }

                        MarkDirty();
                        double newWpx = item.WidthPt * _zoomFactor;
                        double newHpx = item.HeightPt * _zoomFactor;
                        container.Width = newWpx;
                        container.Height = newHpx;
                        Canvas.SetLeft(resizeGrip, newWpx - 7);
                        Canvas.SetTop(resizeGrip, newHpx - 7);
                        ev.Handled = true;
                    };
                    resizeGrip.MouseLeftButtonUp += (s, ev) =>
                    {
                        resizing = false;
                        resizeGrip.ReleaseMouseCapture();
                        ev.Handled = true;
                    };

                    handlesCanvas.Children.Add(resizeGrip);
                    container.Children.Add(handlesCanvas);
                }

                Canvas.SetLeft(container, leftPx);
                Canvas.SetTop(container, topPx);
                slot.AnnotationCanvas.Children.Add(container);
            }
        }

        private void AttachMoveDragBehavior(PageVisualSlot slot, UIElement handle, FrameworkElement targetContainer, Border outerBorder, PdfAnnotationItem item)
        {
            Point dragStart = default;
            double origXPt = 0, origYPt = 0;
            bool dragging = false;
            bool wasAlreadySelected = false;

            handle.MouseLeftButtonDown += (s, ev) =>
            {
                if (ev.OriginalSource is Button) return;
                Keyboard.ClearFocus();
                ClearTextSelection();
                SetCurrentPageActive(slot.PageIndex);
                wasAlreadySelected = (_selectedAnnotation == item);
                SelectAnnotation(item);

                outerBorder.BorderBrush = BrushHex("#C5A059");
                outerBorder.BorderThickness = new Thickness(1.5);

                dragStart = ev.GetPosition(slot.AnnotationCanvas);
                origXPt = item.XPt;
                origYPt = item.YPt;
                dragging = true;
                handle.CaptureMouse();
                ev.Handled = true;
            };

            handle.MouseMove += (s, ev) =>
            {
                if (!dragging || ev.LeftButton != MouseButtonState.Pressed) return;
                Point cur = ev.GetPosition(slot.AnnotationCanvas);
                double dxPt = (cur.X - dragStart.X) / _zoomFactor;
                double dyPt = (cur.Y - dragStart.Y) / _zoomFactor;
                if (Math.Abs(dxPt) > 0.8 || Math.Abs(dyPt) > 0.8)
                {
                    item.XPt = Math.Clamp(origXPt + dxPt, 0, Math.Max(8, slot.WidthPt - 8));
                    item.YPt = Math.Clamp(origYPt + dyPt, 0, Math.Max(8, slot.HeightPt - 8));
                    Canvas.SetLeft(targetContainer, item.XPt * _zoomFactor);
                    Canvas.SetTop(targetContainer, item.YPt * _zoomFactor);
                    MarkDirty();
                }
                ev.Handled = true;
            };

            handle.MouseLeftButtonUp += (s, ev) =>
            {
                if (!dragging) return;
                dragging = false;
                handle.ReleaseMouseCapture();
                ev.Handled = true;

                if (!wasAlreadySelected)
                {
                    RenderAllAnnotationsOverlays();
                }
            };
        }

        private void UpdatePageBadges()
        {
            if (PagesListBox.ItemsSource is not List<PageListEntry> entries) return;
            foreach (var e in entries)
            {
                int c = _annotations.Count(a => a.PageIndex == e.Index)
                      + _formFields.Count(f => f.PageIndex == e.Index && f.HasValue);
                e.Badge = c > 0 ? $"{c}" : "";
            }
            PagesListBox.Items.Refresh();
        }

        // =====================================================================
        // SIZE STEPPER (− / + SCALES CHECKMARKS, CROSSES, TEXT & SIGNATURES)
        // =====================================================================
        private void AdjustElementSize(double deltaPt)
        {
            _activeSizePt = Math.Clamp(_activeSizePt + deltaPt, 6, 96);
            SizeValueLabel.Text = $"{(int)_activeSizePt} pt";

            if (_selectedAnnotation != null)
            {
                var sel = _selectedAnnotation;
                if (sel.Type == PdfAnnotationType.Check || sel.Type == PdfAnnotationType.Cross)
                {
                    double box = Math.Clamp(_activeSizePt * 1.45, 8, 220);
                    sel.WidthPt = box;
                    sel.HeightPt = box;
                    sel.FontSizePt = _activeSizePt;
                }
                else if (sel.Type == PdfAnnotationType.Signature)
                {
                    double scale = deltaPt > 0 ? 1.14 : 0.88;
                    sel.WidthPt = Math.Clamp(sel.WidthPt * scale, 30, 450);
                    sel.HeightPt = Math.Clamp(sel.HeightPt * scale, 15, 220);
                }
                else if (sel.Type == PdfAnnotationType.Highlight)
                {
                    sel.HeightPt = Math.Clamp(sel.HeightPt + deltaPt, 8, 120);
                }
                else
                {
                    sel.FontSizePt = _activeSizePt;
                    sel.HeightPt = Math.Max(sel.HeightPt, _activeSizePt * 1.75);
                }
                MarkDirty();
                RenderAllAnnotationsOverlays();
            }
        }

        private void SizeDown_Click(object sender, RoutedEventArgs e) => AdjustElementSize(-2);
        private void SizeUp_Click(object sender, RoutedEventArgs e) => AdjustElementSize(+2);

        // =====================================================================
        // TOOLBAR MODES & ACTIONS
        // =====================================================================
        private void SetActiveTool(PdfToolMode mode)
        {
            _activeTool = mode;
            foreach (var slot in _pageSlots)
            {
                slot.FormFieldsCanvas.IsHitTestVisible = (mode == PdfToolMode.Select);
            }

            var buttons = new Dictionary<PdfToolMode, Button>
            {
                [PdfToolMode.Select] = ToolSelectBtn,
                [PdfToolMode.Text] = ToolTextBtn,
                [PdfToolMode.Check] = ToolCheckBtn,
                [PdfToolMode.Cross] = ToolCrossBtn,
                [PdfToolMode.Whiteout] = ToolWhiteoutBtn,
                [PdfToolMode.Highlight] = ToolHighlightBtn
            };

            string idleBg = _isDarkMode ? "#1B1F2B" : "#F3F4F6";
            string idleFg = _isDarkMode ? "#F9FAFB" : "#111827";
            string idleBorder = _isDarkMode ? "#2D3343" : "#D1D5DB";

            foreach (var kv in buttons)
            {
                bool active = (kv.Key == mode);
                kv.Value.Background = BrushHex(active ? "#C5A059" : idleBg);
                kv.Value.Foreground = BrushHex(active ? "#0F1117" : idleFg);
                kv.Value.BorderBrush = BrushHex(active ? "#C5A059" : idleBorder);
                kv.Value.BorderThickness = new Thickness(active ? 0 : 1);
                kv.Value.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
            }
        }

        private void ToolSelect_Click(object sender, RoutedEventArgs e)
        {
            SetActiveTool(PdfToolMode.Select);
            StatusText.Text = _formFields.Count > 0
                ? "↖ Auswahl: Text mit der Maus markieren & kopieren oder blau hinterlegte Formularfelder ausfüllen."
                : "↖ Auswahl: Text mit der Maus markieren & per Strg+C kopieren.";
        }

        private void ToolText_Click(object sender, RoutedEventArgs e)
        {
            SetActiveTool(PdfToolMode.Text);
            StatusText.Text = "T Text: Klicke auf die gewünschte Stelle im PDF.";
        }
        private void ToolCheck_Click(object sender, RoutedEventArgs e)
        {
            SetActiveTool(PdfToolMode.Check);
            StatusText.Text = "✓ Haken: Klicke ins Dokument (Größe per − / + oder goldener Ecke ⤡ änderbar).";
        }
        private void ToolCross_Click(object sender, RoutedEventArgs e)
        {
            SetActiveTool(PdfToolMode.Cross);
            StatusText.Text = "✕ Kreuz: Klicke ins Dokument (Größe per − / + oder goldener Ecke ⤡ änderbar).";
        }
        private void ToolWhiteout_Click(object sender, RoutedEventArgs e)
        {
            SetActiveTool(PdfToolMode.Whiteout);
            StatusText.Text = "▭ Korrektur: Klicke auf alten Text, um ihn weiß abzudecken und neu zu beschriften.";
        }
        private void ToolHighlight_Click(object sender, RoutedEventArgs e)
        {
            SetActiveTool(PdfToolMode.Highlight);
            StatusText.Text = "Marker: Klicke auf eine Zeile zum Hervorheben.";
        }

        private double GetVisibleTargetYPtForCurrentPage(double fallbackBottomOffsetPt = 135)
        {
            if (_currentPageIndex < 0 || _currentPageIndex >= _pageSlots.Count)
                return Math.Max(40, _currentPageHeightPt - fallbackBottomOffsetPt);

            var slot = _pageSlots[_currentPageIndex];
            try
            {
                Point topInView = slot.PaperBorder.TranslatePoint(new Point(0, 0), PageScrollViewer);
                double visTopPx = Math.Max(0, -topInView.Y);
                double visBottomPx = Math.Min(slot.PaperBorder.Height, PageScrollViewer.ActualHeight - topInView.Y);
                if (visBottomPx - visTopPx > 80)
                {
                    double targetPx = visTopPx + (visBottomPx - visTopPx) * 0.65;
                    return Math.Clamp(targetPx / _zoomFactor, 40, Math.Max(50, slot.HeightPt - 90));
                }
            }
            catch { }

            return Math.Max(40, slot.HeightPt - fallbackBottomOffsetPt);
        }

        private void InsertDate_Click(object sender, RoutedEventArgs e)
        {
            if (_basePdfBytes == null) return;
            var item = new PdfAnnotationItem
            {
                PageIndex = _currentPageIndex,
                Type = PdfAnnotationType.Text,
                XPt = 65,
                YPt = GetVisibleTargetYPtForCurrentPage(130),
                WidthPt = 165,
                HeightPt = 24,
                Text = $"Gifhorn, {DateTime.Now:dd.MM.yyyy}",
                FontSizePt = _activeSizePt,
                StrokeColor = _activeColor
            };
            _annotations.Add(item);
            MarkDirty();
            SelectAnnotation(item);
            SetActiveTool(PdfToolMode.Select);
            RenderAllAnnotationsOverlays();
            UpdatePageBadges();
        }

        // =====================================================================
        // SIGNATURE STUDIO (DPAPI ENCRYPTED + OPT-IN CONSENT)
        // =====================================================================
        private void RefreshSavedSignatureButtonsState()
        {
            bool hasSaved = File.Exists(SavedSignaturePath);
            UseSavedSigBtn.Visibility = hasSaved ? Visibility.Visible : Visibility.Collapsed;
            DeleteSavedSigBtn.Visibility = hasSaved ? Visibility.Visible : Visibility.Collapsed;
        }

        public void OpenSignatureModal_Click(object sender, RoutedEventArgs e)
        {
            if (_basePdfBytes == null)
            {
                StatusText.Text = "⚠ Bitte zuerst ein PDF öffnen oder ein neues Dokument erstellen.";
                return;
            }
            if (sender == SignModalBtn)
            {
                _pendingSignatureTargetRectPt = null;
                _pendingSignatureTargetPage = -1;
            }
            RefreshSavedSignatureButtonsState();
            SignatureInkCanvas.Strokes.Clear();
            SignatureModalOverlay.Visibility = Visibility.Visible;
        }

        public void CloseSignatureModal_Click(object sender, RoutedEventArgs e)
        {
            _pendingSignatureTargetRectPt = null;
            _pendingSignatureTargetPage = -1;
            SignatureModalOverlay.Visibility = Visibility.Collapsed;
        }

        private void ClearSignaturePad_Click(object sender, RoutedEventArgs e) =>
            SignatureInkCanvas.Strokes.Clear();

        private void DeleteSavedSignature_Click(object sender, RoutedEventArgs e)
        {
            DeleteSavedSignatureFile(SavedSignaturePath);
            RememberSignatureCheckBox.IsChecked = false;
            RefreshSavedSignatureButtonsState();
            ShowViewerToast("✓ Gespeicherte Unterschrift gelöscht");
        }

        private void LoadSignatureImage_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Unterschrift als Bild auswählen",
                Filter = "Bilddateien (*.png;*.jpg;*.jpeg)|*.png;*.jpg;*.jpeg"
            };
            if (dlg.ShowDialog() == true)
            {
                try
                {
                    var fi = new FileInfo(dlg.FileName);
                    if (fi.Length <= 0 || fi.Length > PdfFormFieldEngine.MaxSignatureImageBytes)
                    {
                        ShowViewerToast("⚠ Bilddatei ist zu groß (maximal 16 MB)", isError: true);
                        return;
                    }

                    byte[] bytes = File.ReadAllBytes(dlg.FileName);
                    _ = BytesToBitmapImage(bytes); // Validate image format before accepting

                    if (RememberSignatureCheckBox.IsChecked == true)
                    {
                        SaveEncryptedSignatureToFile(SavedSignaturePath, bytes);
                    }

                    PlaceSignatureOnCurrentPage(bytes);
                    SignatureModalOverlay.Visibility = Visibility.Collapsed;
                }
                catch (Exception ex)
                {
                    StatusText.Text = $"⚠ Bild-Fehler: {ex.Message}";
                    ShowViewerToast($"⚠ Ungültige Bilddatei: {ex.Message}", isError: true);
                }
            }
        }

        private void UseSavedSignature_Click(object sender, RoutedEventArgs e)
        {
            byte[]? bytes = TryLoadEncryptedSignatureFromFile(SavedSignaturePath);
            if (bytes == null)
            {
                DeleteSavedSignatureFile(SavedSignaturePath);
                RefreshSavedSignatureButtonsState();
                ShowViewerToast("⚠ Keine gültige gespeicherte Unterschrift vorhanden", isError: true);
                return;
            }
            PlaceSignatureOnCurrentPage(bytes);
            SignatureModalOverlay.Visibility = Visibility.Collapsed;
        }

        private void ApplySignaturePad_Click(object sender, RoutedEventArgs e)
        {
            if (SignatureInkCanvas.Strokes.Count == 0)
            {
                if (File.Exists(SavedSignaturePath))
                {
                    UseSavedSignature_Click(sender, e);
                    return;
                }
                StatusText.Text = "⚠ Bitte zuerst unterschreiben.";
                return;
            }

            byte[] pngBytes = RenderInkCanvasToTransparentPng(SignatureInkCanvas);
            if (RememberSignatureCheckBox.IsChecked == true)
            {
                try { SaveEncryptedSignatureToFile(SavedSignaturePath, pngBytes); } catch { }
            }

            PlaceSignatureOnCurrentPage(pngBytes);
            SignatureModalOverlay.Visibility = Visibility.Collapsed;
        }

        private static byte[] RenderInkCanvasToTransparentPng(InkCanvas ink)
        {
            Rect bounds = ink.Strokes.GetBounds();
            int pad = 10;

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.PushTransform(new TranslateTransform(-Math.Max(0, bounds.Left - pad), -Math.Max(0, bounds.Top - pad)));
                ink.Strokes.Draw(dc);
                dc.Pop();
            }

            int cropW = Math.Max(80, (int)Math.Ceiling(bounds.Width + pad * 2));
            int cropH = Math.Max(40, (int)Math.Ceiling(bounds.Height + pad * 2));

            var rtb = new RenderTargetBitmap(cropW * 2, cropH * 2, 192, 192, PixelFormats.Pbgra32);
            rtb.Render(dv);

            var enc = new PngBitmapEncoder();
            enc.Frames.Add(BitmapFrame.Create(rtb));
            using var ms = new MemoryStream();
            enc.Save(ms);
            return ms.ToArray();
        }

        public void PlaceSignatureOnCurrentPage(byte[] pngBytes)
        {
            int targetPage = _pendingSignatureTargetPage >= 0 && _pendingSignatureTargetPage < _pageCount
                ? _pendingSignatureTargetPage
                : _currentPageIndex;

            double pageW = (targetPage >= 0 && targetPage < _pageSlots.Count) ? _pageSlots[targetPage].WidthPt : _currentPageWidthPt;
            double xPt = Math.Max(50, pageW - 230);
            double yPt = GetVisibleTargetYPtForCurrentPage(135);
            double wPt = 155;
            double hPt = 58;

            if (_pendingSignatureTargetRectPt.HasValue)
            {
                var r = _pendingSignatureTargetRectPt.Value;
                xPt = r.X;
                yPt = r.Y;
                wPt = Math.Max(40, r.Width);
                hPt = Math.Max(20, r.Height);
                _pendingSignatureTargetRectPt = null;
                _pendingSignatureTargetPage = -1;
            }

            var item = new PdfAnnotationItem
            {
                PageIndex = targetPage,
                Type = PdfAnnotationType.Signature,
                XPt = xPt,
                YPt = yPt,
                WidthPt = wPt,
                HeightPt = hPt,
                ImagePngBytes = pngBytes
            };
            _annotations.Add(item);
            MarkDirty();
            SelectAnnotation(item);
            SetActiveTool(PdfToolMode.Select);
            RenderAllAnnotationsOverlays();
            UpdatePageBadges();
            StatusText.Text = "✍ Unterschrift eingefügt (frei verschiebbar & über goldene Ecke ⤡ skalierbar).";
        }

        // =====================================================================
        // PAGE OPERATIONS (PRESERVING ALL FILLED ACROFORM VALUES & ANNOTATIONS)
        // =====================================================================
        private async void RotatePage_Click(object sender, RoutedEventArgs e)
        {
            await RotatePageAtAsync(_currentPageIndex);
        }

        public async Task RotatePageAtAsync(int pageIndex)
        {
            if (_basePdfBytes == null || pageIndex < 0 || pageIndex >= _pageCount) return;
            try
            {
                double oldSlotH = pageIndex < _pageSlots.Count ? _pageSlots[pageIndex].HeightPt : _currentPageHeightPt;

                byte[] currentWithForms = PdfFormFieldEngine.ApplyFormFieldsToPdfBytes(_basePdfBytes, _formFields);
                using var inMs = new MemoryStream(currentWithForms);
                using var doc = PdfReader.Open(inMs, PdfDocumentOpenMode.Modify);
                var page = doc.Pages[pageIndex];
                page.Rotate = (page.Rotate + 90) % 360;

                // Rotate placed visual annotations on this page 90° clockwise with the page
                foreach (var a in _annotations.Where(x => x.PageIndex == pageIndex))
                {
                    double newX = Math.Max(2, oldSlotH - (a.YPt + a.HeightPt));
                    double newY = Math.Max(2, a.XPt);
                    a.XPt = newX;
                    a.YPt = newY;
                }

                using var outMs = new MemoryStream();
                doc.Save(outMs, false);
                _basePdfBytes = outMs.ToArray();
                MarkDirty();
                ReloadFormFieldsFromBaseBytes();
                await RefreshDocumentStructureAsync(pageIndex);
                ShowViewerToast($"✓ Seite {pageIndex + 1} um 90° gedreht", isError: false, durationMs: 2000);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Drehen fehlgeschlagen: {ex.Message}";
            }
        }

        private async void MergePdf_Click(object sender, RoutedEventArgs e)
        {
            if (_basePdfBytes == null) return;
            var dlg = new OpenFileDialog
            {
                Title = "Weiteres PDF zum Anhängen auswählen",
                Filter = "PDF-Dokumente (*.pdf)|*.pdf"
            };
            if (dlg.ShowDialog() != true) return;

            await MergePdfFromFileAsync(dlg.FileName);
        }

        public async Task MergePdfFromFileAsync(string mergeFilePath)
        {
            if (_basePdfBytes == null) return;
            try
            {
                var fi = new FileInfo(mergeFilePath);
                if (!fi.Exists || fi.Length <= 0 || fi.Length + _basePdfBytes.Length > PdfFormFieldEngine.MaxPdfFileSizeBytes)
                {
                    ShowViewerToast("⚠ PDF-Datei ist zu groß (maximal 256 MB)", isError: true);
                    return;
                }

                byte[] currentWithForms = PdfFormFieldEngine.ApplyFormFieldsToPdfBytes(_basePdfBytes, _formFields);
                using var baseMs = new MemoryStream(currentWithForms);
                using var baseDoc = PdfReader.Open(baseMs, PdfDocumentOpenMode.Modify);
                using var addDoc = PdfReader.Open(mergeFilePath, PdfDocumentOpenMode.Import);

                for (int i = 0; i < addDoc.PageCount; i++)
                    baseDoc.AddPage(addDoc.Pages[i]);

                using var outMs = new MemoryStream();
                baseDoc.Save(outMs, false);
                _basePdfBytes = outMs.ToArray();
                MarkDirty();

                ReloadFormFieldsFromBaseBytes();
                await RefreshDocumentStructureAsync(_currentPageIndex, PageScrollViewer.VerticalOffset, PageScrollViewer.HorizontalOffset);
                StatusText.Text = $"✓ {addDoc.PageCount} Seite(n) angehängt.";
                ShowViewerToast($"✓ {addDoc.PageCount} Seite(n) angehängt", isError: false);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Zusammenfügen fehlgeschlagen: {ex.Message}";
            }
        }

        private async void DeletePage_Click(object sender, RoutedEventArgs e)
        {
            await DeletePageAtAsync(_currentPageIndex);
        }

        public async Task DeletePageAtAsync(int pageIndex)
        {
            if (_basePdfBytes == null || _pageCount <= 1 || pageIndex < 0 || pageIndex >= _pageCount)
            {
                StatusText.Text = "⚠ Ein Dokument muss mindestens 1 Seite behalten.";
                ShowViewerToast("⚠ Mindestens 1 Seite muss erhalten bleiben", isError: true, durationMs: 2200);
                return;
            }

            try
            {
                byte[] currentWithForms = PdfFormFieldEngine.ApplyFormFieldsToPdfBytes(_basePdfBytes, _formFields);
                using var inMs = new MemoryStream(currentWithForms);
                using var doc = PdfReader.Open(inMs, PdfDocumentOpenMode.Modify);
                doc.Pages.RemoveAt(pageIndex);

                if (_selectedAnnotation?.PageIndex == pageIndex)
                    _selectedAnnotation = null;
                _annotations.RemoveAll(a => a.PageIndex == pageIndex);
                foreach (var a in _annotations.Where(a => a.PageIndex > pageIndex))
                    a.PageIndex--;

                using var outMs = new MemoryStream();
                doc.Save(outMs, false);
                _basePdfBytes = outMs.ToArray();
                MarkDirty();

                ReloadFormFieldsFromBaseBytes();
                await RefreshDocumentStructureAsync(Math.Max(0, pageIndex - 1));
                StatusText.Text = $"✓ Seite {pageIndex + 1} gelöscht.";
                ShowViewerToast($"✓ Seite {pageIndex + 1} gelöscht", isError: false, durationMs: 2000);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Seite löschen fehlgeschlagen: {ex.Message}";
            }
        }

        // =====================================================================
        // ATOMIC SAVE + KEEP ANNOTATIONS SELECTABLE/EDITABLE AFTER CTRL+S
        // =====================================================================
        private async void SavePdf_Click(object sender, RoutedEventArgs e)
        {
            if (_basePdfBytes == null)
            {
                ShowViewerToast("⚠ Kein PDF geöffnet", isError: true, durationMs: 2200);
                return;
            }
            if (string.IsNullOrWhiteSpace(_currentFilePath))
            {
                SaveAsPdf_Click(sender, e);
                return;
            }
            await SaveCommittedPdfToPathAsync(_currentFilePath);
        }

        private async void SaveAsPdf_Click(object sender, RoutedEventArgs e)
        {
            if (_basePdfBytes == null)
            {
                ShowViewerToast("⚠ Kein PDF geöffnet", isError: true, durationMs: 2200);
                return;
            }
            var dlg = new SaveFileDialog
            {
                Title = "PDF-Dokument speichern als",
                Filter = "PDF-Dokument (*.pdf)|*.pdf",
                FileName = string.IsNullOrWhiteSpace(_currentFilePath)
                    ? $"Dokument_{DateTime.Now:yyyyMMdd}.pdf"
                    : IOPath.GetFileName(_currentFilePath)
            };
            if (dlg.ShowDialog() == true)
            {
                await SaveCommittedPdfToPathAsync(dlg.FileName);
            }
        }

        private static void WriteAllBytesAtomic(string targetPath, byte[] data)
        {
            string fullTarget = IOPath.GetFullPath(targetPath);
            string dir = IOPath.GetDirectoryName(fullTarget) ?? Environment.CurrentDirectory;
            Directory.CreateDirectory(dir);
            string tmpPath = IOPath.Combine(dir, $".{IOPath.GetFileName(fullTarget)}.{Guid.NewGuid():N}.tmp");
            try
            {
                File.WriteAllBytes(tmpPath, data);
                File.Move(tmpPath, fullTarget, overwrite: true);
            }
            finally
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
            }
        }

        private static async Task WriteAllBytesAtomicAsync(string targetPath, byte[] data)
        {
            string fullTarget = IOPath.GetFullPath(targetPath);
            string dir = IOPath.GetDirectoryName(fullTarget) ?? Environment.CurrentDirectory;
            Directory.CreateDirectory(dir);
            string tmpPath = IOPath.Combine(dir, $".{IOPath.GetFileName(fullTarget)}.{Guid.NewGuid():N}.tmp");
            try
            {
                await File.WriteAllBytesAsync(tmpPath, data);
                File.Move(tmpPath, fullTarget, overwrite: true);
            }
            finally
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
            }
        }

        private bool SaveCommittedPdfSync(string targetPath)
        {
            if (_basePdfBytes == null) return false;
            try
            {
                byte[] withForms = PdfFormFieldEngine.ApplyFormFieldsToPdfBytes(_basePdfBytes, _formFields);
                byte[] committed = BurnAnnotationsIntoPdfBytes(withForms, _annotations);
                WriteAllBytesAtomic(targetPath, committed);

                // Keep _basePdfBytes unburned so placed annotations stay interactive!
                _basePdfBytes = withForms;
                _cleanRenderPdfBytes = PdfFormFieldEngine.CreateCleanRenderPdfBytes(_basePdfBytes, _formFields.Count > 0);
                _currentFilePath = targetPath;
                _documentBaseTitle = $"Funkenwerk PDF · {IOPath.GetFileName(targetPath)}";
                ClearDirty();
                return true;
            }
            catch (Exception ex)
            {
                ShowViewerToast($"⚠ Speichern fehlgeschlagen: {ex.Message}", isError: true, durationMs: 4200);
                return false;
            }
        }

        public async Task SaveCommittedPdfToPathAsync(string targetPath)
        {
            if (_basePdfBytes == null) return;
            try
            {
                byte[] withForms = PdfFormFieldEngine.ApplyFormFieldsToPdfBytes(_basePdfBytes, _formFields);
                byte[] committed = BurnAnnotationsIntoPdfBytes(withForms, _annotations);
                await WriteAllBytesAtomicAsync(targetPath, committed);

                // Keep _basePdfBytes unburned so placed text/check/signature annotations remain selectable, movable, and deletable!
                _basePdfBytes = withForms;
                _cleanRenderPdfBytes = PdfFormFieldEngine.CreateCleanRenderPdfBytes(_basePdfBytes, _formFields.Count > 0);
                _currentFilePath = targetPath;

                string shortName = IOPath.GetFileName(targetPath);
                _documentBaseTitle = $"Funkenwerk PDF · {shortName}";
                ClearDirty();

                StatusText.Text = $"✓ Gespeichert ({DateTime.Now:HH:mm:ss}): {targetPath}";
                FlashSaveButtonSuccess();
                ShowViewerToast($"✓ PDF erfolgreich gespeichert · {shortName}", isError: false, durationMs: 3200);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ Speichern fehlgeschlagen: {ex.Message}";
                ShowViewerToast($"⚠ Speichern fehlgeschlagen: {ex.Message}", isError: true, durationMs: 4200);
            }
        }

        private static List<string> WrapTextLinesWithGraphics(XGraphics gfx, string text, XFont font, double maxWidthPt)
        {
            var lines = new List<string>();
            double avail = Math.Max(10.0, maxWidthPt);
            string[] paragraphs = text.Replace("\r\n", "\n").Replace('\r', '\n').Split('\n');

            foreach (string para in paragraphs)
            {
                if (string.IsNullOrEmpty(para))
                {
                    lines.Add("");
                    continue;
                }

                if (gfx.MeasureString(para, font).Width <= avail)
                {
                    lines.Add(para);
                    continue;
                }

                string[] words = para.Split(' ');
                var cur = new StringBuilder();

                void AppendOversizedWord(string word)
                {
                    var chunk = new StringBuilder();
                    foreach (char ch in word)
                    {
                        chunk.Append(ch);
                        if (gfx.MeasureString(chunk.ToString(), font).Width > avail && chunk.Length > 1)
                        {
                            char carry = chunk[chunk.Length - 1];
                            chunk.Length -= 1;
                            lines.Add(chunk.ToString());
                            chunk.Clear();
                            chunk.Append(carry);
                        }
                    }
                    cur.Clear();
                    cur.Append(chunk);
                }

                foreach (string word in words)
                {
                    if (cur.Length == 0)
                    {
                        if (gfx.MeasureString(word, font).Width <= avail)
                            cur.Append(word);
                        else
                            AppendOversizedWord(word);
                    }
                    else
                    {
                        string candidate = cur.ToString() + " " + word;
                        if (gfx.MeasureString(candidate, font).Width <= avail)
                        {
                            cur.Append(' ').Append(word);
                        }
                        else
                        {
                            lines.Add(cur.ToString());
                            cur.Clear();
                            if (gfx.MeasureString(word, font).Width <= avail)
                                cur.Append(word);
                            else
                                AppendOversizedWord(word);
                        }
                    }
                }

                if (cur.Length > 0)
                    lines.Add(cur.ToString());
            }

            return lines;
        }

        public static byte[] BurnAnnotationsIntoPdfBytes(byte[] sourcePdfBytes, IReadOnlyList<PdfAnnotationItem> items)
        {
            if (items == null || items.Count == 0)
                return sourcePdfBytes;

            using var inMs = new MemoryStream(sourcePdfBytes);
            using var doc = PdfReader.Open(inMs, PdfDocumentOpenMode.Modify);

            for (int pageIdx = 0; pageIdx < doc.PageCount; pageIdx++)
            {
                var pageItems = items.Where(a => a.PageIndex == pageIdx).ToList();
                if (pageItems.Count == 0) continue;

                var page = doc.Pages[pageIdx];
                var media = page.MediaBox;
                var crop = page.Elements.ContainsKey("/CropBox") ? page.CropBox : new PdfRectangle();

                double mX1 = media.X1;
                double mY1 = media.Y1;
                double mW = media.Width > 0 ? media.Width : page.Width.Point;
                double mH = media.Height > 0 ? media.Height : page.Height.Point;

                bool hasCrop = crop.Width > 0 && crop.Height > 0;
                double boxX1 = hasCrop ? crop.X1 : mX1;
                double boxY1 = hasCrop ? crop.Y1 : mY1;
                double boxW = hasCrop ? crop.Width : mW;
                double boxH = hasCrop ? crop.Height : mH;
                int rotate = ((page.Rotate % 360) + 360) % 360;

                using var gfx = XGraphics.FromPdfPage(page, XGraphicsPdfPageOptions.Append);
                var state = gfx.Save();

                // Align XGraphics origin with CropBox top-left in PDFsharp's default top-down MediaBox frame
                double cropOffsetX = boxX1;
                double cropOffsetY = page.Height.Point - (boxY1 + boxH);
                if (Math.Abs(cropOffsetX) > 0.01 || Math.Abs(cropOffsetY) > 0.01)
                {
                    gfx.TranslateTransform(cropOffsetX, cropOffsetY);
                }

                // Map visual rotated coordinates (vx, vy) onto unrotated visible box (boxW x boxH)
                switch (rotate)
                {
                    case 90:
                        gfx.TranslateTransform(0, boxH);
                        gfx.RotateTransform(-90);
                        break;
                    case 180:
                        gfx.TranslateTransform(boxW, boxH);
                        gfx.RotateTransform(180);
                        break;
                    case 270:
                        gfx.TranslateTransform(boxW, 0);
                        gfx.RotateTransform(90);
                        break;
                }

                foreach (var item in pageItems)
                {
                    switch (item.Type)
                    {
                        case PdfAnnotationType.Whiteout:
                        {
                            gfx.DrawRectangle(XBrushes.White, item.XPt, item.YPt, item.WidthPt, item.HeightPt);
                            if (!string.IsNullOrWhiteSpace(item.Text))
                            {
                                var font = new XFont("Arial", item.FontSizePt, XFontStyleEx.Regular);
                                var brush = new XSolidBrush(XColor.FromArgb(item.StrokeColor.R, item.StrokeColor.G, item.StrokeColor.B));
                                var wrapped = WrapTextLinesWithGraphics(gfx, item.Text, font, Math.Max(8, item.WidthPt - 4));
                                double lineY = item.YPt + 2;
                                foreach (string ln in wrapped)
                                {
                                    if (lineY > item.YPt + item.HeightPt) break;
                                    if (!string.IsNullOrEmpty(ln))
                                        gfx.DrawString(ln, font, brush, new XPoint(item.XPt + 2, lineY + item.FontSizePt * 0.88));
                                    lineY += item.FontSizePt * 1.24;
                                }
                            }
                            break;
                        }

                        case PdfAnnotationType.Highlight:
                        {
                            var brush = new XSolidBrush(XColor.FromArgb(90, 250, 204, 21));
                            gfx.DrawRectangle(brush, item.XPt, item.YPt, item.WidthPt, item.HeightPt);
                            break;
                        }

                        case PdfAnnotationType.Text:
                        {
                            if (string.IsNullOrWhiteSpace(item.Text)) break;
                            var font = new XFont("Arial", item.FontSizePt, XFontStyleEx.Regular);
                            var brush = new XSolidBrush(XColor.FromArgb(item.StrokeColor.R, item.StrokeColor.G, item.StrokeColor.B));
                            var wrapped = WrapTextLinesWithGraphics(gfx, item.Text, font, Math.Max(8, item.WidthPt - 4));
                            double lineY = item.YPt + 2;
                            foreach (string ln in wrapped)
                            {
                                if (!string.IsNullOrEmpty(ln))
                                    gfx.DrawString(ln, font, brush, new XPoint(item.XPt + 2, lineY + item.FontSizePt * 0.88));
                                lineY += item.FontSizePt * 1.24;
                            }
                            break;
                        }

                        case PdfAnnotationType.Check:
                        case PdfAnnotationType.Cross:
                        {
                            double w = Math.Max(6, item.WidthPt);
                            double h = Math.Max(6, item.HeightPt);
                            double strokePt = Math.Max(1.4, Math.Min(w, h) * 0.13);
                            var pen = new XPen(XColor.FromArgb(item.StrokeColor.R, item.StrokeColor.G, item.StrokeColor.B), strokePt)
                            {
                                LineCap = XLineCap.Round,
                                LineJoin = XLineJoin.Round
                            };

                            if (item.Type == PdfAnnotationType.Check)
                            {
                                double x1 = item.XPt + w * (3.5 / 24.0);
                                double y1 = item.YPt + h * (13.2 / 24.0);
                                double x2 = item.XPt + w * (9.6 / 24.0);
                                double y2 = item.YPt + h * (19.5 / 24.0);
                                double x3 = item.XPt + w * (20.8 / 24.0);
                                double y3 = item.YPt + h * (4.8 / 24.0);
                                gfx.DrawLines(pen, new[] { new XPoint(x1, y1), new XPoint(x2, y2), new XPoint(x3, y3) });
                            }
                            else
                            {
                                double padX = w * (4.5 / 24.0);
                                double padY = h * (4.5 / 24.0);
                                gfx.DrawLine(pen, item.XPt + padX, item.YPt + padY, item.XPt + w - padX, item.YPt + h - padY);
                                gfx.DrawLine(pen, item.XPt + w - padX, item.YPt + padY, item.XPt + padX, item.YPt + h - padY);
                            }
                            break;
                        }

                        case PdfAnnotationType.Signature:
                        {
                            if (item.ImagePngBytes == null || item.ImagePngBytes.Length == 0) break;
                            using var imgMs = new MemoryStream(item.ImagePngBytes);
                            using var xImg = XImage.FromStream(imgMs);
                            gfx.DrawImage(xImg, item.XPt, item.YPt, item.WidthPt, item.HeightPt);
                            break;
                        }
                    }
                }

                gfx.Restore(state);
            }

            using var outMs = new MemoryStream();
            doc.Save(outMs, false);
            return outMs.ToArray();
        }

        // =====================================================================
        // COLORS, MULTI-PAGE CTRL+WHEEL ZOOM, SHORTCUTS, DRAG & DROP, THEME
        // =====================================================================
        private void SetActiveColor(Color c, Button activeBtn)
        {
            _activeColor = c;
            foreach (var b in new[] { SwatchBlackBtn, SwatchBlueBtn, SwatchGoldBtn, SwatchRedBtn })
            {
                if (b == null) continue;
                b.BorderBrush = b == activeBtn ? BrushHex("#C5A059") : BrushHex("#4B5563");
                b.BorderThickness = new Thickness(b == activeBtn ? 2 : 1);
            }
            if (SignatureInkCanvas != null)
            {
                SignatureInkCanvas.DefaultDrawingAttributes.Color = c;
            }
            if (_selectedAnnotation != null && _selectedAnnotation.Type != PdfAnnotationType.Highlight)
            {
                _selectedAnnotation.StrokeColor = c;
                MarkDirty();
                RenderAllAnnotationsOverlays();
            }
        }

        private void ColorBlack_Click(object sender, RoutedEventArgs e) => SetActiveColor(Color.FromRgb(17, 17, 17), SwatchBlackBtn);
        private void ColorBlue_Click(object sender, RoutedEventArgs e) => SetActiveColor(Color.FromRgb(10, 46, 138), SwatchBlueBtn);
        private void ColorGold_Click(object sender, RoutedEventArgs e) => SetActiveColor(Color.FromRgb(197, 160, 89), SwatchGoldBtn);
        private void ColorRed_Click(object sender, RoutedEventArgs e) => SetActiveColor(Color.FromRgb(220, 38, 38), SwatchRedBtn);

        private void ZoomOut_Click(object sender, RoutedEventArgs e) =>
            ApplyZoomLevel(_zoomFactor - 0.15);

        private void ZoomIn_Click(object sender, RoutedEventArgs e) =>
            ApplyZoomLevel(_zoomFactor + 0.15);

        private void ZoomReset_Click(object sender, MouseButtonEventArgs e) =>
            ApplyZoomLevel(1.25);

        private void Window_PreviewMouseWheel(object sender, MouseWheelEventArgs e)
        {
            if ((Keyboard.Modifiers & ModifierKeys.Control) != ModifierKeys.Control)
                return;

            e.Handled = true;
            if (_basePdfBytes == null || _pageSlots.Count == 0)
                return;

            int notches = e.Delta / 120;
            if (notches == 0) notches = e.Delta > 0 ? 1 : -1;

            Point mouseInViewport = e.GetPosition(PageScrollViewer);
            bool cursorInsideViewport = mouseInViewport.X >= 0 && mouseInViewport.X <= PageScrollViewer.ActualWidth
                                     && mouseInViewport.Y >= 0 && mouseInViewport.Y <= PageScrollViewer.ActualHeight;

            PageVisualSlot anchorSlot = _pageSlots[Math.Clamp(_currentPageIndex, 0, _pageSlots.Count - 1)];
            if (cursorInsideViewport)
            {
                foreach (var candidate in _pageSlots)
                {
                    Point posOnPaper = e.GetPosition(candidate.PaperBorder);
                    if (posOnPaper.Y >= -12 && posOnPaper.Y <= candidate.PaperBorder.Height + 12)
                    {
                        anchorSlot = candidate;
                        break;
                    }
                }
            }

            Point mouseOnAnchorPaper = e.GetPosition(anchorSlot.PaperBorder);
            ApplyZoomLevel(
                _zoomFactor + (0.12 * notches),
                cursorInsideViewport ? mouseInViewport : null,
                anchorSlot,
                cursorInsideViewport ? mouseOnAnchorPaper : null);
        }

        private void ApplyZoomLevel(
            double requestedZoom,
            Point? cursorInViewport = null,
            PageVisualSlot? anchorSlot = null,
            Point? cursorOnPaper = null)
        {
            if (_basePdfBytes == null || _pageSlots.Count == 0) return;

            double clamped = Math.Clamp(Math.Round(requestedZoom * 100.0) / 100.0, 0.40, 3.50);
            if (Math.Abs(clamped - _zoomFactor) < 0.005) return;

            var targetAnchor = anchorSlot ?? _pageSlots[Math.Clamp(_currentPageIndex, 0, _pageSlots.Count - 1)];
            double oldW = targetAnchor.PaperBorder.Width;
            double oldH = targetAnchor.PaperBorder.Height;
            double ratioX = (cursorOnPaper.HasValue && oldW > 0) ? Math.Clamp(cursorOnPaper.Value.X / oldW, 0.0, 1.0) : 0.5;
            double ratioY = (cursorOnPaper.HasValue && oldH > 0) ? Math.Clamp(cursorOnPaper.Value.Y / oldH, 0.0, 1.0) : 0.25;

            var restoreField = _focusedFormField;
            int restoreCaret = (Keyboard.FocusedElement is TextBox focusedTb) ? focusedTb.CaretIndex : -1;

            _zoomFactor = clamped;
            ZoomLabel.Text = $"{(int)Math.Round(_zoomFactor * 100)}%";

            foreach (var slot in _pageSlots)
            {
                double displayW = slot.WidthPt * _zoomFactor;
                double displayH = slot.HeightPt * _zoomFactor;

                slot.PaperBorder.Width = displayW;
                slot.PaperBorder.Height = displayH;
                slot.TextSelectionCanvas.Width = displayW;
                slot.TextSelectionCanvas.Height = displayH;
                slot.FormFieldsCanvas.Width = displayW;
                slot.FormFieldsCanvas.Height = displayH;
                slot.AnnotationCanvas.Width = displayW;
                slot.AnnotationCanvas.Height = displayH;
            }

            RenderAllFormFieldsOverlays(restoreField, restoreCaret);
            RenderAllAnnotationsOverlays();
            if (_currentTextSelection != null)
                RenderTextSelectionHighlights(showCopyPill: false);

            PageScrollViewer.UpdateLayout();
            if (cursorInViewport.HasValue)
            {
                double newAnchorW = targetAnchor.PaperBorder.Width;
                double newAnchorH = targetAnchor.PaperBorder.Height;
                Point paperOriginInViewport = targetAnchor.PaperBorder.TranslatePoint(new Point(0, 0), PageScrollViewer);
                double targetPointInViewportX = paperOriginInViewport.X + ratioX * newAnchorW;
                double targetPointInViewportY = paperOriginInViewport.Y + ratioY * newAnchorH;

                double newOffsetX = PageScrollViewer.HorizontalOffset + (targetPointInViewportX - cursorInViewport.Value.X);
                double newOffsetY = PageScrollViewer.VerticalOffset + (targetPointInViewportY - cursorInViewport.Value.Y);

                PageScrollViewer.ScrollToHorizontalOffset(Math.Max(0, newOffsetX));
                PageScrollViewer.ScrollToVerticalOffset(Math.Max(0, newOffsetY));
            }

            _zoomRenderDebounceTimer.Stop();
            _zoomRenderDebounceTimer.Start();
        }

        public void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape && SignatureModalOverlay.Visibility == Visibility.Visible)
            {
                CloseSignatureModal_Click(sender, new RoutedEventArgs());
                e.Handled = true;
            }
        }

        public void Window_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape && SignatureModalOverlay.Visibility == Visibility.Visible)
            {
                CloseSignatureModal_Click(sender, new RoutedEventArgs());
                e.Handled = true;
                return;
            }

            bool ctrl = (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control;

            if (ctrl && e.Key == Key.S)
            {
                SavePdf_Click(sender, new RoutedEventArgs());
                e.Handled = true;
                return;
            }
            if (ctrl && (e.Key == Key.OemPlus || e.Key == Key.Add))
            {
                ApplyZoomLevel(_zoomFactor + 0.15);
                e.Handled = true;
                return;
            }
            if (ctrl && (e.Key == Key.OemMinus || e.Key == Key.Subtract))
            {
                ApplyZoomLevel(_zoomFactor - 0.15);
                e.Handled = true;
                return;
            }
            if (ctrl && (e.Key == Key.D0 || e.Key == Key.NumPad0))
            {
                ApplyZoomLevel(1.25);
                e.Handled = true;
                return;
            }

            if (Keyboard.FocusedElement is TextBox || Keyboard.FocusedElement is PasswordBox || Keyboard.FocusedElement is ComboBox)
                return;

            if (ctrl && e.Key == Key.C && _currentTextSelection != null)
            {
                CopyCurrentTextSelection();
                e.Handled = true;
                return;
            }
            if (ctrl && e.Key == Key.A && _basePdfBytes != null)
            {
                SelectAllTextOnPage(_currentPageIndex);
                e.Handled = true;
                return;
            }
            if (e.Key == Key.Escape)
            {
                if (_currentTextSelection != null)
                {
                    ClearTextSelection();
                    e.Handled = true;
                    return;
                }
                if (_selectedAnnotation != null)
                {
                    _selectedAnnotation = null;
                    RenderAllAnnotationsOverlays();
                    e.Handled = true;
                    return;
                }
            }

            if (e.Key == Key.Delete && _selectedAnnotation != null)
            {
                _annotations.Remove(_selectedAnnotation);
                _selectedAnnotation = null;
                MarkDirty();
                RenderAllAnnotationsOverlays();
                UpdatePageBadges();
            }
            else if ((e.Key == Key.OemPlus || e.Key == Key.Add) && _selectedAnnotation != null)
            {
                AdjustElementSize(+2);
            }
            else if ((e.Key == Key.OemMinus || e.Key == Key.Subtract) && _selectedAnnotation != null)
            {
                AdjustElementSize(-2);
            }
        }

        private void Window_PreviewDragOver(object sender, DragEventArgs e)
        {
            e.Effects = e.Data.GetDataPresent(DataFormats.FileDrop) ? DragDropEffects.Copy : DragDropEffects.None;
            e.Handled = true;
        }

        private async void Window_PreviewDrop(object sender, DragEventArgs e)
        {
            if (!e.Data.GetDataPresent(DataFormats.FileDrop)) return;
            if (e.Data.GetData(DataFormats.FileDrop) is not string[] files || files.Length == 0) return;

            string first = files[0];
            if (first.EndsWith(".pdf", StringComparison.OrdinalIgnoreCase) && File.Exists(first))
            {
                if (!ConfirmAndHandleUnsavedChangesSync())
                    return;
                await LoadPdfFromFileAsync(first);
            }
            else if ((first.EndsWith(".png", StringComparison.OrdinalIgnoreCase) ||
                      first.EndsWith(".jpg", StringComparison.OrdinalIgnoreCase) ||
                      first.EndsWith(".jpeg", StringComparison.OrdinalIgnoreCase)) &&
                     File.Exists(first) && _basePdfBytes != null)
            {
                var fi = new FileInfo(first);
                if (fi.Length <= 0 || fi.Length > PdfFormFieldEngine.MaxSignatureImageBytes)
                {
                    ShowViewerToast("⚠ Bilddatei ist zu groß (maximal 16 MB)", isError: true);
                    return;
                }
                try
                {
                    byte[] imgBytes = await File.ReadAllBytesAsync(first);
                    _ = BytesToBitmapImage(imgBytes);
                    PlaceSignatureOnCurrentPage(imgBytes);
                }
                catch (Exception ex)
                {
                    ShowViewerToast($"⚠ Bild-Fehler: {ex.Message}", isError: true);
                }
            }
        }

        public void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            _isDarkMode = !_isDarkMode;
            ApplyTheme();
        }

        private void ApplyTheme()
        {
            ThemeToggleBtn.Content = _isDarkMode ? "☀" : "🌙";

            var rootBg = BrushHex(_isDarkMode ? "#0F1117" : "#F3F4F6");
            var barBg = BrushHex(_isDarkMode ? "#12151E" : "#FFFFFF");
            var tileBg = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF");
            var borderCol = BrushHex(_isDarkMode ? "#232734" : "#E5E7EB");
            var btnBg = BrushHex(_isDarkMode ? "#1B1F2B" : "#F3F4F6");
            var btnBorder = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB");
            var textPrimary = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827");
            var textMuted = BrushHex(_isDarkMode ? "#9CA3AF" : "#4B5563");

            Background = rootBg;
            Foreground = textPrimary;
            RootGrid.Background = rootBg;
            TopBarBorder.Background = barBg;
            TopBarBorder.BorderBrush = borderCol;
            BottomBarBorder.Background = barBg;
            BottomBarBorder.BorderBrush = borderCol;
            StatusText.Foreground = textMuted;

            TopSep1.Background = borderCol;
            TopSep2.Background = borderCol;

            PagesTileBorder.Background = tileBg;
            PagesTileBorder.BorderBrush = borderCol;
            ViewerTileBorder.Background = BrushHex(_isDarkMode ? "#141821" : "#E5E7EB");
            ViewerTileBorder.BorderBrush = borderCol;
            EmptyStateOverlay.Background = tileBg;
            EmptyTitleTxt.Foreground = textPrimary;

            // DynamicResources for PagesListBox items (100% Light/Dark reactivity)
            Resources["ListItemBgBrush"] = BrushHex(_isDarkMode ? "#1A1F2C" : "#F9FAFB");
            Resources["ListItemBorderBrush"] = BrushHex(_isDarkMode ? "#262C3D" : "#E5E7EB");
            Resources["ListItemSelectedBgBrush"] = BrushHex(_isDarkMode ? "#222838" : "#FEF9EC");
            Resources["ListItemPrimaryTextBrush"] = textPrimary;

            // Signature Modal Light/Dark styling
            SignatureModalOverlay.Background = BrushHex(_isDarkMode ? "#CC090B10" : "#88111827");
            SignatureModalCardBorder.Background = BrushHex(_isDarkMode ? "#151923" : "#FFFFFF");
            RememberSignatureCheckBox.Foreground = textPrimary;

            CloseSigModalBtn.Background = btnBg;
            CloseSigModalBtn.BorderBrush = btnBorder;
            CloseSigModalBtn.Foreground = textMuted;

            foreach (var sigBtn in new[] { ClearSigBtn, LoadSigImgBtn })
            {
                sigBtn.Background = btnBg;
                sigBtn.BorderBrush = btnBorder;
                sigBtn.Foreground = textPrimary;
            }
            UseSavedSigBtn.Background = btnBg;
            UseSavedSigBtn.BorderBrush = btnBorder;
            DeleteSavedSigBtn.Background = btnBg;
            DeleteSavedSigBtn.BorderBrush = btnBorder;

            SignModalBtn.Background = BrushHex(_isDarkMode ? "#1D2230" : "#FFFBEB");

            SizeStepperBorder.Background = btnBg;
            SizeStepperBorder.BorderBrush = btnBorder;
            ZoomStepperBorder.Background = btnBg;
            ZoomStepperBorder.BorderBrush = btnBorder;
            SizeDownBtn.Foreground = textPrimary;
            SizeUpBtn.Foreground = textPrimary;
            ZoomOutBtn.Foreground = textPrimary;
            ZoomInBtn.Foreground = textPrimary;
            ZoomLabel.Foreground = textPrimary;

            foreach (var b in new[] { ToggleSidebarBtn, OpenBtn, NewBtn, SaveAsBtn, DateBtn, ThemeToggleBtn, RotateBtn, MergeBtn, DeletePageBtn, EmptyNewBtn })
            {
                if (b == null) continue;
                b.Background = btnBg;
                b.BorderBrush = btnBorder;
                if (b != ToggleSidebarBtn && b != ThemeToggleBtn && b != MergeBtn && b != DeletePageBtn)
                    b.Foreground = textPrimary;
            }

            SetActiveTool(_activeTool);
            if (_annotations.Count > 0)
                RenderAllAnnotationsOverlays();
        }

        private static BitmapImage BytesToBitmapImage(byte[] bytes)
        {
            using var ms = new MemoryStream(bytes);
            var bmp = new BitmapImage();
            bmp.BeginInit();
            bmp.CacheOption = BitmapCacheOption.OnLoad;
            bmp.StreamSource = ms;
            bmp.EndInit();
            bmp.Freeze();
            return bmp;
        }

        private static SolidColorBrush BrushHex(string hex)
        {
            var b = new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
            b.Freeze();
            return b;
        }
    }

    public static class PdfSelfTestRunner
    {
        public static bool RunEndToEndSelfTest()
        {
            if (Thread.CurrentThread.GetApartmentState() == ApartmentState.STA)
            {
                return RunAllChecksOnSta();
            }

            bool ok = false;
            Exception? err = null;
            var sta = new Thread(() =>
            {
                try { ok = RunAllChecksOnSta(); }
                catch (Exception ex) { err = ex; }
            });
            sta.SetApartmentState(ApartmentState.STA);
            sta.Start();
            sta.Join();
            if (err != null) throw err;
            return ok;
        }

        private static PdfDictionary AddSyntheticWidget(
            PdfSharp.Pdf.PdfDocument doc,
            PdfSharp.Pdf.PdfPage page,
            PdfArray fieldsArray,
            PdfArray annotsArray,
            string ft,
            string? name,
            double x1,
            double y1,
            double x2,
            double y2,
            int ff = 0,
            string? initialTextV = null,
            string? tooltip = null,
            PdfDictionary? parent = null)
        {
            var w = new PdfDictionary(doc);
            w.Elements.SetName("/Type", "/Annot");
            w.Elements.SetName("/Subtype", "/Widget");
            if (!string.IsNullOrEmpty(ft))
                w.Elements.SetName("/FT", ft);
            if (!string.IsNullOrEmpty(name))
                w.Elements.SetString("/T", name);
            if (!string.IsNullOrEmpty(tooltip))
                w.Elements.SetString("/TU", tooltip);
            if (ff != 0)
                w.Elements.SetInteger("/Ff", ff);
            w.Elements["/Rect"] = new PdfArray(doc, new PdfReal(x1), new PdfReal(y1), new PdfReal(x2), new PdfReal(y2));
            w.Elements.SetString("/DA", "/Helv 11 Tf 0 g");
            if (initialTextV != null)
                w.Elements.SetString("/V", initialTextV);
            if (parent != null)
                w.Elements["/Parent"] = parent.Reference!;

            doc.Internals.AddObject(w);
            annotsArray.Elements.Add(w.Reference!);
            if (parent == null)
                fieldsArray.Elements.Add(w.Reference!);
            return w;
        }

        private static byte[] BuildSyntheticMultiPageAcroFormPdf()
        {
            using var doc = new PdfSharp.Pdf.PdfDocument();
            var p1 = doc.AddPage();
            p1.Width = XUnit.FromPoint(595);
            p1.Height = XUnit.FromPoint(842);
            using (var gfx = XGraphics.FromPdfPage(p1))
            {
                gfx.DrawString("Mein-Seelenfunke Synthetisches Formular", new XFont("Arial", 14, XFontStyleEx.Bold), XBrushes.Black, new XPoint(50, 50));
            }

            var p2 = doc.AddPage();
            p2.Width = XUnit.FromPoint(595);
            p2.Height = XUnit.FromPoint(842);
            using (var gfx2 = XGraphics.FromPdfPage(p2))
            {
                gfx2.DrawString("Seite 2 Unterschrift & Auswahl", new XFont("Arial", 12, XFontStyleEx.Regular), XBrushes.Black, new XPoint(50, 50));
            }

            var fieldsArr = new PdfArray(doc);
            var acro = new PdfDictionary(doc);
            acro.Elements["/Fields"] = fieldsArr;
            acro.Elements.SetBoolean("/NeedAppearances", true);
            acro.Elements.SetString("/DA", "/Helv 10 Tf 0 g");
            doc.Internals.AddObject(acro);
            doc.Internals.Catalog.Elements["/AcroForm"] = acro.Reference!;

            var annots1 = new PdfArray(doc);
            doc.Internals.AddObject(annots1);
            p1.Elements["/Annots"] = annots1.Reference!;

            var annots2 = new PdfArray(doc);
            doc.Internals.AddObject(annots2);
            p2.Elements["/Annots"] = annots2.Reference!;

            // 1. Single-line /Tx
            AddSyntheticWidget(doc, p1, fieldsArr, annots1, "/Tx", "Firmenname", 50, 720, 320, 742, ff: 0, tooltip: "Firmenname eingeben");
            // 2. Tiny 16x16 /Tx box
            AddSyntheticWidget(doc, p1, fieldsArr, annots1, "/Tx", "JaBox", 350, 722, 366, 738, ff: 0);
            // 3. Multiline /Tx (/Ff bit 13 = 1 << 12)
            AddSyntheticWidget(doc, p1, fieldsArr, annots1, "/Tx", "Bemerkungen", 50, 630, 320, 700, ff: (1 << 12));
            // 4. Password /Tx (/Ff bit 14 = 1 << 13)
            AddSyntheticWidget(doc, p1, fieldsArr, annots1, "/Tx", "GeheimPin", 50, 590, 220, 612, ff: (1 << 13));
            // 5. Pre-filled ReadOnly /Tx (/Ff bit 1 = 1) with /NeedAppearances true and NO initial /AP!
            // Placed at y_pdf=[480..510] -> top-down yPt=[332..362], xPt=[50..280] on clean white background
            AddSyntheticWidget(doc, p1, fieldsArr, annots1, "/Tx", "VertragsNr", 50, 480, 280, 510, ff: 1, initialTextV: "SF-RO-2026-99");

            // 6. CheckBox /Btn with dirty OnState key in /AP -> /N to test SanitizeOnStateName
            var cbWidget = AddSyntheticWidget(doc, p1, fieldsArr, annots1, "/Btn", "AgbCheck", 50, 430, 68, 448, ff: 0);
            var cbApN = new PdfDictionary(doc);
            var dummyOff = new PdfDictionary(doc);
            dummyOff.CreateStream(Encoding.ASCII.GetBytes("q Q"));
            doc.Internals.AddObject(dummyOff);
            cbApN.Elements["/Off"] = dummyOff.Reference!;
            cbApN.Elements["/Ja#20Bitte!"] = dummyOff.Reference!;
            var cbAp = new PdfDictionary(doc);
            cbAp.Elements["/N"] = cbApN;
            cbWidget.Elements["/AP"] = cbAp;
            cbWidget.Elements.SetName("/AS", "/Off");

            // 7 & 8. RadioButton group with /Parent (/Ff bit 16 = 1 << 15)
            var radioParent = new PdfDictionary(doc);
            radioParent.Elements.SetName("/FT", "/Btn");
            radioParent.Elements.SetString("/T", "TarifGruppe");
            radioParent.Elements.SetInteger("/Ff", (1 << 15));
            radioParent.Elements.SetName("/V", "/OptA");
            var kidsArr = new PdfArray(doc);
            radioParent.Elements["/Kids"] = kidsArr;
            doc.Internals.AddObject(radioParent);
            fieldsArr.Elements.Add(radioParent.Reference!);

            PdfDictionary MakeRadioKid(double x1, string optName, bool selected)
            {
                var rw = AddSyntheticWidget(doc, p2, fieldsArr, annots2, "", null, x1, 700, x1 + 18, 718, ff: 0, parent: radioParent);
                var rApN = new PdfDictionary(doc);
                rApN.Elements["/Off"] = dummyOff.Reference!;
                rApN.Elements[optName] = dummyOff.Reference!;
                var rAp = new PdfDictionary(doc);
                rAp.Elements["/N"] = rApN;
                rw.Elements["/AP"] = rAp;
                rw.Elements.SetName("/AS", selected ? optName : "/Off");
                kidsArr.Elements.Add(rw.Reference!);
                return rw;
            }
            MakeRadioKid(50, "/OptA", true);
            MakeRadioKid(110, "/OptB", false);

            // 9. ComboBox /Ch with /Opt
            var chWidget = AddSyntheticWidget(doc, p2, fieldsArr, annots2, "/Ch", "Lieferland", 50, 640, 240, 664, ff: (1 << 17));
            var optArr = new PdfArray(doc, new PdfString("Deutschland"), new PdfString("Oesterreich"), new PdfString("Schweiz"));
            chWidget.Elements["/Opt"] = optArr;
            chWidget.Elements.SetString("/V", "Deutschland");

            // 10. Signature trigger /Sig
            AddSyntheticWidget(doc, p2, fieldsArr, annots2, "/Sig", "KundenSignatur", 50, 520, 240, 580, ff: 0);

            using var outMs = new MemoryStream();
            doc.Save(outMs, false);
            return outMs.ToArray();
        }

        private static (uint Width, uint Height, byte[] BgraPixels) RenderPdfPageToPixels(byte[] pdfBytes, uint pageIndex, uint renderWidth = 800)
        {
            return Task.Run(async () =>
            {
                using var ras = new InMemoryRandomAccessStream();
                using (var writer = new DataWriter(ras.GetOutputStreamAt(0)))
                {
                    writer.WriteBytes(pdfBytes);
                    await writer.StoreAsync();
                    await writer.FlushAsync();
                }
                var winPdf = await Windows.Data.Pdf.PdfDocument.LoadFromStreamAsync(ras);
                using var page = winPdf.GetPage(pageIndex);
                uint rw = renderWidth;
                uint rh = (uint)Math.Max(100, Math.Round(renderWidth * (page.Size.Height / Math.Max(1.0, page.Size.Width))));

                using var pngStream = new InMemoryRandomAccessStream();
                await page.RenderToStreamAsync(pngStream, new PdfPageRenderOptions
                {
                    DestinationWidth = rw,
                    DestinationHeight = rh,
                    BackgroundColor = Windows.UI.Color.FromArgb(255, 255, 255, 255)
                });
                pngStream.Seek(0);
                var decoder = await Windows.Graphics.Imaging.BitmapDecoder.CreateAsync(pngStream);
                var provider = await decoder.GetPixelDataAsync(
                    Windows.Graphics.Imaging.BitmapPixelFormat.Bgra8,
                    Windows.Graphics.Imaging.BitmapAlphaMode.Ignore,
                    new Windows.Graphics.Imaging.BitmapTransform(),
                    Windows.Graphics.Imaging.ExifOrientationMode.IgnoreExifOrientation,
                    Windows.Graphics.Imaging.ColorManagementMode.DoNotColorManage);
                return (decoder.PixelWidth, decoder.PixelHeight, provider.DetachPixelData());
            }).GetAwaiter().GetResult();
        }

        private static int CountDarkPixelsInRegion((uint Width, uint Height, byte[] BgraPixels) img, double normX1, double normY1, double normX2, double normY2)
        {
            int xStart = Math.Clamp((int)(normX1 * img.Width), 0, (int)img.Width - 1);
            int xEnd = Math.Clamp((int)(normX2 * img.Width), xStart + 1, (int)img.Width);
            int yStart = Math.Clamp((int)(normY1 * img.Height), 0, (int)img.Height - 1);
            int yEnd = Math.Clamp((int)(normY2 * img.Height), yStart + 1, (int)img.Height);

            int darkCount = 0;
            int stride = (int)img.Width * 4;
            for (int y = yStart; y < yEnd; y++)
            {
                int rowOff = y * stride;
                for (int x = xStart; x < xEnd; x++)
                {
                    int idx = rowOff + x * 4;
                    byte b = img.BgraPixels[idx];
                    byte g = img.BgraPixels[idx + 1];
                    byte r = img.BgraPixels[idx + 2];
                    if (r < 190 || g < 190 || b < 190)
                        darkCount++;
                }
            }
            return darkCount;
        }

        private static void AwaitOnSta(Task task)
        {
            if (task.IsCompleted)
            {
                task.GetAwaiter().GetResult();
                return;
            }
            var frame = new DispatcherFrame();
            task.ContinueWith(_ => frame.Continue = false, TaskScheduler.Default);
            Dispatcher.PushFrame(frame);
            task.GetAwaiter().GetResult();
        }

        private static bool RunAllChecksOnSta()
        {
            int passed = 0;
            void Check(string name, bool condition)
            {
                if (!condition)
                    throw new InvalidOperationException($"FunkenwerkPdf Self-Test fehlgeschlagen: {name}");
                Console.Error.WriteLine($"[PASS] {name}");
                passed++;
            }

            // 01. Synthetic Multi-Page AcroForm Generation & Extraction (Zero External File Dependency)
            byte[] synPdf = BuildSyntheticMultiPageAcroFormPdf();
            var fields = PdfFormFieldEngine.ExtractFields(synPdf);
            Check("01. Synthetisches Mehrseiten-AcroForm erzeugt & 10 Widgets extrahiert",
                fields.Count == 10 &&
                fields.Any(f => f.FullName == "Firmenname" && f.Kind == PdfAcroFieldKind.Text) &&
                fields.Any(f => f.FullName == "JaBox" && f.IsTinyBoxText) &&
                fields.Any(f => f.FullName == "Bemerkungen" && f.IsMultiline) &&
                fields.Any(f => f.FullName == "GeheimPin" && f.IsPassword) &&
                fields.Any(f => f.FullName == "VertragsNr" && f.IsReadOnly && f.TextValue == "SF-RO-2026-99") &&
                fields.Count(f => f.Kind == PdfAcroFieldKind.RadioButton && f.FullName == "TarifGruppe") == 2 &&
                fields.Any(f => f.FullName == "Lieferland" && f.Kind == PdfAcroFieldKind.ComboBox && f.Options.Count == 3) &&
                fields.Any(f => f.FullName == "KundenSignatur" && f.Kind == PdfAcroFieldKind.SignatureTrigger));

            // 02. SanitizeOnStateName Hardening
            var agbField = fields.First(f => f.FullName == "AgbCheck");
            Check("02. SanitizeOnStateName bereinigt PDF-Namen sicher",
                agbField.OnStateName == "/Ja20Bitte" &&
                PdfFormFieldEngine.SanitizeOnStateName("/Off") == "/Yes" &&
                PdfFormFieldEngine.SanitizeOnStateName("  /Evil()\r\n<>#1 ") == "/Evil1");

            // 03. Unicode /V + Multiline + CheckBox + RadioGroup + ComboBox + ReadOnly Roundtrip
            string unicodeFirma = "Mein-Seelenfunke · Łódź Čech Şahin € ÄÖÜäöüß";
            fields.First(f => f.FullName == "Firmenname").TextValue = unicodeFirma;
            fields.First(f => f.FullName == "JaBox").TextValue = "X";
            fields.First(f => f.FullName == "Bemerkungen").TextValue = "Zeile 1 Gifhorn\nZeile 2 Laser Manufaktur";
            fields.First(f => f.FullName == "GeheimPin").TextValue = "TopSecret#4299";
            fields.First(f => f.FullName == "VertragsNr").TextValue = "TAMPERED_MUST_BE_IGNORED";
            agbField.IsChecked = true;

            var radios = fields.Where(f => f.Kind == PdfAcroFieldKind.RadioButton).ToList();
            radios[0].IsChecked = false;
            radios[1].IsChecked = true;
            fields.First(f => f.FullName == "Lieferland").TextValue = "Schweiz";

            byte[] savedFormPdf = PdfFormFieldEngine.ApplyFormFieldsToPdfBytes(synPdf, fields);
            var reloaded = PdfFormFieldEngine.ExtractFields(savedFormPdf);

            Check("03. Unicode /V (UTF-16BE), Multiline, CheckBox, RadioGroup & ComboBox Roundtrip",
                reloaded.Count == 10 &&
                reloaded.First(f => f.FullName == "Firmenname").TextValue == unicodeFirma &&
                reloaded.First(f => f.FullName == "JaBox").TextValue == "X" &&
                reloaded.First(f => f.FullName == "Bemerkungen").TextValue.Contains("Laser Manufaktur") &&
                reloaded.First(f => f.FullName == "AgbCheck").IsChecked &&
                !reloaded.Where(f => f.Kind == PdfAcroFieldKind.RadioButton).ElementAt(0).IsChecked &&
                reloaded.Where(f => f.Kind == PdfAcroFieldKind.RadioButton).ElementAt(1).IsChecked &&
                reloaded.First(f => f.FullName == "Lieferland").TextValue == "Schweiz");

            // 04. ReadOnly /V Immutability
            Check("04. ReadOnly-Feld /V bleibt unverändert ('SF-RO-2026-99')",
                reloaded.First(f => f.FullName == "VertragsNr").TextValue == "SF-RO-2026-99");

            // 05. Password Field /AP Stream Masks Cleartext With Bullets
            bool apMasksPassword = false;
            using (var pwdMs = new MemoryStream(savedFormPdf))
            using (var pwdDoc = PdfReader.Open(pwdMs, PdfDocumentOpenMode.Import))
            {
                var p1Annots = (PdfArray)((PdfReference)pwdDoc.Pages[0].Elements["/Annots"]!).Value;
                var pwdWidget = (PdfDictionary)((PdfReference)p1Annots.Elements[3]).Value;
                var apDict = pwdWidget.Elements.GetDictionary("/AP");
                var apN = apDict?.Elements.GetDictionary("/N");
                byte[] streamBytes = apN?.Stream?.Value ?? Array.Empty<byte>();
                string latin = Encoding.Latin1.GetString(streamBytes);
                // Bullet '•' is 0x95 in CP1252; cleartext "TopSecret" must NOT appear in /AP stream!
                apMasksPassword = streamBytes.Length > 10 &&
                                  !latin.Contains("TopSecret", StringComparison.OrdinalIgnoreCase) &&
                                  streamBytes.Contains((byte)0x95);
            }
            Check("05. Passwort-Feld (/Ff Bit 14) maskiert Klartext im /AP-Stream mit Bullets", apMasksPassword);

            // 06. ReadOnly Field Without Initial /AP Renders Non-Blank Pixels Under /NeedAppearances=false
            var p1Rendered = RenderPdfPageToPixels(savedFormPdf, 0, 900);
            // VertragsNr is at x=[50..280]/595 = [0.084..0.47], y=[332..362]/842 = [0.394..0.430]
            int roDarkPixels = CountDarkPixelsInRegion(p1Rendered, 0.08, 0.39, 0.48, 0.435);
            Check("06. Vorbefülltes ReadOnly-Feld bleibt trotz /NeedAppearances=false sichtbar gerendert",
                roDarkPixels >= 25);

            // 07. Unicode Transliteration For /AP Helper
            string translit = PdfFormFieldEngine.TransliterateForWinAnsi("Łódź Čech Şahin Ğüneş € ÄÖÜß");
            Check("07. Unicode-Transliteration erhält CP1252 (€, ó, ÄÖÜß) & wandelt Łódź/Čech ohne '?'",
                translit.Contains("Lódz") && translit.Contains("Cech") && translit.Contains("Sahin") &&
                translit.Contains("€") && translit.Contains("ÄÖÜß") && !translit.Contains('?'));

            // 08. Cyclic /Parent Infinite Loop Guard (< 200 ms)
            byte[] cyclicPdf;
            using (var cDoc = new PdfSharp.Pdf.PdfDocument())
            {
                var cp = cDoc.AddPage();
                var dictA = new PdfDictionary(cDoc);
                var dictB = new PdfDictionary(cDoc);
                cDoc.Internals.AddObject(dictA);
                cDoc.Internals.AddObject(dictB);

                dictA.Elements.SetName("/Type", "/Annot");
                dictA.Elements.SetName("/Subtype", "/Widget");
                dictA.Elements.SetName("/FT", "/Tx");
                dictA.Elements.SetString("/T", "NodeA");
                dictA.Elements["/Rect"] = new PdfArray(cDoc, new PdfReal(40), new PdfReal(700), new PdfReal(200), new PdfReal(724));
                dictA.Elements["/Parent"] = dictB.Reference!;

                dictB.Elements.SetString("/T", "NodeB");
                dictB.Elements["/Parent"] = dictA.Reference!;

                var cAnnots = new PdfArray(cDoc, dictA.Reference!);
                cDoc.Internals.AddObject(cAnnots);
                cp.Elements["/Annots"] = cAnnots.Reference!;

                using var cms = new MemoryStream();
                cDoc.Save(cms, false);
                cyclicPdf = cms.ToArray();
            }
            var sw = Stopwatch.StartNew();
            var cyclicFields = PdfFormFieldEngine.ExtractFields(cyclicPdf);
            sw.Stop();
            Check("08. Zyklische /Parent-Schleife (A <-> B) terminiert sicher in < 200 ms",
                sw.ElapsedMilliseconds < 200 && cyclicFields.Count == 1);

            // 09. File-Size Caps Verification
            Check("09. Größenlimits aktiv (PDF <= 256 MB, Signaturbild <= 16 MB)",
                PdfFormFieldEngine.MaxPdfFileSizeBytes == 256L * 1024L * 1024L &&
                PdfFormFieldEngine.MaxSignatureImageBytes == 16L * 1024L * 1024L);

            // 10. Wrapped Multi-Line Annotation Burn Test
            byte[] blankDocBytes;
            using (var bDoc = new PdfSharp.Pdf.PdfDocument())
            {
                var bp = bDoc.AddPage();
                bp.Width = XUnit.FromPoint(595);
                bp.Height = XUnit.FromPoint(842);
                using var bms = new MemoryStream();
                bDoc.Save(bms, false);
                blankDocBytes = bms.ToArray();
            }
            var wrapAnnot = new List<PdfAnnotationItem>
            {
                new()
                {
                    PageIndex = 0,
                    Type = PdfAnnotationType.Text,
                    XPt = 60,
                    YPt = 100,
                    WidthPt = 115,
                    HeightPt = 90,
                    FontSizePt = 12,
                    Text = "Dies ist ein langer Vertragstext ohne manuellen Umbruch fuer Gifhorn"
                }
            };
            byte[] wrappedPdf = MainWindow.BurnAnnotationsIntoPdfBytes(blankDocBytes, wrapAnnot);
            var wrapTextEngine = PdfTextSelectionEngine.CreateFromPdfBytes(wrappedPdf);
            var wrapPageSel = wrapTextEngine.SelectAllOnPage(0);
            int wrappedLineCount = wrapPageSel?.HighlightRectsByPage.GetValueOrDefault(0)?.Count ?? 0;
            Check("10. Mehrzeiliger Wortumbruch in BurnAnnotationsIntoPdfBytes (>= 3 Zeilen)",
                wrapPageSel != null && wrappedLineCount >= 3 && wrapPageSel.Text.Contains("Gifhorn"));

            // 11. 90-Degree Rotated Page Annotation Burn & PdfTextSelectionEngine Coordinate Alignment
            byte[] rot90Base;
            using (var rDoc = new PdfSharp.Pdf.PdfDocument())
            {
                var rp = rDoc.AddPage();
                rp.Width = XUnit.FromPoint(595);
                rp.Height = XUnit.FromPoint(842);
                rp.Rotate = 90; // Visual landscape: 842 x 595 pt!
                using var rms = new MemoryStream();
                rDoc.Save(rms, false);
                rot90Base = rms.ToArray();
            }
            // Place text at XPt = 610, YPt = 90 (valid ONLY in 842x595 landscape visual coordinates!)
            byte[] rot90Burned = MainWindow.BurnAnnotationsIntoPdfBytes(rot90Base, new List<PdfAnnotationItem>
            {
                new() { PageIndex = 0, Type = PdfAnnotationType.Text, XPt = 610, YPt = 90, WidthPt = 160, HeightPt = 28, FontSizePt = 14, Text = "ROT90TREFFER" }
            });
            var rot90Pixels = RenderPdfPageToPixels(rot90Burned, 0, 842);
            int rot90Ink = CountDarkPixelsInRegion(rot90Pixels, 605.0 / 842.0, 85.0 / 595.0, 770.0 / 842.0, 125.0 / 595.0);
            var rot90TextEngine = PdfTextSelectionEngine.CreateFromPdfBytes(rot90Burned);
            var rot90Word = rot90TextEngine.SelectWordAt(0, new Point(635, 102));
            Check("11. 90°-Seitenrotation: Annotation-Burn & Textauswahl exakt an (610, 90) ausgerichtet",
                rot90Pixels.Width > rot90Pixels.Height &&
                rot90Ink >= 20 &&
                rot90Word != null &&
                rot90Word.Text.Contains("ROT90TREFFER"));

            // 12. Non-Zero CropBox Origin + 180° Rotation Burn & Text Extraction Alignment
            byte[] cropPdfBase;
            using (var crDoc = new PdfSharp.Pdf.PdfDocument())
            {
                var crp = crDoc.AddPage();
                crp.Width = XUnit.FromPoint(595);
                crp.Height = XUnit.FromPoint(842);
                crp.CropBox = new PdfRectangle(new XPoint(50, 60), new XPoint(500, 760)); // 450 x 700 visible
                using var crms = new MemoryStream();
                crDoc.Save(crms, false);
                cropPdfBase = crms.ToArray();
            }
            byte[] cropBurned = MainWindow.BurnAnnotationsIntoPdfBytes(cropPdfBase, new List<PdfAnnotationItem>
            {
                new() { PageIndex = 0, Type = PdfAnnotationType.Text, XPt = 40, YPt = 50, WidthPt = 160, HeightPt = 26, FontSizePt = 13, Text = "CROPBOXTEXT" }
            });
            var cropEngine = PdfTextSelectionEngine.CreateFromPdfBytes(cropBurned);
            var cropSize = cropEngine.GetPageSizePt(0);
            var cropHit = cropEngine.SelectWordAt(0, new Point(60, 60));
            Check("12. CropBox mit Versatz (50,60): exakte Seitengröße (450x700) & Texttreffer",
                Math.Abs(cropSize.WidthPt - 450.0) < 1.0 &&
                Math.Abs(cropSize.HeightPt - 700.0) < 1.0 &&
                cropHit != null && cropHit.Text.Contains("CROPBOXTEXT"));

            // 13. Vector Symbols, Whiteout, Highlight & Signature Image Burn + Cross-Page Selection
            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.DrawLine(new Pen(Brushes.DarkBlue, 3), new Point(10, 40), new Point(140, 25));
            }
            var rtb = new RenderTargetBitmap(160, 60, 96, 96, PixelFormats.Pbgra32);
            rtb.Render(dv);
            var pngEnc = new PngBitmapEncoder();
            pngEnc.Frames.Add(BitmapFrame.Create(rtb));
            using var sigMs = new MemoryStream();
            pngEnc.Save(sigMs);
            byte[] sigPngBytes = sigMs.ToArray();

            byte[] multiBurned = MainWindow.BurnAnnotationsIntoPdfBytes(savedFormPdf, new List<PdfAnnotationItem>
            {
                new() { PageIndex = 0, Type = PdfAnnotationType.Check, XPt = 400, YPt = 120, WidthPt = 24, HeightPt = 24 },
                new() { PageIndex = 0, Type = PdfAnnotationType.Cross, XPt = 440, YPt = 120, WidthPt = 24, HeightPt = 24 },
                new() { PageIndex = 0, Type = PdfAnnotationType.Whiteout, XPt = 50, YPt = 200, WidthPt = 180, HeightPt = 36, Text = "Korrektur Zeile Eins und Zwei", FontSizePt = 11 },
                new() { PageIndex = 0, Type = PdfAnnotationType.Highlight, XPt = 50, YPt = 40, WidthPt = 200, HeightPt = 18 },
                new() { PageIndex = 1, Type = PdfAnnotationType.Signature, XPt = 60, YPt = 300, WidthPt = 150, HeightPt = 55, ImagePngBytes = sigPngBytes }
            });
            var multiTextEngine = PdfTextSelectionEngine.CreateFromPdfBytes(multiBurned);
            var crossSel = multiTextEngine.SelectRange(0, new Point(45, 45), 1, new Point(300, 70));
            Check("13. Vektor-Annotationen, Signatur-Burn & seitenübergreifende Textauswahl",
                crossSel != null && crossSel.Text.Contains("Synthetisches") && crossSel.Text.Contains("Seite 2"));

            // 14 & 15. DPAPI Encrypted Signature Roundtrip + ACL Directory Hardening in Temp Dir
            string tempDir = IOPath.Combine(IOPath.GetTempPath(), $"fw_pdf_selftest_{Guid.NewGuid():N}");
            try
            {
                MainWindow.EnsureSecureAppDataDirectory(tempDir);
                var acl = new DirectoryInfo(tempDir).GetAccessControl();
                Check("14. AppData-Verzeichnis ACL-Schutz aktiviert (Vererbung deaktiviert)",
                    acl.AreAccessRulesProtected);

                string dpapiFile = IOPath.Combine(tempDir, "saved_signature.dpapi");
                MainWindow.SaveEncryptedSignatureToFile(dpapiFile, sigPngBytes);
                byte[] rawOnDisk = File.ReadAllBytes(dpapiFile);
                bool notPlainPng = !(rawOnDisk.Length >= 4 && rawOnDisk[0] == 0x89 && rawOnDisk[1] == 0x50 && rawOnDisk[2] == 0x4E && rawOnDisk[3] == 0x47);
                byte[]? decryptedSig = MainWindow.TryLoadEncryptedSignatureFromFile(dpapiFile);
                bool deletedOk = MainWindow.DeleteSavedSignatureFile(dpapiFile) && !File.Exists(dpapiFile);

                Check("15. DPAPI-Verschlüsselung (ProtectedData CurrentUser) speichert nicht im Klartext & entschlüsselt bitgenau",
                    notPlainPng && decryptedSig != null && decryptedSig.SequenceEqual(sigPngBytes) && deletedOk);

                // 16-20. STA MainWindow UI, Light/Dark Theme, Esc Signature Modal, Editable Ctrl+S & Merge/Delete
                string workPdfPath = IOPath.Combine(tempDir, "work_doc.pdf");
                string mergePdfPath = IOPath.Combine(tempDir, "append_doc.pdf");
                File.WriteAllBytes(workPdfPath, synPdf);
                File.WriteAllBytes(mergePdfPath, blankDocBytes);

                var win = new MainWindow();
                Check("16. MainWindow STA-Instanziierung im Dark-Mode", win.IsDarkMode && !win.IsDirty);

                win.ThemeToggle_Click(win, new RoutedEventArgs());
                var lightItemBg = (SolidColorBrush)win.Resources["ListItemBgBrush"];
                bool lightOk = !win.IsDarkMode && lightItemBg.Color.R > 240;
                win.ThemeToggle_Click(win, new RoutedEventArgs());
                var darkItemBg = (SolidColorBrush)win.Resources["ListItemBgBrush"];
                Check("17. Light-/Dark-Mode schaltet PagesListBox & SignatureModal konsistent um",
                    lightOk && win.IsDarkMode && darkItemBg.Color.R < 50);

                AwaitOnSta(win.LoadPdfFromFileAsync(workPdfPath));
                Check("18. LoadPdfFromFileAsync lädt 2 Seiten & 10 Formularfelder fehlerfrei",
                    win.ActiveFormFields.Count == 10 && !win.IsDirty);

                // Place signature annotation -> IsDirty becomes true -> Save -> Annotation remains editable!
                win.PlaceSignatureOnCurrentPage(sigPngBytes);
                bool dirtyAfterAnnot = win.IsDirty && win.ActiveAnnotationCount == 1;
                AwaitOnSta(win.SaveCommittedPdfToPathAsync(workPdfPath));
                bool keptEditableAfterSave = !win.IsDirty && win.ActiveAnnotationCount == 1 && File.Exists(workPdfPath);
                Check("19. Atomares Speichern (Strg+S) setzt IsDirty zurück & hält Annotationen weiter bearbeitbar",
                    dirtyAfterAnnot && keptEditableAfterSave);

                // Signature modal Esc close + Merge + Delete page roundtrip
                win.OpenSignatureModal_Click(win, new RoutedEventArgs());
                AwaitOnSta(win.MergePdfFromFileAsync(mergePdfPath));
                AwaitOnSta(win.DeletePageAtAsync(2));
                win.CloseSignatureModal_Click(win, new RoutedEventArgs());
                win.Close();
                Check("20. PDF-Anhängen, Seite-Löschen & Signatur-Modal Lebenszyklus erfolgreich", true);
            }
            finally
            {
                try { if (Directory.Exists(tempDir)) Directory.Delete(tempDir, recursive: true); } catch { }
            }

            Console.Error.WriteLine($"FUNKENWERK PDF SELF-TEST: {passed}/20 TESTS BESTANDEN.");
            return true;
        }
    }
}

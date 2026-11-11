using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Threading;
using Microsoft.Win32;
using IOPath = System.IO.Path;

namespace FunkenwerkDrucker
{
    public partial class MainWindow : Window
    {
        private readonly FunkenwerkDruckerSettings _settings;
        private readonly List<DocumentPageItem> _pages = new();
        private List<DiscoveredPrinterInfo> _printers = new();
        private DiscoveredPrinterInfo? _selectedPrinter;

        private WorkspaceMode _mode = WorkspaceMode.Print;
        private bool _isDarkMode = true;
        private bool _sidebarVisible = true;
        private int _currentPageIndex = 0;
        private double _zoomFactor = 1.0;

        private int _copies = 1;
        private PrintColorMode _printColorMode = PrintColorMode.Color;
        private PrintDuplexMode _duplexMode = PrintDuplexMode.OneSided;
        private PrintPaperFormat _paperFormat = PrintPaperFormat.A4;
        private PrintOrientationMode _orientationMode = PrintOrientationMode.Auto;
        private PrintScaleMode _scaleMode = PrintScaleMode.FitPrintableArea;
        private PrintPageRangeMode _pageRangeMode = PrintPageRangeMode.All;

        private ScanSourceMode _scanSource = ScanSourceMode.Flatbed;
        private int _scanDpi = 300;
        private ScanColorMode _scanColor = ScanColorMode.Color;
        private ScanProtocolMode _scanProtocol = ScanProtocolMode.Auto;

        private bool _isCropping = false;
        private bool _isDraggingCrop = false;
        private Point _cropDragStart;
        private Rect _pendingCanvasCropRect;

        private CancellationTokenSource? _busyCts;
        private readonly DispatcherTimer _toastTimer;
        private bool _suppressListEvents = false;
        private string _loadedDocumentTitle = "Funkenwerk Dokument";

        private const string AccountingReceiptsUncDir = @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\storage\app\buchhaltung\receipts";
        private const string FunkenwerkPdfExePath = @"C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk PDF\FunkenwerkPdf.exe";

        public MainWindow(string? initialFilePath = null, bool startInScanMode = false)
        {
            InitializeComponent();

            _settings = FunkenwerkDruckerSettings.Load();
            _isDarkMode = _settings.IsDarkMode;
            _scanDpi = (_settings.ScanDpi == 150 || _settings.ScanDpi == 300 || _settings.ScanDpi == 600) ? _settings.ScanDpi : 300;
            _scanSource = _settings.LastScanSource;
            _scanColor = _settings.LastScanColor;
            _printColorMode = _settings.LastPrintColor;
            _duplexMode = _settings.LastDuplex;
            _paperFormat = _settings.LastPaperFormat;
            _scaleMode = _settings.LastScaleMode;

            CustomIpTextBox.Text = _settings.CustomPrinterIp ?? "";

            double maxW = SystemParameters.WorkArea.Width * 0.95;
            double maxH = SystemParameters.WorkArea.Height * 0.92;
            if (Width > maxW) Width = Math.Max(MinWidth, maxW);
            if (Height > maxH) Height = Math.Max(MinHeight, maxH);

            _toastTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(3100) };
            _toastTimer.Tick += (s, e) =>
            {
                _toastTimer.Stop();
                ViewerToastBorder.Visibility = Visibility.Collapsed;
            };

            SizeChanged += MainWindow_SizeChanged;

            ApplyTheme();
            if (startInScanMode)
                SetWorkspaceMode(WorkspaceMode.Scan);
            else
                SetWorkspaceMode(WorkspaceMode.Print);

            UpdateAllOptionButtonsVisuals();
            RefreshDocumentView();

            Loaded += async (s, e) =>
            {
                await RefreshPrintersAndQueueAsync(silent: true);
                if (!string.IsNullOrWhiteSpace(initialFilePath) && File.Exists(initialFilePath))
                {
                    await LoadFilesIntoWorkspaceAsync(new[] { initialFilePath }, append: false);
                }
            };
        }

        // =====================================================================
        // TOAST & BUSY OVERLAY
        // =====================================================================
        private void ShowToast(string message, bool isError = false, int durationMs = 3200)
        {
            // Strictly enforce Rule 3.1: Never emit " – "
            string clean = message.Replace(" – ", " · ");
            ViewerToastText.Text = clean;
            ViewerToastBorder.BorderBrush = BrushHex(isError ? "#EF4444" : "#C5A059");
            ViewerToastBorder.Background = BrushHex(isError ? "#F52A1215" : (_isDarkMode ? "#F2151923" : "#F5FFFFFF"));
            ViewerToastText.Foreground = BrushHex(isError ? "#FCA5A5" : (_isDarkMode ? "#F9FAFB" : "#111827"));
            ViewerToastBorder.Visibility = Visibility.Visible;

            _toastTimer.Stop();
            _toastTimer.Interval = TimeSpan.FromMilliseconds(durationMs);
            _toastTimer.Start();
        }

        private CancellationToken BeginBusy(string title, string sub)
        {
            _busyCts?.Cancel();
            _busyCts = new CancellationTokenSource();
            BusyOverlayTitleTxt.Text = title;
            BusyOverlaySubTxt.Text = sub;
            BusyOverlayBorder.Visibility = Visibility.Visible;
            return _busyCts.Token;
        }

        private void EndBusy()
        {
            BusyOverlayBorder.Visibility = Visibility.Collapsed;
            _busyCts = null;
        }

        private void CancelBusyOperation_Click(object sender, RoutedEventArgs e)
        {
            try { _busyCts?.Cancel(); } catch { }
            EndBusy();
            ShowToast("Vorgang abgebrochen");
        }

        // =====================================================================
        // LIGHT / DARK THEME ENGINE (100 % CONSISTENT)
        // =====================================================================
        private static SolidColorBrush BrushHex(string hex)
        {
            var c = (Color)ColorConverter.ConvertFromString(hex);
            var b = new SolidColorBrush(c);
            b.Freeze();
            return b;
        }

        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            _isDarkMode = !_isDarkMode;
            _settings.IsDarkMode = _isDarkMode;
            _settings.Save();
            ApplyTheme();
            UpdateAllOptionButtonsVisuals();
        }

        private void ApplyTheme()
        {
            string bgRoot = _isDarkMode ? "#0F1117" : "#F3F4F6";
            string bgBar = _isDarkMode ? "#12151E" : "#FFFFFF";
            string bgTile = _isDarkMode ? "#141821" : "#FFFFFF";
            string bgSubCard = _isDarkMode ? "#10131B" : "#F9FAFB";
            string borderCol = _isDarkMode ? "#242938" : "#E5E7EB";
            string fgMain = _isDarkMode ? "#F9FAFB" : "#111827";
            string fgMuted = _isDarkMode ? "#9CA3AF" : "#4B5563";
            string btnBg = _isDarkMode ? "#1B1F2B" : "#F3F4F6";
            string btnBorder = _isDarkMode ? "#2D3343" : "#D1D5DB";

            Background = BrushHex(bgRoot);
            RootGrid.Background = BrushHex(bgRoot);
            TopBarBorder.Background = BrushHex(bgBar);
            TopBarBorder.BorderBrush = BrushHex(borderCol);
            FooterBorder.Background = BrushHex(bgBar);
            FooterBorder.BorderBrush = BrushHex(borderCol);

            PagesTileBorder.Background = BrushHex(bgTile);
            PagesTileBorder.BorderBrush = BrushHex(borderCol);
            ViewportTileBorder.Background = BrushHex(bgTile);
            ViewportTileBorder.BorderBrush = BrushHex(borderCol);
            RightTileBorder.Background = BrushHex(bgTile);
            RightTileBorder.BorderBrush = BrushHex(borderCol);

            EmptyStateOverlay.Background = BrushHex(bgTile);
            QueueWorkspaceOverlay.Background = BrushHex(bgTile);
            QueueListContainerBorder.Background = BrushHex(bgSubCard);
            QueueListContainerBorder.BorderBrush = BrushHex(borderCol);
            HardwareMaintCardBorder.Background = BrushHex(bgSubCard);
            HardwareMaintCardBorder.BorderBrush = BrushHex(borderCol);
            InkCardBorder.Background = BrushHex(bgSubCard);
            InkCardBorder.BorderBrush = BrushHex(borderCol);

            BrandTitleTxt.Foreground = BrushHex(fgMain);
            EmptyTitleTxt.Foreground = BrushHex(fgMain);
            FooterLeftTxt.Foreground = BrushHex(fgMuted);
            FooterRightTxt.Foreground = BrushHex(fgMuted);

            ThemeToggleBtn.Content = _isDarkMode ? "☀" : "☾";
            ThemeToggleBtn.Background = BrushHex(btnBg);
            ThemeToggleBtn.BorderBrush = BrushHex(btnBorder);

            ZoomStepperBorder.Background = BrushHex(btnBg);
            ZoomStepperBorder.BorderBrush = BrushHex(btnBorder);
            ZoomLabel.Foreground = BrushHex(fgMain);
            ZoomInBtn.Foreground = BrushHex(fgMain);
            ZoomOutBtn.Foreground = BrushHex(fgMain);

            CustomIpTextBox.Background = BrushHex(btnBg);
            CustomIpTextBox.Foreground = BrushHex(fgMain);
            CustomIpTextBox.BorderBrush = BrushHex(btnBorder);
            CustomPageRangeBox.Background = BrushHex(btnBg);
            CustomPageRangeBox.Foreground = BrushHex(fgMain);
            CustomPageRangeBox.BorderBrush = BrushHex(btnBorder);

            StyleSecondaryButton(ToggleSidebarBtn, true);
            StyleSecondaryButton(OpenFileBtn, false);
            StyleSecondaryButton(SavePdfTopBtn, false);
            StyleSecondaryButton(SaveReceiptBtn, false);
            StyleSecondaryButton(RotatePageBtn, false);
            StyleSecondaryButton(AppendFilesBtn, true);
            StyleSecondaryButton(ClearAllPagesBtn, false);
            StyleSecondaryButton(RefreshPrintersBtn, true);
            StyleSecondaryButton(EmptyTestPageBtn, false);
        }

        private void StyleSegmentButton(Button btn, bool active)
        {
            if (active)
            {
                btn.Background = BrushHex("#C5A059");
                btn.Foreground = BrushHex("#0F1117");
                btn.BorderBrush = BrushHex("#C5A059");
                btn.BorderThickness = new Thickness(0);
                btn.FontWeight = FontWeights.Bold;
            }
            else
            {
                btn.Background = BrushHex(_isDarkMode ? "#1B1F2B" : "#F3F4F6");
                btn.Foreground = BrushHex(_isDarkMode ? "#E5E7EB" : "#1F2937");
                btn.BorderBrush = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB");
                btn.BorderThickness = new Thickness(1);
                btn.FontWeight = FontWeights.SemiBold;
            }
        }

        private void StyleSecondaryButton(Button btn, bool goldText)
        {
            btn.Background = BrushHex(_isDarkMode ? "#1B1F2B" : "#F3F4F6");
            btn.Foreground = BrushHex(goldText ? "#C5A059" : (_isDarkMode ? "#E5E7EB" : "#1F2937"));
            btn.BorderBrush = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB");
        }

        private void UpdateAllOptionButtonsVisuals()
        {
            StyleSegmentButton(ModePrintBtn, _mode == WorkspaceMode.Print);
            StyleSegmentButton(ModeScanBtn, _mode == WorkspaceMode.Scan);
            StyleSegmentButton(ModeCopyBtn, _mode == WorkspaceMode.Copy);
            StyleSegmentButton(ModeQueueBtn, _mode == WorkspaceMode.Queue);

            CopiesValueTxt.Text = _copies.ToString();
            CopyTabCountTxt.Text = _copies.ToString();

            StyleSegmentButton(PrintColorBtn, _printColorMode == PrintColorMode.Color);
            StyleSegmentButton(PrintGrayBtn, _printColorMode == PrintColorMode.Grayscale);

            StyleSegmentButton(DuplexOffBtn, _duplexMode == PrintDuplexMode.OneSided);
            StyleSegmentButton(DuplexLongBtn, _duplexMode == PrintDuplexMode.LongEdge);
            StyleSegmentButton(DuplexShortBtn, _duplexMode == PrintDuplexMode.ShortEdge);

            StyleSegmentButton(FormatA4Btn, _paperFormat == PrintPaperFormat.A4);
            StyleSegmentButton(FormatA5Btn, _paperFormat == PrintPaperFormat.A5);
            StyleSegmentButton(FormatLabelBtn, _paperFormat == PrintPaperFormat.Label100x150);

            StyleSegmentButton(OrientAutoBtn, _orientationMode == PrintOrientationMode.Auto);
            StyleSegmentButton(OrientPortraitBtn, _orientationMode == PrintOrientationMode.Portrait);
            StyleSegmentButton(OrientLandscapeBtn, _orientationMode == PrintOrientationMode.Landscape);

            StyleSegmentButton(ScaleFitBtn, _scaleMode == PrintScaleMode.FitPrintableArea);
            StyleSegmentButton(Scale100Btn, _scaleMode == PrintScaleMode.ActualSize100);

            StyleSegmentButton(RangeAllBtn, _pageRangeMode == PrintPageRangeMode.All);
            StyleSegmentButton(RangeCurrentBtn, _pageRangeMode == PrintPageRangeMode.Current);
            StyleSegmentButton(RangeCustomBtn, _pageRangeMode == PrintPageRangeMode.Custom);
            CustomPageRangeBox.Visibility = _pageRangeMode == PrintPageRangeMode.Custom ? Visibility.Visible : Visibility.Collapsed;

            StyleSegmentButton(ScanSrcFlatbedBtn, _scanSource == ScanSourceMode.Flatbed);
            StyleSegmentButton(ScanSrcAdfBtn, _scanSource == ScanSourceMode.AdfSimplex);
            StyleSegmentButton(ScanSrcDuplexBtn, _scanSource == ScanSourceMode.AdfDuplex);
            StyleSegmentButton(CopySrcFlatbedBtn, _scanSource == ScanSourceMode.Flatbed);
            StyleSegmentButton(CopySrcAdfBtn, _scanSource != ScanSourceMode.Flatbed);

            StyleSegmentButton(ScanDpi150Btn, _scanDpi == 150);
            StyleSegmentButton(ScanDpi300Btn, _scanDpi == 300);
            StyleSegmentButton(ScanDpi600Btn, _scanDpi == 600);

            StyleSegmentButton(ScanColColorBtn, _scanColor == ScanColorMode.Color);
            StyleSegmentButton(ScanColGrayBtn, _scanColor == ScanColorMode.Grayscale);
            StyleSegmentButton(ScanColBwBtn, _scanColor == ScanColorMode.ReceiptBw);

            StyleSegmentButton(ProtoAutoBtn, _scanProtocol == ScanProtocolMode.Auto);
            StyleSegmentButton(ProtoEsclBtn, _scanProtocol == ScanProtocolMode.EsclLan);
            StyleSegmentButton(ProtoWiaBtn, _scanProtocol == ScanProtocolMode.WiaLocal);

            bool hasCur = _currentPageIndex >= 0 && _currentPageIndex < _pages.Count;
            StyleSegmentButton(CropToggleBtn, _isCropping);
            StyleSegmentButton(ReceiptContrastBtn, hasCur && _pages[_currentPageIndex].EnhanceReceiptContrast);
        }

        // =====================================================================
        // WORKSPACE MODE SWITCHING & SIDEBAR
        // =====================================================================
        private void SetWorkspaceMode(WorkspaceMode mode)
        {
            _mode = mode;
            if (_isCropping) ExitCropMode();

            PrintSettingsPanel.Visibility = mode == WorkspaceMode.Print ? Visibility.Visible : Visibility.Collapsed;
            ScanSettingsPanel.Visibility = mode == WorkspaceMode.Scan ? Visibility.Visible : Visibility.Collapsed;
            CopySettingsPanel.Visibility = mode == WorkspaceMode.Copy ? Visibility.Visible : Visibility.Collapsed;
            QueueWorkspaceOverlay.Visibility = mode == WorkspaceMode.Queue ? Visibility.Visible : Visibility.Collapsed;

            EmptyTitleTxt.Text = mode switch
            {
                WorkspaceMode.Scan => "Vorlage einlegen und Scan starten",
                WorkspaceMode.Copy => "Vorlage einlegen und Direktkopie starten",
                _ => "PDF oder Bild hierher ziehen"
            };

            UpdateAllOptionButtonsVisuals();
            RefreshDocumentView();

            if (mode == WorkspaceMode.Queue)
                _ = RefreshQueueOnlyAsync();
        }

        private void ModePrint_Click(object sender, RoutedEventArgs e) => SetWorkspaceMode(WorkspaceMode.Print);
        private void ModeScan_Click(object sender, RoutedEventArgs e) => SetWorkspaceMode(WorkspaceMode.Scan);
        private void ModeCopy_Click(object sender, RoutedEventArgs e) => SetWorkspaceMode(WorkspaceMode.Copy);
        private void ModeQueue_Click(object sender, RoutedEventArgs e) => SetWorkspaceMode(WorkspaceMode.Queue);

        private void ToggleSidebar_Click(object sender, RoutedEventArgs e)
        {
            _sidebarVisible = !_sidebarVisible;
            ApplySidebarVisibility();
        }

        private void MainWindow_SizeChanged(object sender, SizeChangedEventArgs e)
        {
            if (ActualWidth < 880 && _sidebarVisible)
            {
                _sidebarVisible = false;
                ApplySidebarVisibility();
            }
        }

        private void ApplySidebarVisibility()
        {
            PagesTileBorder.Visibility = _sidebarVisible ? Visibility.Visible : Visibility.Collapsed;
            SidebarCol.Width = _sidebarVisible ? new GridLength(184) : new GridLength(0);
            SidebarGapCol.Width = _sidebarVisible ? new GridLength(10) : new GridLength(0);
        }

        // =====================================================================
        // PRINTER DISCOVERY, INK BARS & QUEUE MANAGEMENT
        // =====================================================================
        private async void RefreshPrintersAndQueue_Click(object sender, RoutedEventArgs e)
        {
            await RefreshPrintersAndQueueAsync(silent: false);
        }

        private async Task RefreshPrintersAndQueueAsync(bool silent)
        {
            RefreshPrintersBtn.Content = "…";
            try
            {
                string? customIp = string.IsNullOrWhiteSpace(_settings.CustomPrinterIp) ? null : _settings.CustomPrinterIp;
                var discovered = await PrinterHardwareEngine.DiscoverPrintersAsync(customIp);
                _printers = discovered;

                _suppressListEvents = true;
                PrintersListBox.ItemsSource = _printers;

                DiscoveredPrinterInfo? chosen = null;
                if (!string.IsNullOrWhiteSpace(_settings.PreferredPrinterName))
                {
                    chosen = _printers.FirstOrDefault(p => string.Equals(p.QueueName, _settings.PreferredPrinterName, StringComparison.OrdinalIgnoreCase));
                }
                chosen ??= _printers.FirstOrDefault(p => !p.IsVirtualPrinter) ?? _printers.FirstOrDefault();

                _selectedPrinter = chosen;
                PrintersListBox.SelectedItem = chosen;
                _suppressListEvents = false;

                UpdateInkTelemetryVisuals();
                await RefreshQueueOnlyAsync();

                bool hpInstalled = PrinterHardwareEngine.IsHpSmartStoreAppInstalled();
                HpAppStatusBadgeTxt.Text = hpInstalled ? "HP Smart noch installiert (optional entfernbar)" : "✓ HP Smart deinstalliert";
                OpenWindowsAppsBtn.Visibility = hpInstalled ? Visibility.Visible : Visibility.Collapsed;

                if (!silent && chosen != null)
                    ShowToast($"✓ {chosen.DisplayName} · {chosen.EffectiveStatusLabel}");
            }
            catch (Exception ex)
            {
                if (!silent) ShowToast(ex.Message, isError: true);
            }
            finally
            {
                RefreshPrintersBtn.Content = "↻ Prüfen";
            }
        }

        private async void PrintersListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressListEvents) return;
            if (PrintersListBox.SelectedItem is DiscoveredPrinterInfo p)
            {
                _selectedPrinter = p;
                _settings.PreferredPrinterName = p.QueueName;
                _settings.Save();
                UpdateInkTelemetryVisuals();
                await RefreshQueueOnlyAsync();
            }
        }

        private void UpdateInkTelemetryVisuals()
        {
            var p = _selectedPrinter;
            if (p == null || p.IsVirtualPrinter)
            {
                InkSummaryRightTxt.Text = "Virtuell";
                BlackInkPctTxt.Text = "—";
                ColorInkPctTxt.Text = "—";
                BlackInkFillBar.Width = 0;
                ColorInkFillBar.Width = 0;
                return;
            }

            if (p.BlackInkPercent.HasValue || p.ColorInkPercent.HasValue)
            {
                InkSummaryRightTxt.Text = p.ResolvedIpAddress;
                SetSingleInkBar(BlackInkFillBar, BlackInkPctTxt, p.BlackInkPercent, "#E5E7EB");
                SetSingleInkBar(ColorInkFillBar, ColorInkPctTxt, p.ColorInkPercent, "#C5A059");
            }
            else
            {
                InkSummaryRightTxt.Text = p.LanHttpOnline ? "Lokal aktiv" : "Standby";
                BlackInkPctTxt.Text = "—";
                ColorInkPctTxt.Text = "—";
                BlackInkFillBar.Width = 0;
                ColorInkFillBar.Width = 0;
            }
        }

        private static void SetSingleInkBar(Border fillBar, TextBlock pctTxt, int? pct, string normalHex)
        {
            if (!pct.HasValue)
            {
                fillBar.Width = 0;
                pctTxt.Text = "—";
                return;
            }

            int v = Math.Clamp(pct.Value, 0, 100);
            pctTxt.Text = $"{v}%";
            fillBar.Width = Math.Max(4, v * 1.65);

            string hex = v <= 10 ? "#EF4444" : (v <= 22 ? "#F59E0B" : normalHex);
            fillBar.Background = BrushHex(hex);
        }

        private async Task RefreshQueueOnlyAsync()
        {
            if (_selectedPrinter == null)
            {
                QueueJobsListBox.ItemsSource = null;
                EmptyQueuePlaceholderTxt.Visibility = Visibility.Visible;
                return;
            }

            var jobs = await PrinterHardwareEngine.GetQueueJobsAsync(_selectedPrinter.QueueName);
            QueueJobsListBox.ItemsSource = jobs;
            EmptyQueuePlaceholderTxt.Visibility = jobs.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
        }

        private async void CancelSingleJob_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedPrinter == null) return;
            if (sender is Button btn && btn.Tag is int jobId)
            {
                await PrinterHardwareEngine.CancelSingleJobAsync(_selectedPrinter.QueueName, jobId);
                await RefreshQueueOnlyAsync();
                ShowToast($"✓ Auftrag #{jobId} abgebrochen");
            }
        }

        private async void PurgeQueue_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedPrinter == null) return;
            int n = await PrinterHardwareEngine.PurgeAllQueueJobsAsync(_selectedPrinter.QueueName);
            await RefreshQueueOnlyAsync();
            ShowToast(n > 0 ? $"✓ {n} Druckaufträge entfernt" : "✓ Warteschlange ist leer");
        }

        private async void SaveCustomIp_Click(object sender, RoutedEventArgs e)
        {
            _settings.CustomPrinterIp = (CustomIpTextBox.Text ?? "").Trim();
            _settings.Save();
            await RefreshPrintersAndQueueAsync(silent: false);
        }

        private void OpenPrinterWebUi_Click(object sender, RoutedEventArgs e)
        {
            string? ip = _selectedPrinter?.ResolvedIpAddress;
            if (string.IsNullOrWhiteSpace(ip))
                ip = (_settings.CustomPrinterIp ?? "").Trim();

            if (string.IsNullOrWhiteSpace(ip))
            {
                ShowToast("Keine lokale Drucker-IP bekannt (bitte Gerät einschalten)", isError: true);
                return;
            }

            try
            {
                Process.Start(new ProcessStartInfo($"http://{ip}") { UseShellExecute = true });
            }
            catch (Exception ex)
            {
                ShowToast(ex.Message, isError: true);
            }
        }

        private void OpenWindowsPrintersSettings_Click(object sender, RoutedEventArgs e)
        {
            try { Process.Start(new ProcessStartInfo("ms-settings:printers") { UseShellExecute = true }); } catch { }
        }

        private void OpenWindowsAppsSettings_Click(object sender, RoutedEventArgs e)
        {
            try { Process.Start(new ProcessStartInfo("ms-settings:appsfeatures") { UseShellExecute = true }); } catch { }
        }

        // =====================================================================
        // DOCUMENT LOADING, DRAG & DROP & LIVE PAPER PREVIEW
        // =====================================================================
        private async void OpenFile_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Dokument oder Bild öffnen",
                Filter = "PDF & Bilder|*.pdf;*.png;*.jpg;*.jpeg;*.webp;*.bmp;*.tif;*.tiff|PDF-Dokumente|*.pdf|Bilder|*.png;*.jpg;*.jpeg;*.webp;*.bmp;*.tif",
                Multiselect = true
            };
            if (dlg.ShowDialog(this) == true)
            {
                await LoadFilesIntoWorkspaceAsync(dlg.FileNames, append: false);
            }
        }

        private async void AppendFiles_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Weitere Seiten anhängen",
                Filter = "PDF & Bilder|*.pdf;*.png;*.jpg;*.jpeg;*.webp;*.bmp;*.tif;*.tiff",
                Multiselect = true
            };
            if (dlg.ShowDialog(this) == true)
            {
                await LoadFilesIntoWorkspaceAsync(dlg.FileNames, append: true);
            }
        }

        private async Task LoadFilesIntoWorkspaceAsync(IEnumerable<string> paths, bool append)
        {
            string[] arr = paths.Where(File.Exists).ToArray();
            if (arr.Length == 0) return;

            BeginBusy("Lade Dokument …", IOPath.GetFileName(arr[0]));
            try
            {
                var loaded = await DocumentAndPrintEngine.LoadFilesAsync(arr);
                if (loaded.Count == 0)
                {
                    ShowToast("Datei konnte nicht gelesen werden", isError: true);
                    return;
                }

                if (!append)
                {
                    _pages.Clear();
                    _loadedDocumentTitle = IOPath.GetFileNameWithoutExtension(arr[0]);
                }

                int firstAddedIdx = _pages.Count;
                _pages.AddRange(loaded);
                ReindexPages();
                _currentPageIndex = firstAddedIdx;

                if (_mode == WorkspaceMode.Queue)
                    SetWorkspaceMode(WorkspaceMode.Print);

                RefreshDocumentView();
                ShowToast($"✓ {loaded.Count} Seite(n) geladen");
            }
            catch (Exception ex)
            {
                ShowToast(ex.Message, isError: true);
            }
            finally
            {
                EndBusy();
            }
        }

        private void Window_PreviewDragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                e.Effects = DragDropEffects.Copy;
                e.Handled = true;
            }
        }

        private async void Window_PreviewDrop(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] files)
            {
                var supported = files.Where(f => DocumentAndPrintEngine.SupportedExtensions.Contains(IOPath.GetExtension(f).ToLowerInvariant())).ToArray();
                if (supported.Length > 0)
                {
                    bool append = (Keyboard.Modifiers & ModifierKeys.Shift) != 0 && _pages.Count > 0;
                    await LoadFilesIntoWorkspaceAsync(supported, append);
                }
            }
        }

        private void LoadCalibrationPage_Click(object sender, RoutedEventArgs e)
        {
            var testPage = DocumentAndPrintEngine.CreateCalibrationTestPage();
            _pages.Clear();
            _pages.Add(testPage);
            _loadedDocumentTitle = "Funkenwerk Prüfblatt";
            ReindexPages();
            _currentPageIndex = 0;
            if (_mode == WorkspaceMode.Queue)
                SetWorkspaceMode(WorkspaceMode.Print);
            RefreshDocumentView();
            ShowToast("✓ Prüfblatt (CMYK & 100-mm-Lineal) geladen");
        }

        private void ClearAllPages_Click(object sender, RoutedEventArgs e)
        {
            if (_isCropping) ExitCropMode();
            _pages.Clear();
            _currentPageIndex = 0;
            RefreshDocumentView();
        }

        private void ReindexPages()
        {
            for (int i = 0; i < _pages.Count; i++)
                _pages[i].Index = i;
        }

        private void RefreshDocumentView()
        {
            PageCountBadgeTxt.Text = _pages.Count.ToString();

            _suppressListEvents = true;
            PagesListBox.ItemsSource = null;
            PagesListBox.ItemsSource = _pages;
            if (_pages.Count > 0)
            {
                _currentPageIndex = Math.Clamp(_currentPageIndex, 0, _pages.Count - 1);
                PagesListBox.SelectedIndex = _currentPageIndex;
            }
            _suppressListEvents = false;

            if (_mode == WorkspaceMode.Queue)
            {
                EmptyStateOverlay.Visibility = Visibility.Collapsed;
                return;
            }

            if (_pages.Count == 0)
            {
                EmptyStateOverlay.Visibility = Visibility.Visible;
                PreviewPageImage.Source = null;
                FooterRightTxt.Text = "Kein Dokument geladen";
                UpdateAllOptionButtonsVisuals();
                return;
            }

            EmptyStateOverlay.Visibility = Visibility.Collapsed;
            var cur = _pages[_currentPageIndex];

            bool showAsGray = _mode == WorkspaceMode.Print && _printColorMode == PrintColorMode.Grayscale;
            BitmapSource bmp = showAsGray
                ? DocumentAndPrintEngine.ConvertToGrayscaleBitmap(cur.ProcessedBitmap)
                : cur.ProcessedBitmap;

            PreviewPageImage.Source = bmp;

            var (paperWmm, paperHmm, _) = DocumentAndPrintEngine.GetPaperDimensionsMm(_paperFormat);
            bool landscape = _orientationMode == PrintOrientationMode.Landscape ||
                             (_orientationMode == PrintOrientationMode.Auto && bmp.PixelWidth > bmp.PixelHeight * 1.05);

            double sheetMmW = landscape ? paperHmm : paperWmm;
            double sheetMmH = landscape ? paperWmm : paperHmm;

            // Base scale: 2.52 DIPs per mm at 100% zoom (~530x748 for A4)
            double dipPerMm = 2.52 * _zoomFactor;
            PaperSheetBorder.Width = Math.Max(140, sheetMmW * dipPerMm);
            PaperSheetBorder.Height = Math.Max(180, sheetMmH * dipPerMm);

            double pad = _scaleMode == PrintScaleMode.FitPrintableArea ? Math.Max(4, 4.2 * dipPerMm) : 0;
            PreviewPageImage.Margin = new Thickness(pad);

            ZoomLabel.Text = $"{(int)Math.Round(_zoomFactor * 100)}%";
            FooterRightTxt.Text = $"Seite {_currentPageIndex + 1} / {_pages.Count} · {sheetMmW:0}×{sheetMmH:0} mm";
            UpdateAllOptionButtonsVisuals();
        }

        private void PagesListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressListEvents) return;
            if (PagesListBox.SelectedIndex >= 0 && PagesListBox.SelectedIndex < _pages.Count)
            {
                if (_isCropping) ExitCropMode();
                _currentPageIndex = PagesListBox.SelectedIndex;
                RefreshDocumentView();
            }
        }

        // =====================================================================
        // PAGE ROTATION, CONTRAST, REORDER & INTERACTIVE CROP
        // =====================================================================
        private void RotatePage_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0) return;
            if (_isCropping) ExitCropMode();
            var cur = _pages[_currentPageIndex];
            cur.RotationDegrees = (cur.RotationDegrees + 90) % 360;
            DocumentAndPrintEngine.RebuildProcessedBitmap(cur);
            RefreshDocumentView();
        }

        private void ToggleReceiptContrast_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0) return;
            var cur = _pages[_currentPageIndex];
            cur.EnhanceReceiptContrast = !cur.EnhanceReceiptContrast;
            DocumentAndPrintEngine.RebuildProcessedBitmap(cur);
            RefreshDocumentView();
            ShowToast(cur.EnhanceReceiptContrast ? "✓ Beleg-Kontrast aktiviert (Hintergrund weiß)" : "Originalfarben wiederhergestellt");
        }

        private void DeletePage_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0) return;
            if (_isCropping) ExitCropMode();
            _pages.RemoveAt(_currentPageIndex);
            ReindexPages();
            if (_currentPageIndex >= _pages.Count)
                _currentPageIndex = Math.Max(0, _pages.Count - 1);
            RefreshDocumentView();
        }

        private void MovePageUp_Click(object sender, RoutedEventArgs e)
        {
            if (_currentPageIndex <= 0 || _currentPageIndex >= _pages.Count) return;
            var item = _pages[_currentPageIndex];
            _pages.RemoveAt(_currentPageIndex);
            _currentPageIndex--;
            _pages.Insert(_currentPageIndex, item);
            ReindexPages();
            RefreshDocumentView();
        }

        private void MovePageDown_Click(object sender, RoutedEventArgs e)
        {
            if (_currentPageIndex < 0 || _currentPageIndex >= _pages.Count - 1) return;
            var item = _pages[_currentPageIndex];
            _pages.RemoveAt(_currentPageIndex);
            _currentPageIndex++;
            _pages.Insert(_currentPageIndex, item);
            ReindexPages();
            RefreshDocumentView();
        }

        private void ToggleCropMode_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0) return;
            if (_isCropping)
            {
                ExitCropMode();
                return;
            }

            _isCropping = true;
            CropOverlayCanvas.Visibility = Visibility.Visible;
            CropActionPill.Visibility = Visibility.Visible;

            double cw = PaperContentGrid.ActualWidth;
            double ch = PaperContentGrid.ActualHeight;
            _pendingCanvasCropRect = new Rect(cw * 0.08, ch * 0.08, cw * 0.84, ch * 0.84);
            UpdateCropSelectionVisual();
            UpdateAllOptionButtonsVisuals();
        }

        private void ExitCropMode()
        {
            _isCropping = false;
            _isDraggingCrop = false;
            CropOverlayCanvas.Visibility = Visibility.Collapsed;
            CropActionPill.Visibility = Visibility.Collapsed;
            UpdateAllOptionButtonsVisuals();
        }

        private void CropCanvas_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (!_isCropping) return;
            _isDraggingCrop = true;
            _cropDragStart = e.GetPosition(CropOverlayCanvas);
            CropOverlayCanvas.CaptureMouse();
        }

        private void CropCanvas_MouseMove(object sender, MouseEventArgs e)
        {
            if (!_isDraggingCrop) return;
            Point cur = e.GetPosition(CropOverlayCanvas);
            double x = Math.Clamp(Math.Min(_cropDragStart.X, cur.X), 0, CropOverlayCanvas.ActualWidth);
            double y = Math.Clamp(Math.Min(_cropDragStart.Y, cur.Y), 0, CropOverlayCanvas.ActualHeight);
            double w = Math.Clamp(Math.Abs(cur.X - _cropDragStart.X), 12, CropOverlayCanvas.ActualWidth - x);
            double h = Math.Clamp(Math.Abs(cur.Y - _cropDragStart.Y), 12, CropOverlayCanvas.ActualHeight - y);
            _pendingCanvasCropRect = new Rect(x, y, w, h);
            UpdateCropSelectionVisual();
        }

        private void CropCanvas_MouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (!_isDraggingCrop) return;
            _isDraggingCrop = false;
            CropOverlayCanvas.ReleaseMouseCapture();
        }

        private void UpdateCropSelectionVisual()
        {
            Canvas.SetLeft(CropSelectionRect, _pendingCanvasCropRect.X);
            Canvas.SetTop(CropSelectionRect, _pendingCanvasCropRect.Y);
            CropSelectionRect.Width = Math.Max(8, _pendingCanvasCropRect.Width);
            CropSelectionRect.Height = Math.Max(8, _pendingCanvasCropRect.Height);
        }

        private void ApplyCrop_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0 || PreviewPageImage.Source is not BitmapSource bmp)
            {
                ExitCropMode();
                return;
            }

            // Map CropOverlayCanvas coordinates onto rendered PreviewPageImage bounds
            GeneralTransform gt = PreviewPageImage.TransformToAncestor(PaperContentGrid);
            Rect imgBounds = gt.TransformBounds(new Rect(0, 0, PreviewPageImage.ActualWidth, PreviewPageImage.ActualHeight));

            Rect inter = Rect.Intersect(_pendingCanvasCropRect, imgBounds);
            if (!inter.IsEmpty && inter.Width >= 14 && inter.Height >= 14 && imgBounds.Width > 1 && imgBounds.Height > 1)
            {
                double nx = Math.Clamp((inter.X - imgBounds.X) / imgBounds.Width, 0, 0.95);
                double ny = Math.Clamp((inter.Y - imgBounds.Y) / imgBounds.Height, 0, 0.95);
                double nw = Math.Clamp(inter.Width / imgBounds.Width, 0.05, 1.0 - nx);
                double nh = Math.Clamp(inter.Height / imgBounds.Height, 0.05, 1.0 - ny);

                var cur = _pages[_currentPageIndex];
                Rect prev = cur.NormalizedCrop;
                cur.NormalizedCrop = new Rect(
                    prev.X + nx * prev.Width,
                    prev.Y + ny * prev.Height,
                    nw * prev.Width,
                    nh * prev.Height);

                DocumentAndPrintEngine.RebuildProcessedBitmap(cur);
                ShowToast("✓ Zuschnitt übernommen");
            }

            ExitCropMode();
            RefreshDocumentView();
        }

        private void ResetCrop_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count > 0)
            {
                var cur = _pages[_currentPageIndex];
                cur.NormalizedCrop = new Rect(0, 0, 1, 1);
                DocumentAndPrintEngine.RebuildProcessedBitmap(cur);
            }
            ExitCropMode();
            RefreshDocumentView();
            ShowToast("✓ Originalgröße wiederhergestellt");
        }

        private void CancelCrop_Click(object sender, RoutedEventArgs e) => ExitCropMode();

        // =====================================================================
        // SUBSCRIPTION-FREE SCANNING & MULTI-PAGE APPEND
        // =====================================================================
        private async void StartScanNew_Click(object sender, RoutedEventArgs e)
        {
            await RunScanWorkflowAsync(appendToExisting: false);
        }

        private async void StartScanAppend_Click(object sender, RoutedEventArgs e)
        {
            await RunScanWorkflowAsync(appendToExisting: true);
        }

        private async Task RunScanWorkflowAsync(bool appendToExisting)
        {
            SetWorkspaceMode(WorkspaceMode.Scan);
            var ct = BeginBusy("Scanner arbeitet …", "100 % Lokal · Ohne HP-Cloud");
            try
            {
                var (bitmaps, engineName) = await PrinterHardwareEngine.ScanPagesAsync(
                    _selectedPrinter,
                    _scanSource,
                    _scanDpi,
                    _scanColor,
                    _scanProtocol,
                    progressMsg => Dispatcher.InvokeAsync(() => BusyOverlaySubTxt.Text = progressMsg),
                    ct);

                if (bitmaps.Count == 0)
                {
                    ShowToast("Keine Seite vom Scanner empfangen", isError: true);
                    return;
                }

                if (!appendToExisting)
                {
                    _pages.Clear();
                    _loadedDocumentTitle = $"Scan_{DateTime.Now:yyyy-MM-dd_HHmm}";
                }

                int firstNew = _pages.Count;
                bool receiptContrast = _scanColor == ScanColorMode.ReceiptBw;
                bool pureBw = _scanColor != ScanColorMode.Color;

                foreach (var raw in bitmaps)
                {
                    _pages.Add(DocumentAndPrintEngine.CreateScannedPageItem(raw, _scanDpi, receiptContrast, pureBw));
                }

                ReindexPages();
                _currentPageIndex = firstNew;
                RefreshDocumentView();
                ShowToast($"✓ {bitmaps.Count} Seite(n) gescannt ({engineName}) · Gesamt: {_pages.Count} S.");
            }
            catch (OperationCanceledException)
            {
                ShowToast("Scan abgebrochen");
            }
            catch (Exception ex)
            {
                ShowToast(ex.Message, isError: true, durationMs: 5200);
            }
            finally
            {
                EndBusy();
            }
        }

        // =====================================================================
        // DIRECT PRINTING & 1-CLICK COPIER
        // =====================================================================
        private async void ExecutePrint_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0)
            {
                ShowToast("Bitte zuerst ein PDF oder Bild öffnen bzw. scannen", isError: true);
                return;
            }
            if (_selectedPrinter == null)
            {
                ShowToast("Kein Drucker ausgewählt", isError: true);
                return;
            }

            var indices = DocumentAndPrintEngine.ResolveSelectedPageIndices(
                _pages.Count,
                _currentPageIndex,
                _pageRangeMode,
                CustomPageRangeBox.Text);

            BeginBusy("Sende Druckauftrag …", _selectedPrinter.DisplayName);
            try
            {
                string summary = await DocumentAndPrintEngine.ExecutePrintJobAsync(
                    _selectedPrinter,
                    _pages,
                    indices,
                    _copies,
                    _printColorMode,
                    _duplexMode,
                    _paperFormat,
                    _orientationMode,
                    _scaleMode,
                    _loadedDocumentTitle);

                ShowToast($"✓ {summary}", durationMs: 4200);
                await RefreshQueueOnlyAsync();
            }
            catch (Exception ex)
            {
                ShowToast($"Druckfehler: {ex.Message}", isError: true, durationMs: 5000);
            }
            finally
            {
                EndBusy();
            }
        }

        private async void StartBwCopy_Click(object sender, RoutedEventArgs e)
        {
            await RunDirectCopyAsync(colorCopy: false);
        }

        private async void StartColorCopy_Click(object sender, RoutedEventArgs e)
        {
            await RunDirectCopyAsync(colorCopy: true);
        }

        private async Task RunDirectCopyAsync(bool colorCopy)
        {
            if (_selectedPrinter == null)
            {
                ShowToast("Kein Ziel-Drucker ausgewählt", isError: true);
                return;
            }

            var ct = BeginBusy(colorCopy ? "Farbkopie läuft …" : "S/W-Kopie läuft …", "1. Scannen …");
            try
            {
                var (bitmaps, _) = await PrinterHardwareEngine.ScanPagesAsync(
                    _selectedPrinter,
                    _scanSource == ScanSourceMode.AdfDuplex ? ScanSourceMode.AdfSimplex : _scanSource,
                    300,
                    colorCopy ? ScanColorMode.Color : ScanColorMode.ReceiptBw,
                    _scanProtocol,
                    msg => Dispatcher.InvokeAsync(() => BusyOverlaySubTxt.Text = msg),
                    ct);

                if (bitmaps.Count == 0)
                {
                    ShowToast("Keine Vorlage erkannt", isError: true);
                    return;
                }

                _pages.Clear();
                foreach (var raw in bitmaps)
                {
                    _pages.Add(DocumentAndPrintEngine.CreateScannedPageItem(
                        raw, 300, receiptContrast: !colorCopy, forceBw: !colorCopy));
                }
                ReindexPages();
                _currentPageIndex = 0;
                RefreshDocumentView();

                BusyOverlaySubTxt.Text = "2. Drucke Kopie …";
                var allIdx = Enumerable.Range(0, _pages.Count).ToList();
                string msg = await DocumentAndPrintEngine.ExecutePrintJobAsync(
                    _selectedPrinter,
                    _pages,
                    allIdx,
                    _copies,
                    colorCopy ? PrintColorMode.Color : PrintColorMode.Grayscale,
                    PrintDuplexMode.OneSided,
                    PrintPaperFormat.A4,
                    PrintOrientationMode.Auto,
                    PrintScaleMode.FitPrintableArea,
                    "Funkenwerk Direktkopie");

                ShowToast($"✓ Kopie gestartet ({_pages.Count} S. × {_copies})");
            }
            catch (Exception ex)
            {
                ShowToast(ex.Message, isError: true, durationMs: 5000);
            }
            finally
            {
                EndBusy();
            }
        }

        // =====================================================================
        // EXPORT, FUNKENWERK PDF HANDOFF & ACCOUNTING RECEIPTS
        // =====================================================================
        private async void SaveAsPdf_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0)
            {
                ShowToast("Keine Seiten zum Speichern vorhanden", isError: true);
                return;
            }

            var dlg = new SaveFileDialog
            {
                Title = "Als PDF speichern",
                Filter = "PDF-Dokument (*.pdf)|*.pdf",
                FileName = $"{_loadedDocumentTitle}.pdf"
            };
            if (dlg.ShowDialog(this) == true)
            {
                await DocumentAndPrintEngine.ExportPagesToPdfAsync(_pages, dlg.FileName);
                ShowToast($"✓ PDF gespeichert ({_pages.Count} Seiten)");
            }
        }

        private async void SaveCurrentPageImage_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0)
            {
                ShowToast("Keine Seite vorhanden", isError: true);
                return;
            }

            var dlg = new SaveFileDialog
            {
                Title = "Seite als Bild speichern",
                Filter = "PNG-Bild (*.png)|*.png|JPEG-Bild (*.jpg)|*.jpg",
                FileName = $"{_loadedDocumentTitle}_Seite{_currentPageIndex + 1}.png"
            };
            if (dlg.ShowDialog(this) == true)
            {
                var bmp = _pages[_currentPageIndex].ProcessedBitmap;
                byte[] bytes = dlg.FileName.EndsWith(".jpg", StringComparison.OrdinalIgnoreCase)
                    ? DocumentAndPrintEngine.EncodeBitmapAsJpeg(bmp, 94)
                    : DocumentAndPrintEngine.EncodeBitmapAsPng(bmp);
                await File.WriteAllBytesAsync(dlg.FileName, bytes);
                ShowToast("✓ Bild gespeichert");
            }
        }

        private async void OpenInFunkenwerkPdf_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0)
            {
                ShowToast("Bitte zuerst ein Dokument öffnen oder scannen", isError: true);
                return;
            }

            try
            {
                string exportDir = IOPath.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments),
                    "Funkenwerk Scans");
                Directory.CreateDirectory(exportDir);

                string outPdf = IOPath.Combine(exportDir, $"{_loadedDocumentTitle}_{DateTime.Now:HHmmss}.pdf");
                await DocumentAndPrintEngine.ExportPagesToPdfAsync(_pages, outPdf);

                if (File.Exists(FunkenwerkPdfExePath))
                {
                    var psi = new ProcessStartInfo
                    {
                        FileName = FunkenwerkPdfExePath,
                        WorkingDirectory = IOPath.GetDirectoryName(FunkenwerkPdfExePath)!,
                        UseShellExecute = false
                    };
                    psi.ArgumentList.Add(outPdf);
                    Process.Start(psi);
                    ShowToast("✓ In Funkenwerk PDF geöffnet");
                }
                else
                {
                    Process.Start(new ProcessStartInfo(outPdf) { UseShellExecute = true });
                }
            }
            catch (Exception ex)
            {
                ShowToast(ex.Message, isError: true);
            }
        }

        private async void SaveToAccountingReceipts_Click(object sender, RoutedEventArgs e)
        {
            if (_pages.Count == 0)
            {
                ShowToast("Bitte zuerst einen Beleg scannen oder öffnen", isError: true);
                return;
            }

            string initialDir = Directory.Exists(AccountingReceiptsUncDir)
                ? AccountingReceiptsUncDir
                : Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);

            var dlg = new SaveFileDialog
            {
                Title = "Beleg im Seelenfunke Buchhaltungs-Ordner ablegen",
                InitialDirectory = initialDir,
                Filter = "PDF-Beleg (*.pdf)|*.pdf",
                FileName = $"{DateTime.Now:yyyy-MM-dd}_Beleg.pdf"
            };

            if (dlg.ShowDialog(this) == true)
            {
                await DocumentAndPrintEngine.ExportPagesToPdfAsync(_pages, dlg.FileName);
                ShowToast($"✓ Beleg abgelegt: {IOPath.GetFileName(dlg.FileName)}");
            }
        }

        // =====================================================================
        // OPTION SEGMENT HANDLERS, ZOOM & KEYBOARD SHORTCUTS
        // =====================================================================
        private void CopiesDown_Click(object sender, RoutedEventArgs e)
        {
            _copies = Math.Max(1, _copies - 1);
            UpdateAllOptionButtonsVisuals();
        }

        private void CopiesUp_Click(object sender, RoutedEventArgs e)
        {
            _copies = Math.Min(99, _copies + 1);
            UpdateAllOptionButtonsVisuals();
        }

        private void SetPrintColor_Click(object sender, RoutedEventArgs e) { _printColorMode = PrintColorMode.Color; _settings.LastPrintColor = _printColorMode; _settings.Save(); RefreshDocumentView(); }
        private void SetPrintGray_Click(object sender, RoutedEventArgs e) { _printColorMode = PrintColorMode.Grayscale; _settings.LastPrintColor = _printColorMode; _settings.Save(); RefreshDocumentView(); }

        private void DuplexOff_Click(object sender, RoutedEventArgs e) { _duplexMode = PrintDuplexMode.OneSided; _settings.LastDuplex = _duplexMode; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void DuplexLong_Click(object sender, RoutedEventArgs e) { _duplexMode = PrintDuplexMode.LongEdge; _settings.LastDuplex = _duplexMode; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void DuplexShort_Click(object sender, RoutedEventArgs e) { _duplexMode = PrintDuplexMode.ShortEdge; _settings.LastDuplex = _duplexMode; _settings.Save(); UpdateAllOptionButtonsVisuals(); }

        private void FormatA4_Click(object sender, RoutedEventArgs e) { _paperFormat = PrintPaperFormat.A4; _settings.LastPaperFormat = _paperFormat; _settings.Save(); RefreshDocumentView(); }
        private void FormatA5_Click(object sender, RoutedEventArgs e) { _paperFormat = PrintPaperFormat.A5; _settings.LastPaperFormat = _paperFormat; _settings.Save(); RefreshDocumentView(); }
        private void FormatLabel_Click(object sender, RoutedEventArgs e) { _paperFormat = PrintPaperFormat.Label100x150; _settings.LastPaperFormat = _paperFormat; _settings.Save(); RefreshDocumentView(); }

        private void OrientAuto_Click(object sender, RoutedEventArgs e) { _orientationMode = PrintOrientationMode.Auto; RefreshDocumentView(); }
        private void OrientPortrait_Click(object sender, RoutedEventArgs e) { _orientationMode = PrintOrientationMode.Portrait; RefreshDocumentView(); }
        private void OrientLandscape_Click(object sender, RoutedEventArgs e) { _orientationMode = PrintOrientationMode.Landscape; RefreshDocumentView(); }

        private void ScaleFit_Click(object sender, RoutedEventArgs e) { _scaleMode = PrintScaleMode.FitPrintableArea; _settings.LastScaleMode = _scaleMode; _settings.Save(); RefreshDocumentView(); }
        private void Scale100_Click(object sender, RoutedEventArgs e) { _scaleMode = PrintScaleMode.ActualSize100; _settings.LastScaleMode = _scaleMode; _settings.Save(); RefreshDocumentView(); }

        private void RangeAll_Click(object sender, RoutedEventArgs e) { _pageRangeMode = PrintPageRangeMode.All; UpdateAllOptionButtonsVisuals(); }
        private void RangeCurrent_Click(object sender, RoutedEventArgs e) { _pageRangeMode = PrintPageRangeMode.Current; UpdateAllOptionButtonsVisuals(); }
        private void RangeCustom_Click(object sender, RoutedEventArgs e) { _pageRangeMode = PrintPageRangeMode.Custom; UpdateAllOptionButtonsVisuals(); }

        private void ScanSrcFlatbed_Click(object sender, RoutedEventArgs e) { _scanSource = ScanSourceMode.Flatbed; _settings.LastScanSource = _scanSource; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void ScanSrcAdf_Click(object sender, RoutedEventArgs e) { _scanSource = ScanSourceMode.AdfSimplex; _settings.LastScanSource = _scanSource; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void ScanSrcDuplex_Click(object sender, RoutedEventArgs e) { _scanSource = ScanSourceMode.AdfDuplex; _settings.LastScanSource = _scanSource; _settings.Save(); UpdateAllOptionButtonsVisuals(); }

        private void ScanDpi150_Click(object sender, RoutedEventArgs e) { _scanDpi = 150; _settings.ScanDpi = 150; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void ScanDpi300_Click(object sender, RoutedEventArgs e) { _scanDpi = 300; _settings.ScanDpi = 300; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void ScanDpi600_Click(object sender, RoutedEventArgs e) { _scanDpi = 600; _settings.ScanDpi = 600; _settings.Save(); UpdateAllOptionButtonsVisuals(); }

        private void ScanColColor_Click(object sender, RoutedEventArgs e) { _scanColor = ScanColorMode.Color; _settings.LastScanColor = _scanColor; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void ScanColGray_Click(object sender, RoutedEventArgs e) { _scanColor = ScanColorMode.Grayscale; _settings.LastScanColor = _scanColor; _settings.Save(); UpdateAllOptionButtonsVisuals(); }
        private void ScanColBw_Click(object sender, RoutedEventArgs e) { _scanColor = ScanColorMode.ReceiptBw; _settings.LastScanColor = _scanColor; _settings.Save(); UpdateAllOptionButtonsVisuals(); }

        private void ProtoAuto_Click(object sender, RoutedEventArgs e) { _scanProtocol = ScanProtocolMode.Auto; UpdateAllOptionButtonsVisuals(); }
        private void ProtoEscl_Click(object sender, RoutedEventArgs e) { _scanProtocol = ScanProtocolMode.EsclLan; UpdateAllOptionButtonsVisuals(); }
        private void ProtoWia_Click(object sender, RoutedEventArgs e) { _scanProtocol = ScanProtocolMode.WiaLocal; UpdateAllOptionButtonsVisuals(); }

        private void ZoomIn_Click(object sender, RoutedEventArgs e) { _zoomFactor = Math.Clamp(_zoomFactor + 0.15, 0.40, 3.0); RefreshDocumentView(); }
        private void ZoomOut_Click(object sender, RoutedEventArgs e) { _zoomFactor = Math.Clamp(_zoomFactor - 0.15, 0.40, 3.0); RefreshDocumentView(); }
        private void ZoomReset_Click(object sender, MouseButtonEventArgs e) { _zoomFactor = 1.0; RefreshDocumentView(); }

        private void Window_PreviewMouseWheel(object sender, MouseWheelEventArgs e)
        {
            if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                e.Handled = true;
                double delta = e.Delta > 0 ? 0.12 : -0.12;
                _zoomFactor = Math.Clamp(_zoomFactor + delta, 0.40, 3.0);
                RefreshDocumentView();
            }
        }

        private void Window_KeyDown(object sender, KeyEventArgs e)
        {
            if (Keyboard.FocusedElement is TextBox) return;

            bool ctrl = (Keyboard.Modifiers & ModifierKeys.Control) != 0;
            if (ctrl && e.Key == Key.O)
            {
                e.Handled = true;
                OpenFile_Click(this, new RoutedEventArgs());
            }
            else if (ctrl && e.Key == Key.S)
            {
                e.Handled = true;
                SaveAsPdf_Click(this, new RoutedEventArgs());
            }
            else if (ctrl && e.Key == Key.P)
            {
                e.Handled = true;
                ExecutePrint_Click(this, new RoutedEventArgs());
            }
            else if (ctrl && (e.Key == Key.OemPlus || e.Key == Key.Add))
            {
                e.Handled = true;
                ZoomIn_Click(this, new RoutedEventArgs());
            }
            else if (ctrl && (e.Key == Key.OemMinus || e.Key == Key.Subtract))
            {
                e.Handled = true;
                ZoomOut_Click(this, new RoutedEventArgs());
            }
            else if (ctrl && (e.Key == Key.D0 || e.Key == Key.NumPad0))
            {
                e.Handled = true;
                _zoomFactor = 1.0;
                RefreshDocumentView();
            }
            else if (e.Key == Key.Delete && _pages.Count > 0)
            {
                e.Handled = true;
                DeletePage_Click(this, new RoutedEventArgs());
            }
            else if (e.Key == Key.Escape)
            {
                if (_isCropping) ExitCropMode();
                else if (_busyCts != null) CancelBusyOperation_Click(this, new RoutedEventArgs());
            }
        }
    }
}

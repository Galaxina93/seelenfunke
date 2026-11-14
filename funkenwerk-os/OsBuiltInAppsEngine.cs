using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Interop;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Brush = System.Windows.Media.Brush;
using Brushes = System.Windows.Media.Brushes;
using Clipboard = System.Windows.Clipboard;
using Color = System.Windows.Media.Color;
using Cursors = System.Windows.Input.Cursors;
using HorizontalAlignment = System.Windows.HorizontalAlignment;
using Image = System.Windows.Controls.Image;
using KeyEventArgs = System.Windows.Input.KeyEventArgs;
using MouseEventArgs = System.Windows.Input.MouseEventArgs;
using Point = System.Windows.Point;
using Rectangle = System.Windows.Shapes.Rectangle;
using VerticalAlignment = System.Windows.VerticalAlignment;

namespace FunkenwerkOS
{
    // =========================================================================
    // 1. TASCHENRECHNER ENGINE (128-BIT DECIMAL + 19% MWST/NETTO + VERLAUF)
    // =========================================================================
    public sealed class CalcHistoryItem
    {
        public string Expression { get; set; } = "";
        public string ResultFormatted { get; set; } = "";
        public decimal RawValue { get; set; }
    }

    public sealed class OsCalculatorEngine
    {
        private static readonly CultureInfo De = new("de-DE");
        private string _inputBuffer = "0";
        private decimal? _storedLeft;
        private string? _pendingOp;
        private bool _resetBufferOnNextDigit;
        private bool _hasError;

        public List<CalcHistoryItem> History { get; } = new();
        public string ExpressionHeader { get; private set; } = "";

        public string CurrentDisplay
        {
            get
            {
                if (_hasError) return "Nicht definiert";
                return FormatBufferForDisplay(_inputBuffer);
            }
        }

        public decimal CurrentValue => ParseBuffer(_inputBuffer);

        public void ClearAll()
        {
            _inputBuffer = "0";
            _storedLeft = null;
            _pendingOp = null;
            _resetBufferOnNextDigit = false;
            _hasError = false;
            ExpressionHeader = "";
        }

        public void Backspace()
        {
            if (_hasError || _resetBufferOnNextDigit)
            {
                ClearEntryOnly();
                return;
            }

            if (_inputBuffer.Length <= 1 || (_inputBuffer.Length == 2 && _inputBuffer.StartsWith('-')))
                _inputBuffer = "0";
            else
                _inputBuffer = _inputBuffer.Substring(0, _inputBuffer.Length - 1);
        }

        public void ClearEntryOnly()
        {
            _hasError = false;
            _inputBuffer = "0";
            _resetBufferOnNextDigit = false;
        }

        public void InputDigit(char d)
        {
            if (d < '0' || d > '9') return;
            if (_hasError) ClearAll();

            if (_resetBufferOnNextDigit)
            {
                _inputBuffer = d.ToString();
                _resetBufferOnNextDigit = false;
                return;
            }

            int digitsCount = _inputBuffer.Count(char.IsDigit);
            if (digitsCount >= 16) return;

            if (_inputBuffer == "0")
                _inputBuffer = d.ToString();
            else if (_inputBuffer == "-0")
                _inputBuffer = "-" + d;
            else
                _inputBuffer += d;
        }

        public void InputDecimal()
        {
            if (_hasError) ClearAll();
            if (_resetBufferOnNextDigit)
            {
                _inputBuffer = "0,";
                _resetBufferOnNextDigit = false;
                return;
            }

            if (!_inputBuffer.Contains(','))
                _inputBuffer += ",";
        }

        public void ToggleSign()
        {
            if (_hasError) return;
            if (_inputBuffer == "0" || _inputBuffer == "0,") return;
            _inputBuffer = _inputBuffer.StartsWith('-')
                ? _inputBuffer.Substring(1)
                : "-" + _inputBuffer;
        }

        public void InputOperator(string op)
        {
            if (_hasError) ClearAll();

            decimal right = ParseBuffer(_inputBuffer);
            if (_storedLeft.HasValue && !string.IsNullOrEmpty(_pendingOp) && !_resetBufferOnNextDigit)
            {
                if (!TryCompute(_storedLeft.Value, right, _pendingOp!, out decimal mid))
                {
                    _hasError = true;
                    return;
                }
                _storedLeft = mid;
                _inputBuffer = DecimalToCleanBuffer(mid);
            }
            else
            {
                _storedLeft = right;
            }

            _pendingOp = op;
            ExpressionHeader = $"{FormatDecimal(_storedLeft.Value)} {PrettyOp(op)}";
            _resetBufferOnNextDigit = true;
        }

        public void InputEquals()
        {
            if (_hasError || !_storedLeft.HasValue || string.IsNullOrEmpty(_pendingOp))
                return;

            decimal left = _storedLeft.Value;
            decimal right = ParseBuffer(_inputBuffer);
            string op = _pendingOp!;

            if (!TryCompute(left, right, op, out decimal result))
            {
                _hasError = true;
                ExpressionHeader = "";
                _storedLeft = null;
                _pendingOp = null;
                return;
            }

            string expr = $"{FormatDecimal(left)} {PrettyOp(op)} {FormatDecimal(right)} =";
            string resFmt = FormatDecimal(result);
            ExpressionHeader = expr;
            _inputBuffer = DecimalToCleanBuffer(result);
            _storedLeft = null;
            _pendingOp = null;
            _resetBufferOnNextDigit = true;

            PushHistory(expr, resFmt, result);
        }

        public void ApplyPercent()
        {
            if (_hasError) return;
            decimal cur = ParseBuffer(_inputBuffer);
            decimal pctVal = (_storedLeft.HasValue && (_pendingOp == "+" || _pendingOp == "-"))
                ? (_storedLeft.Value * cur / 100m)
                : (cur / 100m);

            _inputBuffer = DecimalToCleanBuffer(pctVal);
            _resetBufferOnNextDigit = true;
        }

        public void ApplyPlusVat19()
        {
            if (_hasError) ClearAll();
            decimal net = ParseBuffer(_inputBuffer);
            decimal gross = Math.Round(net * 1.19m, 2, MidpointRounding.AwayFromZero);
            string expr = $"{FormatDecimal(net)} + 19% MwSt =";
            string resFmt = FormatDecimal(gross);
            ExpressionHeader = expr;
            _inputBuffer = DecimalToCleanBuffer(gross);
            _storedLeft = null;
            _pendingOp = null;
            _resetBufferOnNextDigit = true;
            PushHistory(expr, resFmt, gross);
        }

        public void ApplyNetFromGross19()
        {
            if (_hasError) ClearAll();
            decimal gross = ParseBuffer(_inputBuffer);
            decimal net = Math.Round(gross / 1.19m, 2, MidpointRounding.AwayFromZero);
            string expr = $"{FormatDecimal(gross)} Netto (19%) =";
            string resFmt = FormatDecimal(net);
            ExpressionHeader = expr;
            _inputBuffer = DecimalToCleanBuffer(net);
            _storedLeft = null;
            _pendingOp = null;
            _resetBufferOnNextDigit = true;
            PushHistory(expr, resFmt, net);
        }

        public void LoadValueDirect(decimal val, string header = "")
        {
            _hasError = false;
            _inputBuffer = DecimalToCleanBuffer(val);
            ExpressionHeader = header;
            _resetBufferOnNextDigit = true;
        }

        private void PushHistory(string expr, string formatted, decimal val)
        {
            History.Insert(0, new CalcHistoryItem { Expression = expr, ResultFormatted = formatted, RawValue = val });
            if (History.Count > 25)
                History.RemoveAt(History.Count - 1);
        }

        private static bool TryCompute(decimal a, decimal b, string op, out decimal res)
        {
            res = 0m;
            try
            {
                switch (op)
                {
                    case "+": res = a + b; return true;
                    case "-": res = a - b; return true;
                    case "*": res = a * b; return true;
                    case "/":
                        if (b == 0m) return false;
                        res = a / b;
                        return true;
                    default:
                        return false;
                }
            }
            catch
            {
                return false;
            }
        }

        private static string PrettyOp(string op) => op switch
        {
            "*" => "×",
            "/" => "÷",
            "-" => "−",
            _ => op
        };

        private static decimal ParseBuffer(string buf)
        {
            string clean = (buf ?? "0").Trim().TrimEnd(',');
            if (string.IsNullOrEmpty(clean) || clean == "-") return 0m;
            return decimal.TryParse(clean, NumberStyles.Any, De, out decimal v) ? v : 0m;
        }

        private static string DecimalToCleanBuffer(decimal val)
        {
            decimal rounded = Math.Round(val, 8, MidpointRounding.AwayFromZero);
            return rounded.ToString("0.########", De);
        }

        public static string FormatDecimal(decimal val)
        {
            decimal rounded = Math.Round(val, 8, MidpointRounding.AwayFromZero);
            return rounded.ToString("#,##0.########", De);
        }

        private static string FormatBufferForDisplay(string buf)
        {
            if (string.IsNullOrEmpty(buf)) return "0";
            bool neg = buf.StartsWith('-');
            string unsigned = neg ? buf.Substring(1) : buf;
            string[] parts = unsigned.Split(',');
            string intPart = parts[0];
            if (decimal.TryParse(intPart, NumberStyles.Integer, CultureInfo.InvariantCulture, out decimal intNum))
                intPart = intNum.ToString("#,##0", De);

            string sign = neg ? "-" : "";
            return parts.Length > 1 ? $"{sign}{intPart},{parts[1]}" : $"{sign}{intPart}";
        }
    }

    // =========================================================================
    // 2. KURZNOTIZEN / STICKY NOTES VAULT (AUTO-SAVE + EDLE AKZENTFARBEN)
    // =========================================================================
    public sealed class StickyNoteDto : INotifyPropertyChanged
    {
        private string _content = "";
        private string _colorKey = "gold";
        private DateTime _updatedAt = DateTime.Now;
        private bool _isSelected;

        public event PropertyChangedEventHandler? PropertyChanged;

        public string Id { get; set; } = Guid.NewGuid().ToString("N");

        public string Content
        {
            get => _content;
            set
            {
                if (_content != value)
                {
                    _content = value ?? "";
                    Notify(nameof(Content));
                    Notify(nameof(TitleLine));
                    Notify(nameof(PreviewSnippet));
                }
            }
        }

        public string ColorKey
        {
            get => _colorKey;
            set
            {
                if (_colorKey != value)
                {
                    _colorKey = value ?? "gold";
                    Notify(nameof(ColorKey));
                    Notify(nameof(AccentBrush));
                    Notify(nameof(CardTintBrush));
                }
            }
        }

        public DateTime UpdatedAt
        {
            get => _updatedAt;
            set
            {
                _updatedAt = value;
                Notify(nameof(UpdatedAt));
                Notify(nameof(UpdatedFormatted));
            }
        }

        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsSelected
        {
            get => _isSelected;
            set
            {
                if (_isSelected != value)
                {
                    _isSelected = value;
                    Notify(nameof(IsSelected));
                }
            }
        }

        [System.Text.Json.Serialization.JsonIgnore]
        public string TitleLine
        {
            get
            {
                if (string.IsNullOrWhiteSpace(_content)) return "Neue Notiz";
                foreach (string raw in _content.Split('\n'))
                {
                    string t = raw.Trim();
                    if (!string.IsNullOrEmpty(t))
                        return t.Length > 34 ? t.Substring(0, 33) + "…" : t;
                }
                return "Neue Notiz";
            }
        }

        [System.Text.Json.Serialization.JsonIgnore]
        public string PreviewSnippet
        {
            get
            {
                if (string.IsNullOrWhiteSpace(_content)) return "Leer";
                var lines = _content.Split('\n').Select(l => l.Trim()).Where(l => !string.IsNullOrEmpty(l)).Skip(1).ToList();
                if (lines.Count == 0) return UpdatedFormatted;
                string second = lines[0];
                return second.Length > 42 ? second.Substring(0, 41) + "…" : second;
            }
        }

        [System.Text.Json.Serialization.JsonIgnore]
        public string UpdatedFormatted => _updatedAt.Date == DateTime.Today
            ? _updatedAt.ToString("HH:mm")
            : _updatedAt.ToString("dd.MM. HH:mm");

        [System.Text.Json.Serialization.JsonIgnore]
        public Brush AccentBrush => ResolveAccentBrush(_colorKey);

        [System.Text.Json.Serialization.JsonIgnore]
        public Brush CardTintBrush => ResolveTintBrush(_colorKey);

        public static SolidColorBrush ResolveAccentBrush(string? key)
        {
            string hex = (key ?? "gold").ToLowerInvariant() switch
            {
                "champagne" => "#FDE68A",
                "emerald" => "#10B981",
                "ocean" => "#38BDF8",
                "rose" => "#F472B6",
                "slate" => "#94A3B8",
                _ => "#C5A059"
            };
            var br = new SolidColorBrush((Color)System.Windows.Media.ColorConverter.ConvertFromString(hex));
            br.Freeze();
            return br;
        }

        public static SolidColorBrush ResolveTintBrush(string? key)
        {
            string hex = (key ?? "gold").ToLowerInvariant() switch
            {
                "champagne" => "#24FDE68A",
                "emerald" => "#2210B981",
                "ocean" => "#2238BDF8",
                "rose" => "#22F472B6",
                "slate" => "#2294A3B8",
                _ => "#24C5A059"
            };
            var br = new SolidColorBrush((Color)System.Windows.Media.ColorConverter.ConvertFromString(hex));
            br.Freeze();
            return br;
        }

        private void Notify(string prop) => PropertyChanged?.Invoke(this, new PropertyChangedEventArgs(prop));
    }

    public static class StickyNotesVault
    {
        public static string NotesDir
        {
            get
            {
                string dir = Path.Combine(OsVaultAndStorage.DataDir, "StickyNotes");
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    OsVaultAndStorage.LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        public static string NotesFilePath => Path.Combine(NotesDir, "sticky_notes.json");

        public static List<StickyNoteDto> LoadNotes()
        {
            try
            {
                if (File.Exists(NotesFilePath))
                {
                    string json = File.ReadAllText(NotesFilePath, Encoding.UTF8);
                    var list = JsonSerializer.Deserialize<List<StickyNoteDto>>(json);
                    if (list != null && list.Count > 0)
                        return list.OrderByDescending(n => n.UpdatedAt).ToList();
                }
            }
            catch { }

            var initial = new List<StickyNoteDto>
            {
                new()
                {
                    ColorKey = "gold",
                    Content = "Ideen & Schnellnotizen\n",
                    UpdatedAt = DateTime.Now
                }
            };
            SaveNotes(initial);
            return initial;
        }

        public static void SaveNotes(IEnumerable<StickyNoteDto> notes)
        {
            try
            {
                string tmp = NotesFilePath + ".tmp";
                string json = JsonSerializer.Serialize(notes.ToList(), new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(tmp, json, new UTF8Encoding(false));
                File.Move(tmp, NotesFilePath, overwrite: true);
                OsVaultAndStorage.LockDownFileAcl(NotesFilePath);
            }
            catch { }
        }
    }

    // =========================================================================
    // 3. AUSSCHNEIDEN (DPI-EXACT FULLSCREEN SNIPPING OVERLAY & EXPORT)
    // =========================================================================
    public sealed class SnipCaptureOverlayWindow : Window
    {
        private readonly System.Drawing.Bitmap _fullScreenBitmap;
        private readonly Canvas _overlayCanvas;
        private readonly Rectangle _dimTop;
        private readonly Rectangle _dimBottom;
        private readonly Rectangle _dimLeft;
        private readonly Rectangle _dimRight;
        private readonly Border _selectionFrame;
        private readonly Border _sizeBadge;
        private readonly TextBlock _sizeBadgeTxt;

        private bool _isDragging;
        private Point _dragStart;
        private Rect _currentSelectionDip;

        public BitmapSource? CapturedBitmapSource { get; private set; }

        public SnipCaptureOverlayWindow(System.Drawing.Bitmap preCapturedVirtualScreen)
        {
            _fullScreenBitmap = preCapturedVirtualScreen;

            WindowStyle = WindowStyle.None;
            AllowsTransparency = false;
            ResizeMode = ResizeMode.NoResize;
            Topmost = true;
            ShowInTaskbar = false;
            Cursor = Cursors.Cross;
            Background = Brushes.Black;

            Left = SystemParameters.VirtualScreenLeft;
            Top = SystemParameters.VirtualScreenTop;
            Width = SystemParameters.VirtualScreenWidth;
            Height = SystemParameters.VirtualScreenHeight;

            var rootGrid = new Grid();
            var bgImage = new Image
            {
                Source = ConvertDrawingBitmapToFrozenSource(_fullScreenBitmap),
                Stretch = Stretch.Fill
            };
            RenderOptions.SetBitmapScalingMode(bgImage, BitmapScalingMode.HighQuality);
            rootGrid.Children.Add(bgImage);

            _overlayCanvas = new Canvas { Background = Brushes.Transparent };
            var dimBrush = new SolidColorBrush(Color.FromArgb(148, 6, 8, 13));
            dimBrush.Freeze();

            _dimTop = new Rectangle { Fill = dimBrush };
            _dimBottom = new Rectangle { Fill = dimBrush };
            _dimLeft = new Rectangle { Fill = dimBrush };
            _dimRight = new Rectangle { Fill = dimBrush };

            _overlayCanvas.Children.Add(_dimTop);
            _overlayCanvas.Children.Add(_dimBottom);
            _overlayCanvas.Children.Add(_dimLeft);
            _overlayCanvas.Children.Add(_dimRight);

            var goldBrush = new SolidColorBrush(Color.FromRgb(242, 197, 114));
            goldBrush.Freeze();

            _selectionFrame = new Border
            {
                BorderBrush = goldBrush,
                BorderThickness = new Thickness(1.8),
                Background = Brushes.Transparent,
                Visibility = Visibility.Collapsed
            };
            _overlayCanvas.Children.Add(_selectionFrame);

            _sizeBadgeTxt = new TextBlock
            {
                Foreground = new SolidColorBrush(Color.FromRgb(252, 231, 178)),
                FontSize = 11.5,
                FontWeight = FontWeights.Bold
            };
            _sizeBadge = new Border
            {
                Background = new SolidColorBrush(Color.FromArgb(228, 12, 15, 22)),
                BorderBrush = goldBrush,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(7),
                Padding = new Thickness(9, 4, 9, 4),
                Child = _sizeBadgeTxt,
                Visibility = Visibility.Collapsed
            };
            _overlayCanvas.Children.Add(_sizeBadge);

            var topHint = new Border
            {
                Background = new SolidColorBrush(Color.FromArgb(225, 11, 14, 21)),
                BorderBrush = goldBrush,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(16, 7, 16, 7),
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Top,
                Margin = new Thickness(0, 22, 0, 0),
                IsHitTestVisible = false,
                Child = new TextBlock
                {
                    Text = "✂  Bereich aufziehen  ·  ESC Abbrechen",
                    Foreground = new SolidColorBrush(Color.FromRgb(252, 231, 178)),
                    FontSize = 12.5,
                    FontWeight = FontWeights.Bold
                }
            };

            rootGrid.Children.Add(_overlayCanvas);
            rootGrid.Children.Add(topHint);
            Content = rootGrid;

            Loaded += (_, _) =>
            {
                UpdateDimMask(new Rect(0, 0, 0, 0));
                Activate();
                Focus();
            };

            KeyDown += OnOverlayKeyDown;
            MouseRightButtonUp += (_, _) => CancelAndClose();
            MouseLeftButtonDown += OnOverlayMouseDown;
            MouseMove += OnOverlayMouseMove;
            MouseLeftButtonUp += OnOverlayMouseUp;
        }

        public static System.Drawing.Bitmap CaptureFullVirtualScreenBitmap()
        {
            var vs = System.Windows.Forms.SystemInformation.VirtualScreen;
            int w = Math.Max(1, vs.Width);
            int h = Math.Max(1, vs.Height);
            var bmp = new System.Drawing.Bitmap(w, h, System.Drawing.Imaging.PixelFormat.Format32bppArgb);
            using var g = System.Drawing.Graphics.FromImage(bmp);
            g.CopyFromScreen(vs.Left, vs.Top, 0, 0, new System.Drawing.Size(w, h), System.Drawing.CopyPixelOperation.SourceCopy);
            return bmp;
        }

        private void OnOverlayKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape)
            {
                e.Handled = true;
                CancelAndClose();
            }
        }

        private void OnOverlayMouseDown(object sender, MouseButtonEventArgs e)
        {
            _isDragging = true;
            _dragStart = e.GetPosition(_overlayCanvas);
            _currentSelectionDip = new Rect(_dragStart, new System.Windows.Size(0, 0));
            _selectionFrame.Visibility = Visibility.Visible;
            _sizeBadge.Visibility = Visibility.Visible;
            _overlayCanvas.CaptureMouse();
        }

        private void OnOverlayMouseMove(object sender, MouseEventArgs e)
        {
            if (!_isDragging) return;
            Point cur = e.GetPosition(_overlayCanvas);
            double x = Math.Clamp(Math.Min(_dragStart.X, cur.X), 0, ActualWidth);
            double y = Math.Clamp(Math.Min(_dragStart.Y, cur.Y), 0, ActualHeight);
            double w = Math.Clamp(Math.Abs(cur.X - _dragStart.X), 0, ActualWidth - x);
            double h = Math.Clamp(Math.Abs(cur.Y - _dragStart.Y), 0, ActualHeight - y);

            _currentSelectionDip = new Rect(x, y, w, h);
            UpdateDimMask(_currentSelectionDip);

            Canvas.SetLeft(_selectionFrame, x);
            Canvas.SetTop(_selectionFrame, y);
            _selectionFrame.Width = w;
            _selectionFrame.Height = h;

            var pxRect = MapDipToPixelRect(_currentSelectionDip);
            _sizeBadgeTxt.Text = $"{pxRect.Width} × {pxRect.Height} px";
            Canvas.SetLeft(_sizeBadge, Math.Clamp(x, 6, Math.Max(8, ActualWidth - 130)));
            Canvas.SetTop(_sizeBadge, y > 34 ? y - 30 : Math.Min(ActualHeight - 32, y + h + 6));
        }

        private void OnOverlayMouseUp(object sender, MouseButtonEventArgs e)
        {
            if (!_isDragging) return;
            _isDragging = false;
            _overlayCanvas.ReleaseMouseCapture();

            var pxRect = MapDipToPixelRect(_currentSelectionDip);
            if (pxRect.Width < 8 || pxRect.Height < 8)
            {
                _selectionFrame.Visibility = Visibility.Collapsed;
                _sizeBadge.Visibility = Visibility.Collapsed;
                UpdateDimMask(new Rect(0, 0, 0, 0));
                return;
            }

            try
            {
                using var cropped = _fullScreenBitmap.Clone(pxRect, System.Drawing.Imaging.PixelFormat.Format32bppArgb);
                CapturedBitmapSource = ConvertDrawingBitmapToFrozenSource(cropped);
                try { Clipboard.SetImage(CapturedBitmapSource); } catch { }
                DialogResult = true;
            }
            catch
            {
                DialogResult = false;
            }
            Close();
        }

        private void CancelAndClose()
        {
            DialogResult = false;
            Close();
        }

        private System.Drawing.Rectangle MapDipToPixelRect(Rect dip)
        {
            double aw = Math.Max(1.0, ActualWidth);
            double ah = Math.Max(1.0, ActualHeight);
            int px = Math.Clamp((int)Math.Round((dip.X / aw) * _fullScreenBitmap.Width), 0, _fullScreenBitmap.Width - 1);
            int py = Math.Clamp((int)Math.Round((dip.Y / ah) * _fullScreenBitmap.Height), 0, _fullScreenBitmap.Height - 1);
            int pw = Math.Clamp((int)Math.Round((dip.Width / aw) * _fullScreenBitmap.Width), 1, _fullScreenBitmap.Width - px);
            int ph = Math.Clamp((int)Math.Round((dip.Height / ah) * _fullScreenBitmap.Height), 1, _fullScreenBitmap.Height - py);
            return new System.Drawing.Rectangle(px, py, pw, ph);
        }

        private void UpdateDimMask(Rect hole)
        {
            double w = Math.Max(1, ActualWidth);
            double h = Math.Max(1, ActualHeight);

            if (hole.Width <= 1 || hole.Height <= 1)
            {
                Canvas.SetLeft(_dimTop, 0); Canvas.SetTop(_dimTop, 0); _dimTop.Width = w; _dimTop.Height = h;
                _dimBottom.Width = 0; _dimLeft.Width = 0; _dimRight.Width = 0;
                return;
            }

            Canvas.SetLeft(_dimTop, 0); Canvas.SetTop(_dimTop, 0);
            _dimTop.Width = w; _dimTop.Height = Math.Max(0, hole.Top);

            Canvas.SetLeft(_dimBottom, 0); Canvas.SetTop(_dimBottom, hole.Bottom);
            _dimBottom.Width = w; _dimBottom.Height = Math.Max(0, h - hole.Bottom);

            Canvas.SetLeft(_dimLeft, 0); Canvas.SetTop(_dimLeft, hole.Top);
            _dimLeft.Width = Math.Max(0, hole.Left); _dimLeft.Height = Math.Max(0, hole.Height);

            Canvas.SetLeft(_dimRight, hole.Right); Canvas.SetTop(_dimRight, hole.Top);
            _dimRight.Width = Math.Max(0, w - hole.Right); _dimRight.Height = Math.Max(0, hole.Height);
        }

        public static BitmapSource ConvertDrawingBitmapToFrozenSource(System.Drawing.Bitmap bmp)
        {
            var rect = new System.Drawing.Rectangle(0, 0, bmp.Width, bmp.Height);
            var data = bmp.LockBits(rect, System.Drawing.Imaging.ImageLockMode.ReadOnly, System.Drawing.Imaging.PixelFormat.Format32bppArgb);
            try
            {
                var src = BitmapSource.Create(
                    bmp.Width,
                    bmp.Height,
                    96.0,
                    96.0,
                    PixelFormats.Bgra32,
                    null,
                    data.Scan0,
                    data.Stride * bmp.Height,
                    data.Stride);
                src.Freeze();
                return src;
            }
            finally
            {
                bmp.UnlockBits(data);
            }
        }

        public static BitmapSource? RenderAnnotatedSurface(FrameworkElement surface, BitmapSource baseImage)
        {
            if (surface.ActualWidth < 2 || surface.ActualHeight < 2) return baseImage;

            double dpiX = 96.0 * (baseImage.PixelWidth / surface.ActualWidth);
            double dpiY = 96.0 * (baseImage.PixelHeight / surface.ActualHeight);
            var rtb = new RenderTargetBitmap(baseImage.PixelWidth, baseImage.PixelHeight, dpiX, dpiY, PixelFormats.Pbgra32);

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                var vb = new VisualBrush(surface) { Stretch = Stretch.Fill };
                dc.DrawRectangle(vb, null, new Rect(0, 0, surface.ActualWidth, surface.ActualHeight));
            }
            rtb.Render(dv);
            rtb.Freeze();
            return rtb;
        }

        public static void SaveBitmapSourceAsPng(BitmapSource source, string filePath)
        {
            var encoder = new PngBitmapEncoder();
            encoder.Frames.Add(BitmapFrame.Create(source));
            using var fs = File.Create(filePath);
            encoder.Save(fs);
        }
    }

    // =========================================================================
    // 4. SMART FUZZY SEARCH ENGINE + WINDOWS-KEY INSTANT LAUNCHER HOOK
    // =========================================================================
    public static class OsSmartSearchEngine
    {
        private static readonly Dictionary<string, string[]> AppKeywords = new(StringComparer.OrdinalIgnoreCase)
        {
            ["app:funkenwerk"] = new[] { "zentrale", "funkenwerk", "hauptprogramm", "mail", "email", "postfach", "kalender", "kontakte", "todo", "aufgaben", "smarthome", "kamera", "marketing" },
            ["app:browser"] = new[] { "browser", "internet", "web", "webseiten", "google", "youtube", "instagram", "whatsapp", "chrome", "edge", "surfen" },
            ["app:pdf"] = new[] { "pdf", "dokument", "unterschreiben", "signatur", "formular", "stempel", "adobe" },
            ["app:drucker"] = new[] { "drucker", "scanner", "scannen", "kopieren", "drucken", "hp", "tinte", "beleg", "papier" },
            ["app:ftp"] = new[] { "ftp", "sftp", "upload", "server", "dateitransfer", "hosting" },
            ["app:ssh"] = new[] { "ssh", "terminal", "konsole", "shell", "linux", "server", "bash" },
            ["app:player"] = new[] { "mediaplayer", "musik", "player", "audio", "video", "mp3", "mp4", "film", "abspielen", "ton", "vlc" },
            ["app:video"] = new[] { "videostudio", "video", "schnitt", "schneiden", "obs", "aufnahme", "kamera" },
            ["app:craft"] = new[] { "craft", "kreativstudio", "reel", "reels", "tiktok", "social", "animation", "kreativ", "storytold", "soundpool" },
            ["app:tresor"] = new[] { "tresor", "passwort", "passwörter", "keepass", "kdbx", "vault", "safe", "2fa", "totp", "zugangsdaten", "login", "schlüssel" },
            ["sys:meinedaten"] = new[] { "meine daten", "trichter", "finanzen", "steuern", "haus", "immobilie", "versicherung", "familie", "gesundheit", "behörde", "archiv", "dokumente" },
            ["sys:calculator"] = new[] { "taschenrechner", "rechner", "rechnen", "calc", "calculator", "plus", "minus", "mal", "prozent", "mwst", "steuer", "brutto", "netto", "zahlen" },
            ["sys:snip"] = new[] { "ausschneiden", "snipping", "snip", "screenshot", "bildschirmfoto", "bildschirmausschnitt", "foto", "erfassen", "schere" },
            ["sys:notes"] = new[] { "notizen", "kurznotizen", "sticky", "notes", "memo", "zettel", "notiz", "aufschreiben", "text" },
            ["sys:explorer"] = new[] { "dateien", "explorer", "ordner", "laufwerk", "festplatte", "usb", "dokumente", "downloads", "dateibrowser" },
            ["sys:leitstand"] = new[] { "leitstand", "dashboard", "bestellungen", "umsatz", "aufträge", "manufaktur", "status" },
            ["sys:settings"] = new[] { "einstellungen", "system", "standardprogramme", "taskleiste", "taskleisteneinstellungen", "hintergrund", "design", "festung", "firewall", "konto", "usb" },
            ["sys:recycle"] = new[] { "papierkorb", "müll", "gelöscht", "wiederherstellen", "leeren" }
        };

        public static List<DesktopItemModel> FilterAndRank(string rawQuery, IEnumerable<DesktopItemModel> catalog)
        {
            string q = Normalize(rawQuery);
            if (string.IsNullOrEmpty(q))
                return catalog.Take(16).ToList();

            var scored = new List<(DesktopItemModel Item, int Score)>();
            foreach (var item in catalog)
            {
                int score = ComputeMatchScore(q, item);
                if (score > 0)
                    scored.Add((item, score));
            }

            return scored
                .OrderByDescending(x => x.Score)
                .ThenBy(x => x.Item.Title, StringComparer.OrdinalIgnoreCase)
                .Select(x => x.Item)
                .Take(16)
                .ToList();
        }

        public static int ComputeMatchScore(string normalizedQuery, DesktopItemModel item)
        {
            string q = Normalize(normalizedQuery);
            if (string.IsNullOrEmpty(q)) return 100;

            string title = Normalize(item.Title);
            string key = Normalize(item.AppOrToolKey);

            // 1. Direct prefix on Title (highest priority)
            if (title.StartsWith(q, StringComparison.Ordinal))
                return 1000 - Math.Min(40, title.Length);

            // 2. Word-boundary prefix inside Title
            foreach (string word in title.Split(' ', '-', '_'))
            {
                if (word.Length > 0 && word.StartsWith(q, StringComparison.Ordinal))
                    return 910;
            }

            // 3. Keyword / Synonym prefix ("re" -> "rechner" -> Taschenrechner, "snip" -> Ausschneiden, "kurz" -> Notizen)
            AppKeywords.TryGetValue(item.Id, out string[]? kws);
            if (kws != null)
            {
                foreach (string kw in kws)
                {
                    string nkw = Normalize(kw);
                    if (nkw.StartsWith(q, StringComparison.Ordinal))
                        return 860 - Math.Min(30, nkw.Length);
                }
            }

            if (key.StartsWith(q, StringComparison.Ordinal))
                return 820;

            // 4. Substring in Title
            if (title.Contains(q, StringComparison.Ordinal))
                return 740;

            // 5. Substring in Keywords
            if (kws != null && kws.Any(kw => Normalize(kw).Contains(q, StringComparison.Ordinal)))
                return 640;

            // 6. Ordered character subsequence on Title (e.g. "tr" -> Taschenrechner, "mp" -> Mediaplayer)
            if (q.Length >= 2 && IsSubsequence(q, title))
                return 460;

            // 7. Typo-tolerant near-match (Damerau-Levenshtein <= 1 for 3-5 chars, <= 2 for 6+ chars)
            if (q.Length >= 3)
            {
                int maxDist = q.Length >= 6 ? 2 : 1;
                string titlePrefix = title.Length > q.Length ? title.Substring(0, q.Length) : title;
                if (EditDistance(q, titlePrefix) <= maxDist || EditDistance(q, title) <= maxDist)
                    return 380;

                if (kws != null)
                {
                    foreach (string kw in kws)
                    {
                        string nkw = Normalize(kw);
                        string kwPref = nkw.Length > q.Length ? nkw.Substring(0, q.Length) : nkw;
                        if (EditDistance(q, kwPref) <= maxDist || EditDistance(q, nkw) <= maxDist)
                            return 340;
                    }
                }
            }

            return 0;
        }

        private static string Normalize(string? s)
        {
            if (string.IsNullOrWhiteSpace(s)) return "";
            return s.Trim().ToLowerInvariant()
                .Replace("ä", "ae")
                .Replace("ö", "oe")
                .Replace("ü", "ue")
                .Replace("ß", "ss");
        }

        private static bool IsSubsequence(string needle, string haystack)
        {
            int ni = 0;
            for (int hi = 0; hi < haystack.Length && ni < needle.Length; hi++)
            {
                if (haystack[hi] == needle[ni]) ni++;
            }
            return ni == needle.Length;
        }

        private static int EditDistance(string a, string b)
        {
            int n = a.Length, m = b.Length;
            if (Math.Abs(n - m) > 2) return 99;
            int[,] dp = new int[n + 1, m + 1];
            for (int i = 0; i <= n; i++) dp[i, 0] = i;
            for (int j = 0; j <= m; j++) dp[0, j] = j;

            for (int i = 1; i <= n; i++)
            {
                for (int j = 1; j <= m; j++)
                {
                    int cost = a[i - 1] == b[j - 1] ? 0 : 1;
                    dp[i, j] = Math.Min(
                        Math.Min(dp[i - 1, j] + 1, dp[i, j - 1] + 1),
                        dp[i - 1, j - 1] + cost);

                    if (i > 1 && j > 1 && a[i - 1] == b[j - 2] && a[i - 2] == b[j - 1])
                        dp[i, j] = Math.Min(dp[i, j], dp[i - 2, j - 2] + 1);
                }
            }
            return dp[n, m];
        }
    }

    public sealed class WindowsKeySearchHook : IDisposable
    {
        private const int WH_KEYBOARD_LL = 13;
        private const int WM_KEYDOWN = 0x0100;
        private const int WM_KEYUP = 0x0101;
        private const int VK_LWIN = 0x5B;
        private const int VK_RWIN = 0x5C;

        private delegate IntPtr LowLevelKeyboardProc(int nCode, IntPtr wParam, IntPtr lParam);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern IntPtr SetWindowsHookEx(int idHook, LowLevelKeyboardProc lpfn, IntPtr hMod, uint dwThreadId);

        [DllImport("user32.dll", SetLastError = true)]
        [return: MarshalAs(UnmanagedType.Bool)]
        private static extern bool UnhookWindowsHookEx(IntPtr hhk);

        [DllImport("user32.dll")]
        private static extern IntPtr CallNextHookEx(IntPtr hhk, int nCode, IntPtr wParam, IntPtr lParam);

        [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
        private static extern IntPtr GetModuleHandle(string? lpModuleName);

        [DllImport("user32.dll")]
        private static extern IntPtr GetForegroundWindow();

        [DllImport("user32.dll")]
        private static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);

        [DllImport("user32.dll")]
        private static extern short GetAsyncKeyState(int vKey);

        private readonly Action _onWinKeyTriggered;
        private readonly LowLevelKeyboardProc _procDelegate;
        private readonly uint _ownPid;
        private IntPtr _hookId = IntPtr.Zero;
        private bool _winHeldSwallowed;

        public WindowsKeySearchHook(Action onWinKeyTriggered)
        {
            _onWinKeyTriggered = onWinKeyTriggered;
            _procDelegate = HookCallback;
            _ownPid = (uint)Environment.ProcessId;

            try
            {
                using var curProcess = Process.GetCurrentProcess();
                using var curModule = curProcess.MainModule;
                IntPtr hMod = GetModuleHandle(curModule?.ModuleName);
                _hookId = SetWindowsHookEx(WH_KEYBOARD_LL, _procDelegate, hMod, 0);
            }
            catch { }
        }

        private IntPtr HookCallback(int nCode, IntPtr wParam, IntPtr lParam)
        {
            if (nCode >= 0)
            {
                int vkCode = Marshal.ReadInt32(lParam);
                if (vkCode == VK_LWIN || vkCode == VK_RWIN)
                {
                    int msg = wParam.ToInt32();
                    if (msg == WM_KEYDOWN && IsOwnProcessForeground() && !AnyOtherModifierDown())
                    {
                        if (!_winHeldSwallowed)
                        {
                            _winHeldSwallowed = true;
                            System.Windows.Application.Current?.Dispatcher.BeginInvoke(_onWinKeyTriggered);
                        }
                        return (IntPtr)1;
                    }
                    if (msg == WM_KEYUP && _winHeldSwallowed)
                    {
                        _winHeldSwallowed = false;
                        return (IntPtr)1;
                    }
                }
            }
            return CallNextHookEx(_hookId, nCode, wParam, lParam);
        }

        private bool IsOwnProcessForeground()
        {
            IntPtr fg = GetForegroundWindow();
            if (fg == IntPtr.Zero) return false;
            GetWindowThreadProcessId(fg, out uint fgPid);
            return fgPid == _ownPid;
        }

        private static bool AnyOtherModifierDown()
        {
            return (GetAsyncKeyState(0x10) & 0x8000) != 0 || // VK_SHIFT
                   (GetAsyncKeyState(0x11) & 0x8000) != 0 || // VK_CONTROL
                   (GetAsyncKeyState(0x12) & 0x8000) != 0;   // VK_MENU (Alt)
        }

        public void Dispose()
        {
            if (_hookId != IntPtr.Zero)
            {
                UnhookWindowsHookEx(_hookId);
                _hookId = IntPtr.Zero;
            }
        }
    }
}

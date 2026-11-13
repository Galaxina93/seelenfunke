using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;

namespace FunkenwerkFtp
{
    public sealed class DecodedTextDocument
    {
        public string Text { get; set; } = "";
        public Encoding Encoding { get; set; } = new UTF8Encoding(false);
        public string EncodingLabel { get; set; } = "UTF-8";
        public bool UseCrLf { get; set; }
    }

    public static class FtpTextBufferCodec
    {
        private static readonly HashSet<string> KnownTextExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".txt", ".md", ".json", ".xml", ".yml", ".yaml", ".toml", ".ini", ".cfg", ".conf", ".config",
            ".env", ".htaccess", ".htpasswd", ".gitignore", ".gitattributes", ".editorconfig",
            ".php", ".phtml", ".html", ".htm", ".twig", ".vue", ".svelte",
            ".css", ".scss", ".sass", ".less", ".svg",
            ".js", ".mjs", ".cjs", ".ts", ".tsx", ".jsx",
            ".cs", ".csproj", ".sln", ".xaml", ".vb", ".fs",
            ".py", ".rb", ".go", ".rs", ".java", ".kt", ".kts", ".gradle", ".c", ".cpp", ".h", ".hpp",
            ".sh", ".bash", ".zsh", ".fish", ".bat", ".cmd", ".ps1", ".psm1",
            ".sql", ".csv", ".tsv", ".log", ".lock", ".properties", ".plist", ".service", ".timer", ".nginx"
        };

        private static readonly HashSet<string> KnownTextExactNames = new(StringComparer.OrdinalIgnoreCase)
        {
            "artisan", "Dockerfile", "Makefile", "Procfile", "Vagrantfile", "Caddyfile",
            ".env", ".htaccess", ".gitignore", ".dockerignore", ".npmrc", ".babelrc", ".prettierrc",
            "composer.json", "composer.lock", "package.json", "phpunit.xml", "robots.txt", "sitemap.xml", "LICENSE", "README"
        };

        private static readonly HashSet<string> KnownImageExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".png", ".jpg", ".jpeg", ".webp", ".gif", ".bmp", ".ico"
        };

        private static readonly HashSet<string> KnownBinaryExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".zip", ".7z", ".rar", ".tar", ".gz", ".bz2", ".xz", ".iso",
            ".exe", ".dll", ".so", ".dylib", ".bin", ".pdb",
            ".mp4", ".mov", ".mkv", ".avi", ".webm", ".mp3", ".wav", ".flac", ".ogg", ".m4a",
            ".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".odt",
            ".glb", ".gltf", ".fbx", ".obj", ".ttf", ".otf", ".woff", ".woff2", ".sqlite", ".db", ".kdbx"
        };

        public static bool IsImageFileName(string fileName)
        {
            string ext = Path.GetExtension(fileName ?? "");
            return !string.IsNullOrEmpty(ext) && KnownImageExtensions.Contains(ext);
        }

        public static bool IsLikelyTextFileName(string fileName)
        {
            if (string.IsNullOrWhiteSpace(fileName)) return false;
            string name = Path.GetFileName(fileName);
            if (KnownTextExactNames.Contains(name)) return true;
            if (name.StartsWith(".env.", StringComparison.OrdinalIgnoreCase)) return true;
            if (name.EndsWith(".blade.php", StringComparison.OrdinalIgnoreCase)) return true;

            string ext = Path.GetExtension(name);
            if (string.IsNullOrEmpty(ext)) return false;
            if (KnownBinaryExtensions.Contains(ext) || KnownImageExtensions.Contains(ext)) return false;
            return KnownTextExtensions.Contains(ext);
        }

        public static bool LooksLikeTextBytes(byte[] sample)
        {
            if (sample == null || sample.Length == 0) return true;
            int checkLen = Math.Min(sample.Length, 8192);
            // UTF-16 BOMs are valid text even though they contain 0x00 bytes
            if (checkLen >= 2 && ((sample[0] == 0xFF && sample[1] == 0xFE) || (sample[0] == 0xFE && sample[1] == 0xFF)))
                return true;

            for (int i = 0; i < checkLen; i++)
            {
                if (sample[i] == 0) return false;
            }
            return true;
        }

        public static DecodedTextDocument Decode(byte[] rawBytes, bool defaultUseCrLf)
        {
            rawBytes ??= Array.Empty<byte>();
            Encoding enc;
            string label;
            int offset = 0;

            if (rawBytes.Length >= 3 && rawBytes[0] == 0xEF && rawBytes[1] == 0xBB && rawBytes[2] == 0xBF)
            {
                enc = new UTF8Encoding(encoderShouldEmitUTF8Identifier: true);
                label = "UTF-8 BOM";
                offset = 3;
            }
            else if (rawBytes.Length >= 2 && rawBytes[0] == 0xFF && rawBytes[1] == 0xFE)
            {
                enc = Encoding.Unicode;
                label = "UTF-16 LE";
                offset = 2;
            }
            else if (rawBytes.Length >= 2 && rawBytes[0] == 0xFE && rawBytes[1] == 0xFF)
            {
                enc = Encoding.BigEndianUnicode;
                label = "UTF-16 BE";
                offset = 2;
            }
            else
            {
                var strictUtf8 = new UTF8Encoding(encoderShouldEmitUTF8Identifier: false, throwOnInvalidBytes: true);
                try
                {
                    string utf8Text = strictUtf8.GetString(rawBytes);
                    bool useCrLfUtf8 = DetectLineEndings(utf8Text, defaultUseCrLf);
                    return new DecodedTextDocument
                    {
                        Text = NormalizeToLf(utf8Text),
                        Encoding = new UTF8Encoding(false),
                        EncodingLabel = "UTF-8",
                        UseCrLf = useCrLfUtf8
                    };
                }
                catch
                {
                    enc = Encoding.Latin1;
                    label = "ISO-8859-1";
                }
            }

            string decoded = enc.GetString(rawBytes, offset, rawBytes.Length - offset);
            bool useCrLf = DetectLineEndings(decoded, defaultUseCrLf);
            return new DecodedTextDocument
            {
                Text = NormalizeToLf(decoded),
                Encoding = enc,
                EncodingLabel = label,
                UseCrLf = useCrLf
            };
        }

        public static byte[] Encode(string editorText, Encoding encoding, bool useCrLf)
        {
            string normalizedLf = NormalizeToLf(editorText ?? "");
            string finalContent = useCrLf ? normalizedLf.Replace("\n", "\r\n", StringComparison.Ordinal) : normalizedLf;

            byte[] preamble = encoding.GetPreamble();
            byte[] body = encoding.GetBytes(finalContent);
            if (preamble.Length == 0) return body;

            byte[] combined = new byte[preamble.Length + body.Length];
            Buffer.BlockCopy(preamble, 0, combined, 0, preamble.Length);
            Buffer.BlockCopy(body, 0, combined, preamble.Length, body.Length);
            return combined;
        }

        private static string NormalizeToLf(string s)
        {
            if (string.IsNullOrEmpty(s)) return "";
            return s.Replace("\r\n", "\n", StringComparison.Ordinal).Replace('\r', '\n');
        }

        private static bool DetectLineEndings(string s, bool fallback)
        {
            if (string.IsNullOrEmpty(s)) return fallback;
            int crlf = 0;
            int bareLf = 0;
            for (int i = 0; i < s.Length; i++)
            {
                if (s[i] == '\n')
                {
                    if (i > 0 && s[i - 1] == '\r') crlf++;
                    else bareLf++;
                }
            }
            if (crlf == 0 && bareLf == 0) return fallback;
            return crlf > bareLf;
        }

        public static short ParseOctalPermissions(string octalText)
        {
            string clean = (octalText ?? "").Trim();
            if (clean.Length == 4 && clean[0] == '0')
                clean = clean.Substring(1);
            if (clean.Length != 3 || clean.Any(c => c < '0' || c > '7'))
                throw new FormatException("Bitte gültige 3-stellige Oktal-Rechte eingeben (z. B. 644 oder 755).");
            return Convert.ToInt16(clean, 8);
        }

        public static string PermissionsStringToOctal(string? permStr, bool isDirectory)
        {
            if (!string.IsNullOrWhiteSpace(permStr))
            {
                string p = permStr.Trim();
                if (p.Length >= 3 && p.All(c => c >= '0' && c <= '7'))
                    return p.Length > 3 ? p[^3..] : p;

                if (p.Length >= 10)
                {
                    int u = (p[1] == 'r' ? 4 : 0) + (p[2] == 'w' ? 2 : 0) + (p[3] is 'x' or 's' or 'S' ? 1 : 0);
                    int g = (p[4] == 'r' ? 4 : 0) + (p[5] == 'w' ? 2 : 0) + (p[6] is 'x' or 's' or 'S' ? 1 : 0);
                    int o = (p[7] == 'r' ? 4 : 0) + (p[8] == 'w' ? 2 : 0) + (p[9] is 'x' or 't' or 'T' ? 1 : 0);
                    return $"{u}{g}{o}";
                }
            }
            return isDirectory ? "755" : "644";
        }
    }

    // =========================================================================
    // INTEGRATED STUDIO CODE & TEXT EDITOR WINDOW (SERVER & LOKAL)
    // =========================================================================
    public sealed class FtpFileEditorWindow : Window
    {
        private readonly bool _isDark;
        private readonly bool _isRemote;
        private readonly string _displayPath;
        private readonly Func<byte[], Task> _saveBytesCallback;
        private readonly Func<byte[], Task>? _saveAndUploadCallback;

        private Encoding _encoding;
        private bool _useCrLf;
        private string _lastSavedNormalizedText;
        private bool _isDirty;
        private bool _isSaving;
        private double _fontSizePt = 13.0;

        private readonly TextBlock _titleFileTxt;
        private readonly Button _eolBtn;
        private readonly TextBlock _encodingBadgeTxt;
        private readonly Button _wrapBtn;
        private readonly Button _saveBtn;
        private readonly Border _findBarBorder;
        private readonly TextBox _findBox;
        private readonly TextBox _replaceBox;
        private readonly ScrollViewer _gutterScroll;
        private readonly TextBlock _lineNumbersTxt;
        private readonly TextBox _editorBox;
        private readonly TextBlock _cursorInfoTxt;
        private readonly TextBlock _saveStatusTxt;

        public FtpFileEditorWindow(
            Window owner,
            bool isDark,
            bool isRemote,
            string fileName,
            string displayPath,
            DecodedTextDocument doc,
            Func<byte[], Task> saveBytesCallback,
            Func<byte[], Task>? saveAndUploadCallback = null)
        {
            if (owner.IsLoaded) Owner = owner;
            _isDark = isDark;
            _isRemote = isRemote;
            _displayPath = displayPath;
            _encoding = doc.Encoding;
            _useCrLf = doc.UseCrLf;
            _saveBytesCallback = saveBytesCallback;
            _saveAndUploadCallback = saveAndUploadCallback;
            _lastSavedNormalizedText = doc.Text;

            Title = $"{fileName} · {(isRemote ? "SERVER" : "LOKAL")} · Funkenwerk FTP Editor";
            Icon = owner.Icon;
            Width = 1080;
            Height = 760;
            MinWidth = 680;
            MinHeight = 440;
            WindowStartupLocation = WindowStartupLocation.CenterOwner;

            var bgRoot = BrushHex(isDark ? "#0F1117" : "#F3F4F6");
            var bgBar = BrushHex(isDark ? "#12151E" : "#FFFFFF");
            var bgEditor = BrushHex(isDark ? "#0B0D13" : "#FFFFFF");
            var bgGutter = BrushHex(isDark ? "#10131C" : "#F9FAFB");
            var bgBtn = BrushHex(isDark ? "#1B1F2B" : "#F3F4F6");
            var borderCol = BrushHex(isDark ? "#252A3A" : "#E5E7EB");
            var fgPrimary = BrushHex(isDark ? "#F9FAFB" : "#111827");
            var fgMuted = BrushHex(isDark ? "#9CA3AF" : "#6B7280");
            var gold = BrushHex("#C5A059");

            Background = bgRoot;
            Foreground = fgPrimary;

            var root = new Grid { Background = bgRoot };
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(54) });
            root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(34) });

            // 1. TOP TOOLBAR
            var topBar = new Border
            {
                Background = bgBar,
                BorderBrush = borderCol,
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(16, 0, 16, 0)
            };
            var topGrid = new Grid();
            topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var leftStack = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            var scopeBadge = new Border
            {
                Background = isRemote ? BrushHex("#26C5A059") : BrushHex(isDark ? "#1F2937" : "#E5E7EB"),
                BorderBrush = isRemote ? gold : borderCol,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(5),
                Padding = new Thickness(8, 3, 8, 3),
                Margin = new Thickness(0, 0, 10, 0)
            };
            scopeBadge.Child = new TextBlock
            {
                Text = isRemote ? "SERVER" : "LOKAL",
                Foreground = isRemote ? gold : fgPrimary,
                FontSize = 11,
                FontWeight = FontWeights.Bold
            };
            leftStack.Children.Add(scopeBadge);

            _titleFileTxt = new TextBlock
            {
                Text = fileName,
                ToolTip = displayPath,
                Foreground = fgPrimary,
                FontSize = 14,
                FontWeight = FontWeights.SemiBold,
                VerticalAlignment = VerticalAlignment.Center,
                TextTrimming = TextTrimming.CharacterEllipsis,
                MaxWidth = 380
            };
            leftStack.Children.Add(_titleFileTxt);
            Grid.SetColumn(leftStack, 0);
            topGrid.Children.Add(leftStack);

            var rightStack = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };

            _encodingBadgeTxt = new TextBlock
            {
                Text = doc.EncodingLabel,
                Foreground = fgMuted,
                FontSize = 11.5,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(0, 0, 8, 0)
            };
            rightStack.Children.Add(_encodingBadgeTxt);

            _eolBtn = CreateToolbarButton(_useCrLf ? "CRLF" : "LF", bgBtn, fgPrimary, borderCol);
            _eolBtn.ToolTip = "Zeilenende umschalten (LF für Linux-Server / CRLF für Windows)";
            _eolBtn.Click += (_, _) =>
            {
                _useCrLf = !_useCrLf;
                _eolBtn.Content = _useCrLf ? "CRLF" : "LF";
                MarkDirty(true);
            };
            rightStack.Children.Add(_eolBtn);

            _wrapBtn = CreateToolbarButton("⇄ Umbruch", bgBtn, fgPrimary, borderCol);
            _wrapBtn.Click += (_, _) =>
            {
                if (_editorBox == null) return;
                bool wrap = _editorBox.TextWrapping == TextWrapping.NoWrap;
                _editorBox.TextWrapping = wrap ? TextWrapping.Wrap : TextWrapping.NoWrap;
                _wrapBtn.Foreground = wrap ? gold : fgPrimary;
            };
            rightStack.Children.Add(_wrapBtn);

            var findBtn = CreateToolbarButton("🔍 Suchen", bgBtn, fgPrimary, borderCol);
            findBtn.ToolTip = "Suchen & Ersetzen (Strg+F)";
            findBtn.Click += (_, _) => ToggleFindBar(true);
            rightStack.Children.Add(findBtn);

            var zoomOutBtn = CreateToolbarButton("−", bgBtn, fgPrimary, borderCol, width: 30);
            zoomOutBtn.ToolTip = "Schrift kleiner";
            zoomOutBtn.Click += (_, _) => SetEditorFontSize(_fontSizePt - 1.0);
            rightStack.Children.Add(zoomOutBtn);

            var zoomInBtn = CreateToolbarButton("+", bgBtn, fgPrimary, borderCol, width: 30);
            zoomInBtn.ToolTip = "Schrift größer";
            zoomInBtn.Click += (_, _) => SetEditorFontSize(_fontSizePt + 1.0);
            rightStack.Children.Add(zoomInBtn);

            if (_saveAndUploadCallback != null)
            {
                var saveUploadBtn = CreateToolbarButton("⬆ Speichern & Hochladen", bgBtn, gold, gold);
                saveUploadBtn.FontWeight = FontWeights.SemiBold;
                saveUploadBtn.Click += async (_, _) => await SaveDocumentAsync(closeAfter: false, alsoUpload: true);
                rightStack.Children.Add(saveUploadBtn);
            }

            _saveBtn = new Button
            {
                Content = "💾 SPEICHERN",
                Height = 32,
                Padding = new Thickness(14, 0, 14, 0),
                Margin = new Thickness(4, 0, 6, 0),
                Background = gold,
                Foreground = BrushHex("#0F1117"),
                FontWeight = FontWeights.Bold,
                BorderThickness = new Thickness(0),
                Cursor = Cursors.Hand,
                ToolTip = "Speichern (Strg+S)"
            };
            _saveBtn.Click += async (_, _) => await SaveDocumentAsync(closeAfter: false, alsoUpload: false);
            rightStack.Children.Add(_saveBtn);

            var saveCloseBtn = CreateToolbarButton("✓ Speichern & Schließen", bgBtn, fgPrimary, borderCol);
            saveCloseBtn.Margin = new Thickness(0);
            saveCloseBtn.Click += async (_, _) => await SaveDocumentAsync(closeAfter: true, alsoUpload: false);
            rightStack.Children.Add(saveCloseBtn);

            Grid.SetColumn(rightStack, 1);
            topGrid.Children.Add(rightStack);
            topBar.Child = topGrid;
            Grid.SetRow(topBar, 0);
            root.Children.Add(topBar);

            // 2. COLLAPSIBLE FIND & REPLACE STRIP
            _findBarBorder = new Border
            {
                Background = bgBar,
                BorderBrush = borderCol,
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(16, 8, 16, 8),
                Visibility = Visibility.Collapsed
            };
            var findWrap = new WrapPanel { VerticalAlignment = VerticalAlignment.Center };
            _findBox = new TextBox
            {
                Width = 220,
                Height = 28,
                VerticalContentAlignment = VerticalAlignment.Center,
                Background = bgEditor,
                Foreground = fgPrimary,
                BorderBrush = borderCol,
                Padding = new Thickness(8, 0, 8, 0),
                Margin = new Thickness(0, 0, 6, 0),
                ToolTip = "Suchbegriff"
            };
            _findBox.KeyDown += (_, ke) =>
            {
                if (ke.Key == Key.Enter) { FindNext(); ke.Handled = true; }
                else if (ke.Key == Key.Escape) { ToggleFindBar(false); ke.Handled = true; }
            };
            findWrap.Children.Add(new TextBlock { Text = "Suchen:", Foreground = fgMuted, VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(0, 0, 6, 0) });
            findWrap.Children.Add(_findBox);

            var nextBtn = CreateToolbarButton("Weiter", bgBtn, fgPrimary, borderCol);
            nextBtn.Height = 28;
            nextBtn.Click += (_, _) => FindNext();
            findWrap.Children.Add(nextBtn);

            findWrap.Children.Add(new TextBlock { Text = "Ersetzen:", Foreground = fgMuted, VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(8, 0, 6, 0) });
            _replaceBox = new TextBox
            {
                Width = 200,
                Height = 28,
                VerticalContentAlignment = VerticalAlignment.Center,
                Background = bgEditor,
                Foreground = fgPrimary,
                BorderBrush = borderCol,
                Padding = new Thickness(8, 0, 8, 0),
                Margin = new Thickness(0, 0, 6, 0)
            };
            findWrap.Children.Add(_replaceBox);

            var replOneBtn = CreateToolbarButton("Ersetzen", bgBtn, fgPrimary, borderCol);
            replOneBtn.Height = 28;
            replOneBtn.Click += (_, _) => ReplaceCurrent();
            findWrap.Children.Add(replOneBtn);

            var replAllBtn = CreateToolbarButton("Alle", bgBtn, fgPrimary, borderCol);
            replAllBtn.Height = 28;
            replAllBtn.Click += (_, _) => ReplaceAll();
            findWrap.Children.Add(replAllBtn);

            var closeFindBtn = CreateToolbarButton("✕", bgBtn, fgMuted, borderCol, width: 28);
            closeFindBtn.Height = 28;
            closeFindBtn.Click += (_, _) => ToggleFindBar(false);
            findWrap.Children.Add(closeFindBtn);

            _findBarBorder.Child = findWrap;
            Grid.SetRow(_findBarBorder, 1);
            root.Children.Add(_findBarBorder);

            // 3. MAIN CODE EDITOR + LINE NUMBER GUTTER
            var editorGrid = new Grid { Background = bgEditor };
            editorGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            editorGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

            var gutterBorder = new Border
            {
                Background = bgGutter,
                BorderBrush = borderCol,
                BorderThickness = new Thickness(0, 0, 1, 0),
                MinWidth = 52
            };
            _gutterScroll = new ScrollViewer
            {
                VerticalScrollBarVisibility = ScrollBarVisibility.Hidden,
                HorizontalScrollBarVisibility = ScrollBarVisibility.Hidden,
                Focusable = false
            };
            _lineNumbersTxt = new TextBlock
            {
                FontFamily = new FontFamily("Cascadia Mono, Consolas, Courier New"),
                FontSize = _fontSizePt,
                Foreground = fgMuted,
                TextAlignment = TextAlignment.Right,
                Padding = new Thickness(10, 10, 10, 24)
            };
            _gutterScroll.Content = _lineNumbersTxt;
            gutterBorder.Child = _gutterScroll;
            Grid.SetColumn(gutterBorder, 0);
            editorGrid.Children.Add(gutterBorder);

            _editorBox = new TextBox
            {
                Text = doc.Text,
                FontFamily = new FontFamily("Cascadia Mono, Consolas, Courier New"),
                FontSize = _fontSizePt,
                Background = bgEditor,
                Foreground = fgPrimary,
                CaretBrush = gold,
                SelectionBrush = BrushHex("#55C5A059"),
                BorderThickness = new Thickness(0),
                Padding = new Thickness(12, 10, 12, 24),
                AcceptsReturn = true,
                AcceptsTab = true,
                TextWrapping = TextWrapping.NoWrap,
                HorizontalScrollBarVisibility = ScrollBarVisibility.Auto,
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto
            };
            _editorBox.AddHandler(ScrollViewer.ScrollChangedEvent, new ScrollChangedEventHandler((_, se) =>
            {
                _gutterScroll.ScrollToVerticalOffset(se.VerticalOffset);
            }));
            _editorBox.TextChanged += (_, _) =>
            {
                UpdateLineNumbersAndStats();
                MarkDirty(!string.Equals(_editorBox.Text, _lastSavedNormalizedText, StringComparison.Ordinal));
            };
            _editorBox.SelectionChanged += (_, _) => UpdateCursorStats();
            _editorBox.PreviewMouseWheel += (_, we) =>
            {
                if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
                {
                    SetEditorFontSize(_fontSizePt + (we.Delta > 0 ? 1.0 : -1.0));
                    we.Handled = true;
                }
            };
            Grid.SetColumn(_editorBox, 1);
            editorGrid.Children.Add(_editorBox);

            Grid.SetRow(editorGrid, 2);
            root.Children.Add(editorGrid);

            // 4. BOTTOM STATUS BAR
            var bottomBar = new Border
            {
                Background = bgBar,
                BorderBrush = borderCol,
                BorderThickness = new Thickness(0, 1, 0, 0),
                Padding = new Thickness(16, 0, 16, 0)
            };
            var botGrid = new Grid();
            botGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            botGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            _saveStatusTxt = new TextBlock
            {
                Text = _displayPath,
                Foreground = fgMuted,
                FontSize = 11.5,
                VerticalAlignment = VerticalAlignment.Center,
                TextTrimming = TextTrimming.CharacterEllipsis
            };
            Grid.SetColumn(_saveStatusTxt, 0);
            botGrid.Children.Add(_saveStatusTxt);

            _cursorInfoTxt = new TextBlock
            {
                Foreground = fgMuted,
                FontSize = 11.5,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(16, 0, 0, 0)
            };
            Grid.SetColumn(_cursorInfoTxt, 1);
            botGrid.Children.Add(_cursorInfoTxt);

            bottomBar.Child = botGrid;
            Grid.SetRow(bottomBar, 3);
            root.Children.Add(bottomBar);

            Content = root;

            PreviewKeyDown += async (_, ke) =>
            {
                if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
                {
                    if (ke.Key == Key.S)
                    {
                        ke.Handled = true;
                        await SaveDocumentAsync(closeAfter: false, alsoUpload: false);
                    }
                    else if (ke.Key == Key.F || ke.Key == Key.H)
                    {
                        ke.Handled = true;
                        ToggleFindBar(true);
                    }
                }
                else if (ke.Key == Key.F3)
                {
                    ke.Handled = true;
                    FindNext();
                }
            };

            Closing += async (_, ce) =>
            {
                if (!_isDirty || _isSaving) return;
                ce.Cancel = true;
                var choice = FtpModalDialogs.ShowUnsavedChangesDialog(this, _isDark, fileName);
                if (choice == MessageBoxResult.Yes)
                {
                    bool ok = await SaveDocumentAsync(closeAfter: false, alsoUpload: false);
                    if (ok)
                    {
                        _isDirty = false;
                        Close();
                    }
                }
                else if (choice == MessageBoxResult.No)
                {
                    _isDirty = false;
                    Close();
                }
            };

            Loaded += (_, _) =>
            {
                UpdateLineNumbersAndStats();
                UpdateCursorStats();
                _editorBox.Focus();
            };
        }

        private void MarkDirty(bool dirty)
        {
            _isDirty = dirty;
            string baseName = Path.GetFileName(_displayPath);
            _titleFileTxt.Text = dirty ? $"● {baseName}" : baseName;
        }

        private void SetEditorFontSize(double pt)
        {
            _fontSizePt = Math.Clamp(pt, 9.5, 28.0);
            _editorBox.FontSize = _fontSizePt;
            _lineNumbersTxt.FontSize = _fontSizePt;
        }

        private void ToggleFindBar(bool show)
        {
            _findBarBorder.Visibility = show ? Visibility.Visible : Visibility.Collapsed;
            if (show)
            {
                if (!string.IsNullOrEmpty(_editorBox.SelectedText) && !_editorBox.SelectedText.Contains('\n'))
                    _findBox.Text = _editorBox.SelectedText;
                _findBox.Focus();
                _findBox.SelectAll();
            }
            else
            {
                _editorBox.Focus();
            }
        }

        private void FindNext()
        {
            string needle = _findBox.Text;
            if (string.IsNullOrEmpty(needle)) return;
            string hay = _editorBox.Text;
            int start = Math.Min(hay.Length, _editorBox.SelectionStart + _editorBox.SelectionLength);
            int idx = hay.IndexOf(needle, start, StringComparison.OrdinalIgnoreCase);
            if (idx < 0 && start > 0)
                idx = hay.IndexOf(needle, 0, StringComparison.OrdinalIgnoreCase);

            if (idx >= 0)
            {
                _editorBox.Focus();
                _editorBox.Select(idx, needle.Length);
                int line = _editorBox.GetLineIndexFromCharacterIndex(idx);
                if (line >= 0) _editorBox.ScrollToLine(line);
                _saveStatusTxt.Text = $"Treffer bei Zeile {line + 1}";
            }
            else
            {
                _saveStatusTxt.Text = $"Kein Treffer für '{needle}'";
            }
        }

        private void ReplaceCurrent()
        {
            string needle = _findBox.Text;
            if (string.IsNullOrEmpty(needle)) return;
            if (string.Equals(_editorBox.SelectedText, needle, StringComparison.OrdinalIgnoreCase))
            {
                int pos = _editorBox.SelectionStart;
                string repl = _replaceBox.Text ?? "";
                _editorBox.SelectedText = repl;
                _editorBox.Select(pos + repl.Length, 0);
            }
            FindNext();
        }

        private void ReplaceAll()
        {
            string needle = _findBox.Text;
            if (string.IsNullOrEmpty(needle)) return;
            string repl = _replaceBox.Text ?? "";
            string updated = _editorBox.Text.Replace(needle, repl, StringComparison.OrdinalIgnoreCase);
            if (!string.Equals(updated, _editorBox.Text, StringComparison.Ordinal))
            {
                _editorBox.Text = updated;
                _saveStatusTxt.Text = $"Alle Vorkommen von '{needle}' ersetzt.";
            }
        }

        private int _lastLineCount = -1;
        private void UpdateLineNumbersAndStats()
        {
            string txt = _editorBox.Text ?? "";
            int lines = 1;
            for (int i = 0; i < txt.Length; i++)
            {
                if (txt[i] == '\n') lines++;
            }
            if (lines != _lastLineCount)
            {
                _lastLineCount = lines;
                var sb = new StringBuilder(lines * 5);
                for (int i = 1; i <= lines; i++)
                {
                    if (i > 1) sb.Append('\n');
                    sb.Append(i);
                }
                _lineNumbersTxt.Text = sb.ToString();
            }
            UpdateCursorStats();
        }

        private void UpdateCursorStats()
        {
            try
            {
                int caret = Math.Clamp(_editorBox.CaretIndex, 0, _editorBox.Text.Length);
                int line = 1;
                int col = 1;
                string t = _editorBox.Text;
                for (int i = 0; i < caret; i++)
                {
                    if (t[i] == '\n') { line++; col = 1; }
                    else if (t[i] != '\r') col++;
                }
                _cursorInfoTxt.Text = $"Zeile {line}, Spalte {col} · {Math.Max(1, _lastLineCount)} Zeilen";
            }
            catch { }
        }

        private async Task<bool> SaveDocumentAsync(bool closeAfter, bool alsoUpload)
        {
            if (_isSaving) return false;
            _isSaving = true;
            _saveBtn.IsEnabled = false;
            try
            {
                _saveStatusTxt.Foreground = BrushHex("#C5A059");
                _saveStatusTxt.Text = _isRemote ? "⏳ Speichere direkt auf Server ..." : "⏳ Speichere Datei ...";

                byte[] encoded = FtpTextBufferCodec.Encode(_editorBox.Text, _encoding, _useCrLf);
                if (alsoUpload && _saveAndUploadCallback != null)
                    await _saveAndUploadCallback(encoded);
                else
                    await _saveBytesCallback(encoded);

                _lastSavedNormalizedText = _editorBox.Text;
                MarkDirty(false);
                _saveStatusTxt.Foreground = BrushHex("#34D399");
                string targetLabel = (_isRemote || alsoUpload) ? "Auf Server gespeichert" : "Lokal gespeichert";
                _saveStatusTxt.Text = $"✓ {targetLabel} · {DateTime.Now:HH:mm:ss} ({encoded.Length:N0} Bytes)";

                if (closeAfter)
                {
                    _isSaving = false;
                    Close();
                }
                return true;
            }
            catch (Exception ex)
            {
                _saveStatusTxt.Foreground = BrushHex("#F87171");
                _saveStatusTxt.Text = $"⚠ Speichern fehlgeschlagen: {ex.Message}";
                return false;
            }
            finally
            {
                _isSaving = false;
                _saveBtn.IsEnabled = true;
            }
        }

        private static Button CreateToolbarButton(string text, Brush bg, Brush fg, Brush border, double width = double.NaN)
        {
            var btn = new Button
            {
                Content = text,
                Height = 30,
                Padding = new Thickness(10, 0, 10, 0),
                Margin = new Thickness(0, 0, 5, 0),
                Background = bg,
                Foreground = fg,
                BorderBrush = border,
                BorderThickness = new Thickness(1),
                Cursor = Cursors.Hand
            };
            if (!double.IsNaN(width))
            {
                btn.Width = width;
                btn.Padding = new Thickness(0);
            }
            return btn;
        }

        private static SolidColorBrush BrushHex(string hex) =>
            new((Color)ColorConverter.ConvertFromString(hex));
    }

    // =========================================================================
    // CLEAN DARK/LIGHT MODAL DIALOGS (RENAME / MKDIR / CHMOD / DELETE / LIGHTBOX)
    // =========================================================================
    public static class FtpModalDialogs
    {
        public static string? ShowInputPrompt(
            Window owner,
            bool isDark,
            string title,
            string initialValue,
            string confirmText = "Übernehmen",
            bool selectFileNameStemOnly = false)
        {
            var dlg = CreateBaseDialog(owner, isDark, title, 440, 178);
            var bgInput = BrushHex(isDark ? "#0F1117" : "#F9FAFB");
            var fgPrimary = BrushHex(isDark ? "#F9FAFB" : "#111827");
            var borderCol = BrushHex(isDark ? "#2D3343" : "#D1D5DB");

            var stack = new StackPanel { Margin = new Thickness(20, 18, 20, 16) };
            var tb = new TextBox
            {
                Text = initialValue ?? "",
                Height = 36,
                VerticalContentAlignment = VerticalAlignment.Center,
                Background = bgInput,
                Foreground = fgPrimary,
                CaretBrush = BrushHex("#C5A059"),
                BorderBrush = borderCol,
                Padding = new Thickness(10, 0, 10, 0),
                FontSize = 13.5
            };
            stack.Children.Add(tb);

            var btnRow = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Right,
                Margin = new Thickness(0, 18, 0, 0)
            };

            string? result = null;
            var cancelBtn = CreateDialogButton("Abbrechen", isDark, isPrimary: false, isDanger: false);
            cancelBtn.Click += (_, _) => { dlg.DialogResult = false; };

            var okBtn = CreateDialogButton(confirmText, isDark, isPrimary: true, isDanger: false);
            okBtn.Margin = new Thickness(8, 0, 0, 0);
            okBtn.Click += (_, _) =>
            {
                string val = tb.Text.Trim();
                if (!string.IsNullOrEmpty(val))
                {
                    result = val;
                    dlg.DialogResult = true;
                }
            };

            tb.KeyDown += (_, ke) =>
            {
                if (ke.Key == Key.Enter)
                {
                    string val = tb.Text.Trim();
                    if (!string.IsNullOrEmpty(val))
                    {
                        result = val;
                        dlg.DialogResult = true;
                    }
                    ke.Handled = true;
                }
                else if (ke.Key == Key.Escape)
                {
                    dlg.DialogResult = false;
                    ke.Handled = true;
                }
            };

            btnRow.Children.Add(cancelBtn);
            btnRow.Children.Add(okBtn);
            stack.Children.Add(btnRow);
            dlg.Content = stack;

            dlg.Loaded += (_, _) =>
            {
                tb.Focus();
                if (selectFileNameStemOnly && !string.IsNullOrEmpty(tb.Text))
                {
                    int dot = tb.Text.LastIndexOf('.');
                    if (dot > 0) tb.Select(0, dot);
                    else tb.SelectAll();
                }
                else
                {
                    tb.SelectAll();
                }
            };

            return dlg.ShowDialog() == true ? result : null;
        }

        public static bool ShowConfirmDialog(
            Window owner,
            bool isDark,
            string title,
            string targetSummary,
            string confirmText = "Löschen",
            bool isDanger = true)
        {
            var dlg = CreateBaseDialog(owner, isDark, title, 450, 185);
            var fgPrimary = BrushHex(isDark ? "#F9FAFB" : "#111827");

            var stack = new StackPanel { Margin = new Thickness(20, 18, 20, 16) };
            stack.Children.Add(new TextBlock
            {
                Text = targetSummary,
                Foreground = fgPrimary,
                FontSize = 13.5,
                TextWrapping = TextWrapping.Wrap
            });

            var btnRow = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Right,
                Margin = new Thickness(0, 22, 0, 0)
            };

            var cancelBtn = CreateDialogButton("Abbrechen", isDark, isPrimary: false, isDanger: false);
            cancelBtn.Click += (_, _) => dlg.DialogResult = false;

            var okBtn = CreateDialogButton(confirmText, isDark, isPrimary: !isDanger, isDanger: isDanger);
            okBtn.Margin = new Thickness(8, 0, 0, 0);
            okBtn.Click += (_, _) => dlg.DialogResult = true;

            btnRow.Children.Add(cancelBtn);
            btnRow.Children.Add(okBtn);
            stack.Children.Add(btnRow);
            dlg.Content = stack;

            dlg.PreviewKeyDown += (_, ke) =>
            {
                if (ke.Key == Key.Escape) { dlg.DialogResult = false; ke.Handled = true; }
                else if (ke.Key == Key.Enter) { dlg.DialogResult = true; ke.Handled = true; }
            };

            return dlg.ShowDialog() == true;
        }

        public static MessageBoxResult ShowUnsavedChangesDialog(Window owner, bool isDark, string fileName)
        {
            var dlg = CreateBaseDialog(owner, isDark, "Ungespeicherte Änderungen", 470, 185);
            var fgPrimary = BrushHex(isDark ? "#F9FAFB" : "#111827");
            MessageBoxResult res = MessageBoxResult.Cancel;

            var stack = new StackPanel { Margin = new Thickness(20, 18, 20, 16) };
            stack.Children.Add(new TextBlock
            {
                Text = $"Änderungen in '{fileName}' vor dem Schließen speichern?",
                Foreground = fgPrimary,
                FontSize = 13.5,
                TextWrapping = TextWrapping.Wrap
            });

            var btnRow = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Right,
                Margin = new Thickness(0, 22, 0, 0)
            };

            var cancelBtn = CreateDialogButton("Abbrechen", isDark, false, false);
            cancelBtn.Click += (_, _) => { res = MessageBoxResult.Cancel; dlg.Close(); };

            var discardBtn = CreateDialogButton("Verwerfen", isDark, false, true);
            discardBtn.Margin = new Thickness(8, 0, 0, 0);
            discardBtn.Click += (_, _) => { res = MessageBoxResult.No; dlg.Close(); };

            var saveBtn = CreateDialogButton("Speichern", isDark, true, false);
            saveBtn.Margin = new Thickness(8, 0, 0, 0);
            saveBtn.Click += (_, _) => { res = MessageBoxResult.Yes; dlg.Close(); };

            btnRow.Children.Add(cancelBtn);
            btnRow.Children.Add(discardBtn);
            btnRow.Children.Add(saveBtn);
            stack.Children.Add(btnRow);
            dlg.Content = stack;

            dlg.ShowDialog();
            return res;
        }

        public static (short OctalValue, string OctalString, bool Recursive)? ShowChmodDialog(
            Window owner,
            bool isDark,
            string targetName,
            string currentPermString,
            bool hasDirectories)
        {
            string startOctal = FtpTextBufferCodec.PermissionsStringToOctal(currentPermString, hasDirectories);
            var dlg = CreateBaseDialog(owner, isDark, $"Berechtigungen (CHMOD) · {targetName}", 470, hasDirectories ? 330 : 295);
            var fgPrimary = BrushHex(isDark ? "#F9FAFB" : "#111827");
            var fgMuted = BrushHex(isDark ? "#9CA3AF" : "#6B7280");
            var bgInput = BrushHex(isDark ? "#0F1117" : "#F9FAFB");
            var borderCol = BrushHex(isDark ? "#2D3343" : "#D1D5DB");

            var stack = new StackPanel { Margin = new Thickness(20, 16, 20, 16) };

            // 3x3 permission matrix
            var matrix = new Grid { Margin = new Thickness(0, 0, 0, 14) };
            matrix.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(120) });
            matrix.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            matrix.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            matrix.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            for (int r = 0; r < 4; r++)
                matrix.RowDefinitions.Add(new RowDefinition { Height = new GridLength(28) });

            string[] headers = { "", "Lesen (r)", "Schreiben (w)", "Ausführen (x)" };
            for (int c = 1; c < 4; c++)
            {
                var h = new TextBlock { Text = headers[c], Foreground = fgMuted, FontSize = 12, VerticalAlignment = VerticalAlignment.Center };
                Grid.SetRow(h, 0);
                Grid.SetColumn(h, c);
                matrix.Children.Add(h);
            }

            string[] scopes = { "Besitzer", "Gruppe", "Öffentlich" };
            var checks = new CheckBox[3, 3];
            bool suppressSync = false;

            var octalBox = new TextBox
            {
                Text = startOctal,
                Width = 82,
                Height = 32,
                HorizontalContentAlignment = HorizontalAlignment.Center,
                VerticalContentAlignment = VerticalAlignment.Center,
                Background = bgInput,
                Foreground = BrushHex("#C5A059"),
                FontWeight = FontWeights.Bold,
                FontSize = 14,
                BorderBrush = borderCol
            };

            void SyncOctalFromChecks()
            {
                if (suppressSync) return;
                suppressSync = true;
                int u = (checks[0, 0].IsChecked == true ? 4 : 0) + (checks[0, 1].IsChecked == true ? 2 : 0) + (checks[0, 2].IsChecked == true ? 1 : 0);
                int g = (checks[1, 0].IsChecked == true ? 4 : 0) + (checks[1, 1].IsChecked == true ? 2 : 0) + (checks[1, 2].IsChecked == true ? 1 : 0);
                int o = (checks[2, 0].IsChecked == true ? 4 : 0) + (checks[2, 1].IsChecked == true ? 2 : 0) + (checks[2, 2].IsChecked == true ? 1 : 0);
                octalBox.Text = $"{u}{g}{o}";
                suppressSync = false;
            }

            void SyncChecksFromOctal(string oct)
            {
                if (oct.Length != 3 || oct.Any(ch => ch < '0' || ch > '7')) return;
                suppressSync = true;
                for (int r = 0; r < 3; r++)
                {
                    int v = oct[r] - '0';
                    checks[r, 0].IsChecked = (v & 4) != 0;
                    checks[r, 1].IsChecked = (v & 2) != 0;
                    checks[r, 2].IsChecked = (v & 1) != 0;
                }
                suppressSync = false;
            }

            for (int r = 0; r < 3; r++)
            {
                var lbl = new TextBlock { Text = scopes[r], Foreground = fgPrimary, FontWeight = FontWeights.SemiBold, VerticalAlignment = VerticalAlignment.Center };
                Grid.SetRow(lbl, r + 1);
                Grid.SetColumn(lbl, 0);
                matrix.Children.Add(lbl);

                for (int c = 0; c < 3; c++)
                {
                    var cb = new CheckBox { VerticalAlignment = VerticalAlignment.Center, Cursor = Cursors.Hand };
                    cb.Checked += (_, _) => SyncOctalFromChecks();
                    cb.Unchecked += (_, _) => SyncOctalFromChecks();
                    checks[r, c] = cb;
                    Grid.SetRow(cb, r + 1);
                    Grid.SetColumn(cb, c + 1);
                    matrix.Children.Add(cb);
                }
            }
            stack.Children.Add(matrix);

            octalBox.TextChanged += (_, _) =>
            {
                if (!suppressSync)
                    SyncChecksFromOctal(octalBox.Text.Trim());
            };
            SyncChecksFromOctal(startOctal);

            // Octal + Quick Presets row
            var presetRow = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            presetRow.Children.Add(new TextBlock { Text = "Oktal:", Foreground = fgMuted, VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(0, 0, 8, 0) });
            presetRow.Children.Add(octalBox);

            foreach (string preset in new[] { "644", "755", "775", "600" })
            {
                string pCopy = preset;
                var pb = CreateDialogButton(pCopy, isDark, false, false);
                pb.Height = 30;
                pb.Padding = new Thickness(10, 0, 10, 0);
                pb.Margin = new Thickness(6, 0, 0, 0);
                pb.Click += (_, _) => octalBox.Text = pCopy;
                presetRow.Children.Add(pb);
            }
            stack.Children.Add(presetRow);

            CheckBox? recursiveCb = null;
            if (hasDirectories)
            {
                recursiveCb = new CheckBox
                {
                    Content = "Auf Unterordner & enthaltene Dateien anwenden",
                    Foreground = fgPrimary,
                    Margin = new Thickness(0, 12, 0, 0),
                    Cursor = Cursors.Hand
                };
                stack.Children.Add(recursiveCb);
            }

            var btnRow = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Right,
                Margin = new Thickness(0, 18, 0, 0)
            };

            (short, string, bool)? finalResult = null;
            var cancelBtn = CreateDialogButton("Abbrechen", isDark, false, false);
            cancelBtn.Click += (_, _) => dlg.DialogResult = false;

            var applyBtn = CreateDialogButton("Übernehmen", isDark, true, false);
            applyBtn.Margin = new Thickness(8, 0, 0, 0);
            applyBtn.Click += (_, _) =>
            {
                try
                {
                    short parsed = FtpTextBufferCodec.ParseOctalPermissions(octalBox.Text);
                    string cleanOct = octalBox.Text.Trim();
                    if (cleanOct.Length == 4) cleanOct = cleanOct.Substring(1);
                    finalResult = (parsed, cleanOct, recursiveCb?.IsChecked == true);
                    dlg.DialogResult = true;
                }
                catch { }
            };

            btnRow.Children.Add(cancelBtn);
            btnRow.Children.Add(applyBtn);
            stack.Children.Add(btnRow);
            dlg.Content = stack;

            return dlg.ShowDialog() == true ? finalResult : null;
        }

        public static void ShowImageLightbox(
            Window owner,
            bool isDark,
            IReadOnlyList<FileEntryItem> imageItems,
            int startIndex,
            Func<FileEntryItem, Task<byte[]>> loadBytesAsync,
            Func<FileEntryItem, Task>? onTransferRequested = null,
            string transferLabel = "← Herunterladen")
        {
            if (imageItems.Count == 0) return;
            int idx = Math.Clamp(startIndex, 0, imageItems.Count - 1);
            double zoom = 1.0;

            var win = new Window
            {
                Owner = owner,
                Title = imageItems[idx].Name,
                Icon = owner.Icon,
                Width = Math.Min(1160, owner.ActualWidth * 0.92),
                Height = Math.Min(780, owner.ActualHeight * 0.92),
                WindowStartupLocation = WindowStartupLocation.CenterOwner,
                WindowStyle = WindowStyle.SingleBorderWindow,
                Background = BrushHex(isDark ? "#0A0C10" : "#F3F4F6")
            };

            var root = new Grid();
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(48) });
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });

            var topBar = new Border
            {
                Background = BrushHex(isDark ? "#12151E" : "#FFFFFF"),
                BorderBrush = BrushHex(isDark ? "#232734" : "#E5E7EB"),
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(16, 0, 16, 0)
            };
            var topGrid = new Grid();
            topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var titleTxt = new TextBlock
            {
                Foreground = BrushHex(isDark ? "#F9FAFB" : "#111827"),
                FontSize = 13.5,
                FontWeight = FontWeights.SemiBold,
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(titleTxt, 0);
            topGrid.Children.Add(titleTxt);

            var actions = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            if (onTransferRequested != null)
            {
                var dlBtn = CreateDialogButton(transferLabel, isDark, true, false);
                dlBtn.Height = 30;
                dlBtn.Click += async (_, _) => await onTransferRequested(imageItems[idx]);
                actions.Children.Add(dlBtn);
            }
            Grid.SetColumn(actions, 1);
            topGrid.Children.Add(actions);
            topBar.Child = topGrid;
            Grid.SetRow(topBar, 0);
            root.Children.Add(topBar);

            var stage = new Grid();
            var scroll = new ScrollViewer
            {
                HorizontalScrollBarVisibility = ScrollBarVisibility.Auto,
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto
            };
            var scaleTransform = new ScaleTransform(1.0, 1.0);
            var img = new Image
            {
                Stretch = Stretch.Uniform,
                Margin = new Thickness(28),
                LayoutTransform = scaleTransform
            };
            RenderOptions.SetBitmapScalingMode(img, BitmapScalingMode.HighQuality);
            scroll.Content = img;
            stage.Children.Add(scroll);

            if (imageItems.Count > 1)
            {
                var prevBtn = CreateDialogButton("‹", isDark, false, false);
                prevBtn.Width = 42;
                prevBtn.Height = 42;
                prevBtn.FontSize = 22;
                prevBtn.HorizontalAlignment = HorizontalAlignment.Left;
                prevBtn.VerticalAlignment = VerticalAlignment.Center;
                prevBtn.Margin = new Thickness(14, 0, 0, 0);
                prevBtn.Click += async (_, _) => { idx = (idx - 1 + imageItems.Count) % imageItems.Count; await LoadCurrentAsync(); };
                stage.Children.Add(prevBtn);

                var nextBtn = CreateDialogButton("›", isDark, false, false);
                nextBtn.Width = 42;
                nextBtn.Height = 42;
                nextBtn.FontSize = 22;
                nextBtn.HorizontalAlignment = HorizontalAlignment.Right;
                nextBtn.VerticalAlignment = VerticalAlignment.Center;
                nextBtn.Margin = new Thickness(0, 0, 14, 0);
                nextBtn.Click += async (_, _) => { idx = (idx + 1) % imageItems.Count; await LoadCurrentAsync(); };
                stage.Children.Add(nextBtn);
            }

            Grid.SetRow(stage, 1);
            root.Children.Add(stage);
            win.Content = root;

            async Task LoadCurrentAsync()
            {
                var cur = imageItems[idx];
                zoom = 1.0;
                scaleTransform.ScaleX = 1.0;
                scaleTransform.ScaleY = 1.0;
                titleTxt.Text = $"{cur.Name} ({idx + 1}/{imageItems.Count})";
                win.Title = cur.Name;
                try
                {
                    byte[] bytes = await loadBytesAsync(cur);
                    using var ms = new MemoryStream(bytes);
                    var bmp = new BitmapImage();
                    bmp.BeginInit();
                    bmp.CacheOption = BitmapCacheOption.OnLoad;
                    bmp.StreamSource = ms;
                    bmp.EndInit();
                    bmp.Freeze();
                    img.Source = bmp;
                }
                catch (Exception ex)
                {
                    titleTxt.Text = $"⚠ {cur.Name}: {ex.Message}";
                }
            }

            win.PreviewMouseWheel += (_, we) =>
            {
                zoom = Math.Clamp(zoom + (we.Delta > 0 ? 0.15 : -0.15), 0.4, 4.0);
                scaleTransform.ScaleX = zoom;
                scaleTransform.ScaleY = zoom;
                we.Handled = true;
            };

            win.PreviewKeyDown += async (_, ke) =>
            {
                if (ke.Key == Key.Escape) { win.Close(); ke.Handled = true; }
                else if (ke.Key == Key.Left && imageItems.Count > 1) { idx = (idx - 1 + imageItems.Count) % imageItems.Count; await LoadCurrentAsync(); ke.Handled = true; }
                else if (ke.Key == Key.Right && imageItems.Count > 1) { idx = (idx + 1) % imageItems.Count; await LoadCurrentAsync(); ke.Handled = true; }
            };

            win.Loaded += async (_, _) => await LoadCurrentAsync();
            win.ShowDialog();
        }

        private static Window CreateBaseDialog(Window owner, bool isDark, string title, double width, double height)
        {
            var w = new Window
            {
                Title = title,
                Icon = owner.Icon,
                Width = width,
                Height = height,
                ResizeMode = ResizeMode.NoResize,
                WindowStartupLocation = owner.IsLoaded ? WindowStartupLocation.CenterOwner : WindowStartupLocation.CenterScreen,
                Background = BrushHex(isDark ? "#141821" : "#FFFFFF"),
                Foreground = BrushHex(isDark ? "#F9FAFB" : "#111827")
            };
            if (owner.IsLoaded) w.Owner = owner;
            return w;
        }

        private static Button CreateDialogButton(string text, bool isDark, bool isPrimary, bool isDanger)
        {
            Brush bg = isDanger
                ? BrushHex("#DC2626")
                : isPrimary
                    ? BrushHex("#C5A059")
                    : BrushHex(isDark ? "#1E2330" : "#F3F4F6");

            Brush fg = isDanger
                ? Brushes.White
                : isPrimary
                    ? BrushHex("#0F1117")
                    : BrushHex(isDark ? "#E5E7EB" : "#111827");

            return new Button
            {
                Content = text,
                Height = 34,
                Padding = new Thickness(16, 0, 16, 0),
                Background = bg,
                Foreground = fg,
                FontWeight = (isPrimary || isDanger) ? FontWeights.Bold : FontWeights.Normal,
                BorderBrush = BrushHex(isDark ? "#2D3343" : "#D1D5DB"),
                BorderThickness = new Thickness((isPrimary || isDanger) ? 0 : 1),
                Cursor = Cursors.Hand
            };
        }

        private static SolidColorBrush BrushHex(string hex) =>
            new((Color)ColorConverter.ConvertFromString(hex));
    }
}

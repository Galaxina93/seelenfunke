using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Threading;
using Microsoft.Win32;
using Microsoft.Web.WebView2.Core;

namespace Funkenwerk3D
{
    public sealed class LoadedModelStatsDto
    {
        public double WidthMm { get; init; }
        public double HeightMm { get; init; }
        public double DepthMm { get; init; }
        public int Triangles { get; init; }
        public int Vertices { get; init; }
        public int MeshCount { get; init; }
        public int MaterialCount { get; init; }
        public bool IsRigged { get; init; }
        public int BoneCount { get; init; }
        public int AnimationCount { get; init; }
        public string UnitLabel { get; init; } = "";
    }

    public partial class MainWindow : Window
    {
        public const string AppVirtualHost = "app.funkenwerk3d.internal";
        public const string ModelVirtualHost = "model.funkenwerk3d.internal";

        private readonly Funkenwerk3DSettings _settings;
        private readonly DispatcherTimer _toastTimer;
        private readonly ConcurrentDictionary<string, TaskCompletionSource<byte[]>> _pendingScreenshots = new();
        private readonly ConcurrentDictionary<string, TaskCompletionSource<(int BoneCount, int ClipCount, string ProfileResolved)>> _pendingExoskeletons = new();
        private readonly ConcurrentDictionary<string, TaskCompletionSource<byte[]>> _pendingGlbExports = new();

        private Task? _viewportInitTask;
        private bool _isWebViewReady;
        private bool _isViewerJsReady;
        private bool _suppressUiEvents;
        private bool _wireframeOn;
        private bool _turntableOn;
        private bool _animPlaying = true;
        private bool _currentIsRigged;
        private bool _showBonesOn;

        private string? _pendingInitialFile;
        private string? _activeModelPath;
        private string _activeModelSessionToken = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
        private List<string> _siblingModelFiles = new();
        private int _siblingIndex = -1;

        private TaskCompletionSource<LoadedModelStatsDto>? _selfTestLoadTcs;
        private readonly TaskCompletionSource<bool> _viewerReadyTcs = new(TaskCreationOptions.RunContinuationsAsynchronously);

        public bool IsDarkMode => _settings.DarkMode;

        public MainWindow(string? initialModelFile = null)
        {
            _suppressUiEvents = true;
            _settings = Funkenwerk3DSettings.Load();
            _pendingInitialFile = initialModelFile;

            InitializeComponent();
            LoadBrandIcon();
            ApplyTheme(_settings.DarkMode);

            GridFloorCheckBox.IsChecked = _settings.ShowGrid;
            ExposureSlider.Value = Math.Clamp(_settings.Exposure, 0.3, 2.5);
            ExposureValueText.Text = _settings.Exposure.ToString("0.00", new CultureInfo("de-DE"));
            SelectComboByTag(BackdropCombo, _settings.BackdropPreset);
            SelectComboByTag(UnitModeCombo, _settings.UnitMode);
            ApplySidebarVisibility(_settings.ShowSidebar);
            UpdateTogglePillVisual(DimToggleBtn, _settings.ShowDimensions);

            _toastTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(3000) };
            _toastTimer.Tick += (_, _) =>
            {
                _toastTimer.Stop();
                ViewerToastBorder.Visibility = Visibility.Collapsed;
            };

            _suppressUiEvents = false;
            Loaded += MainWindow_Loaded;
        }

        private void LoadBrandIcon()
        {
            try
            {
                string png = Path.Combine(AppContext.BaseDirectory, "funkenwerk_3d.png");
                if (File.Exists(png))
                {
                    var bmp = new BitmapImage();
                    bmp.BeginInit();
                    bmp.CacheOption = BitmapCacheOption.OnLoad;
                    bmp.UriSource = new Uri(png, UriKind.Absolute);
                    bmp.EndInit();
                    bmp.Freeze();
                    BrandLogoImg.Source = bmp;
                }
            }
            catch { }
        }

        private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            await EnsureViewportInitializedAsync();
        }

        internal Task EnsureViewportInitializedAsync()
        {
            return _viewportInitTask ??= InitializeViewportCoreAsync();
        }

        private async Task InitializeViewportCoreAsync()
        {
            string userDataDir = Funkenwerk3DSettings.GetWebViewUserDataDirectory();
            var env = await CoreWebView2Environment.CreateAsync(null, userDataDir);
            await ViewportWebView.EnsureCoreWebView2Async(env);

            var core = ViewportWebView.CoreWebView2;
            core.Settings.AreDevToolsEnabled = false;
            core.Settings.AreDefaultContextMenusEnabled = false;
            core.Settings.IsStatusBarEnabled = false;
            core.Settings.IsPasswordAutosaveEnabled = false;
            core.Settings.IsGeneralAutofillEnabled = false;
            core.Settings.IsZoomControlEnabled = false;
            core.Settings.AreBrowserAcceleratorKeysEnabled = false;

            string wwwrootDir = Path.Combine(AppContext.BaseDirectory, "wwwroot");
            if (!Directory.Exists(wwwrootDir))
                throw new DirectoryNotFoundException($"3D-Engine-Verzeichnis nicht gefunden: {wwwrootDir}");

            core.SetVirtualHostNameToFolderMapping(
                AppVirtualHost,
                wwwrootDir,
                CoreWebView2HostResourceAccessKind.DenyCors);

            // Air-gapped network & sandboxed model stream filter
            core.AddWebResourceRequestedFilter("*", CoreWebView2WebResourceContext.All);
            core.WebResourceRequested += Core_WebResourceRequested;
            core.NavigationStarting += Core_NavigationStarting;
            core.NewWindowRequested += (_, args) => args.Handled = true;
            core.PermissionRequested += (_, args) => args.State = CoreWebView2PermissionState.Deny;
            core.WebMessageReceived += Core_WebMessageReceived;

            _isWebViewReady = true;
            core.Navigate($"https://{AppVirtualHost}/viewer.html");

            await _viewerReadyTcs.Task;
        }

        private void Core_NavigationStarting(object? sender, CoreWebView2NavigationStartingEventArgs e)
        {
            if (!Uri.TryCreate(e.Uri, UriKind.Absolute, out var uri) ||
                !string.Equals(uri.Scheme, "https", StringComparison.OrdinalIgnoreCase) ||
                !string.Equals(uri.Host, AppVirtualHost, StringComparison.OrdinalIgnoreCase))
            {
                e.Cancel = true;
            }
        }

        private void Core_WebResourceRequested(object? sender, CoreWebView2WebResourceRequestedEventArgs e)
        {
            var env = ViewportWebView.CoreWebView2.Environment;
            if (!Uri.TryCreate(e.Request.Uri, UriKind.Absolute, out var uri))
            {
                e.Response = CreateBlockedResponse(env, 403, "Blocked");
                return;
            }

            // 1. Allow local bundled Three.js / Draco assets on app.funkenwerk3d.internal
            if (string.Equals(uri.Scheme, "https", StringComparison.OrdinalIgnoreCase) &&
                string.Equals(uri.Host, AppVirtualHost, StringComparison.OrdinalIgnoreCase))
            {
                return;
            }

            // 2. Serve active 3D model + sandboxed sibling textures/.bin on model.funkenwerk3d.internal/<sessionToken>/<subPath>
            if (string.Equals(uri.Scheme, "https", StringComparison.OrdinalIgnoreCase) &&
                string.Equals(uri.Host, ModelVirtualHost, StringComparison.OrdinalIgnoreCase))
            {
                e.Response = HandleModelVirtualRequest(env, uri);
                return;
            }

            // 3. Block 100% of external internet / file:// / ws:// requests
            e.Response = CreateBlockedResponse(env, 403, "Offline Air-Gap Active");
        }

        internal CoreWebView2WebResourceResponse HandleModelVirtualRequest(CoreWebView2Environment env, Uri uri)
        {
            if (string.IsNullOrWhiteSpace(_activeModelPath))
                return CreateBlockedResponse(env, 404, "No Active Model");

            string rawPath = uri.AbsolutePath.TrimStart('/');
            int slashIdx = rawPath.IndexOf('/');
            if (slashIdx <= 0 || slashIdx >= rawPath.Length - 1)
                return CreateBlockedResponse(env, 403, "Invalid Token Path");

            string reqToken = rawPath.Substring(0, slashIdx);
            string encodedSub = rawPath.Substring(slashIdx + 1);

            if (!ModelSecurityValidator.FixedTimeEqualsAscii(reqToken, _activeModelSessionToken))
                return CreateBlockedResponse(env, 403, "Invalid Session Token");

            string decodedSub = Uri.UnescapeDataString(encodedSub);
            if (!ModelSecurityValidator.TryResolveSandboxedSiblingAsset(
                    _activeModelPath,
                    decodedSub,
                    out string resolvedFile,
                    out string mimeType))
            {
                return CreateBlockedResponse(env, 403, "Asset Blocked By Sandbox");
            }

            try
            {
                byte[] fileBytes;
                using (var fs = new FileStream(resolvedFile, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                {
                    fileBytes = new byte[fs.Length];
                    int totalRead = 0;
                    while (totalRead < fileBytes.Length)
                    {
                        int n = fs.Read(fileBytes, totalRead, fileBytes.Length - totalRead);
                        if (n <= 0) break;
                        totalRead += n;
                    }
                }
                var memStream = new MemoryStream(fileBytes, writable: false);
                string headers = $"Content-Type: {mimeType}\r\nAccess-Control-Allow-Origin: https://{AppVirtualHost}\r\nCache-Control: no-store";
                return env.CreateWebResourceResponse(memStream, 200, "OK", headers);
            }
            catch
            {
                return CreateBlockedResponse(env, 500, "Read Error");
            }
        }

        private static CoreWebView2WebResourceResponse CreateBlockedResponse(CoreWebView2Environment env, int statusCode, string reason)
        {
            var ms = new MemoryStream(Array.Empty<byte>());
            return env.CreateWebResourceResponse(ms, statusCode, reason, "Content-Type: text/plain; charset=utf-8\r\nCache-Control: no-store");
        }

        private async void Core_WebMessageReceived(object? sender, CoreWebView2WebMessageReceivedEventArgs e)
        {
            try
            {
                using var doc = JsonDocument.Parse(e.WebMessageAsJson);
                var root = doc.RootElement;
                if (!root.TryGetProperty("type", out var typeProp))
                    return;

                string msgType = typeProp.GetString() ?? "";
                switch (msgType)
                {
                    case "viewerReady":
                        _isViewerJsReady = true;
                        SyncInitialStateToViewer();
                        _viewerReadyTcs.TrySetResult(true);
                        if (!string.IsNullOrWhiteSpace(_pendingInitialFile))
                        {
                            string toOpen = _pendingInitialFile;
                            _pendingInitialFile = null;
                            await LoadModelFileAsync(toOpen);
                        }
                        break;

                    case "filesDropped":
                        if (e.AdditionalObjects != null)
                        {
                            var droppedFile = e.AdditionalObjects.OfType<CoreWebView2File>().FirstOrDefault();
                            if (droppedFile != null && !string.IsNullOrWhiteSpace(droppedFile.Path))
                            {
                                await LoadModelFileAsync(droppedFile.Path);
                            }
                        }
                        break;

                    case "modelLoaded":
                    {
                        double wMm = root.TryGetProperty("widthMm", out var pW) ? pW.GetDouble() : 0;
                        double hMm = root.TryGetProperty("heightMm", out var pH) ? pH.GetDouble() : 0;
                        double dMm = root.TryGetProperty("depthMm", out var pD) ? pD.GetDouble() : 0;
                        int tris = root.TryGetProperty("triangles", out var pT) ? pT.GetInt32() : 0;
                        int verts = root.TryGetProperty("vertices", out var pV) ? pV.GetInt32() : 0;
                        int meshes = root.TryGetProperty("meshCount", out var pM) ? pM.GetInt32() : 0;
                        int mats = root.TryGetProperty("materialCount", out var pMat) ? pMat.GetInt32() : 0;
                        bool isRigged = root.TryGetProperty("isRigged", out var pRig) && pRig.GetBoolean();
                        int boneCount = root.TryGetProperty("boneCount", out var pBones) ? pBones.GetInt32() : 0;
                        bool showBones = root.TryGetProperty("showBones", out var pSb) && pSb.GetBoolean();
                        int animCount = root.TryGetProperty("animations", out var pAnims) && pAnims.ValueKind == JsonValueKind.Array
                            ? pAnims.GetArrayLength()
                            : 0;
                        string unitLabel = root.TryGetProperty("unitLabel", out var pU) ? (pU.GetString() ?? "") : "";

                        StatWidthText.Text = FormatMillimeters(wMm);
                        StatHeightText.Text = FormatMillimeters(hMm);
                        StatDepthText.Text = FormatMillimeters(dMm);
                        StatTrianglesText.Text = tris.ToString("N0", new CultureInfo("de-DE"));
                        StatVerticesText.Text = verts.ToString("N0", new CultureInfo("de-DE"));
                        StatMeshesText.Text = $"{meshes} / {mats}";
                        UnitHintText.Text = unitLabel;

                        PopulateMeshesInspector(root);
                        PopulateAnimationsInspector(root);
                        PopulateRigInspector(root, isRigged, boneCount, showBones);

                        if (!string.IsNullOrWhiteSpace(_activeModelPath))
                        {
                            StatusText.Text = isRigged
                                ? $"Geöffnet: {_activeModelPath} · Exoskelett ({boneCount} Knochen, {animCount} Animationen)"
                                : $"Geöffnet: {_activeModelPath}";
                        }

                        _selfTestLoadTcs?.TrySetResult(new LoadedModelStatsDto
                        {
                            WidthMm = wMm,
                            HeightMm = hMm,
                            DepthMm = dMm,
                            Triangles = tris,
                            Vertices = verts,
                            MeshCount = meshes,
                            MaterialCount = mats,
                            IsRigged = isRigged,
                            BoneCount = boneCount,
                            AnimationCount = animCount,
                            UnitLabel = unitLabel
                        });
                        break;
                    }

                    case "poseManualStarted":
                    {
                        _animPlaying = false;
                        AnimPlayPauseBtn.Content = "▶";
                        break;
                    }

                    case "exoskeletonReady":
                    {
                        string reqId = root.TryGetProperty("requestId", out var pId) ? (pId.GetString() ?? "") : "";
                        int bones = root.TryGetProperty("boneCount", out var pB) ? pB.GetInt32() : 0;
                        int clips = root.TryGetProperty("clipCount", out var pC) ? pC.GetInt32() : 0;
                        string prof = root.TryGetProperty("profileResolved", out var pProf) ? (pProf.GetString() ?? "auto") : "auto";
                        if (!string.IsNullOrEmpty(reqId) && _pendingExoskeletons.TryRemove(reqId, out var tcs))
                        {
                            tcs.TrySetResult((bones, clips, prof));
                        }
                        break;
                    }

                    case "exoskeletonError":
                    {
                        string reqId = root.TryGetProperty("requestId", out var pId) ? (pId.GetString() ?? "") : "";
                        string err = root.TryGetProperty("message", out var pErr) ? (pErr.GetString() ?? "Rigging-Fehler") : "Rigging-Fehler";
                        if (!string.IsNullOrEmpty(reqId) && _pendingExoskeletons.TryRemove(reqId, out var tcs))
                        {
                            tcs.TrySetException(new InvalidOperationException(err));
                        }
                        else
                        {
                            ShowToast($"Exoskelett-Fehler: {err}", isError: true);
                        }
                        break;
                    }

                    case "glbExportReady":
                    {
                        string reqId = root.TryGetProperty("requestId", out var pId) ? (pId.GetString() ?? "") : "";
                        string b64 = root.TryGetProperty("base64Glb", out var pData) ? (pData.GetString() ?? "") : "";
                        if (!string.IsNullOrEmpty(reqId) && _pendingGlbExports.TryRemove(reqId, out var tcs))
                        {
                            byte[] rawBytes = Convert.FromBase64String(b64);
                            byte[] stampedBytes = StampSeelenfunkeGlbMetadata(rawBytes);
                            tcs.TrySetResult(stampedBytes);
                        }
                        break;
                    }

                    case "glbExportError":
                    {
                        string reqId = root.TryGetProperty("requestId", out var pId) ? (pId.GetString() ?? "") : "";
                        string err = root.TryGetProperty("message", out var pErr) ? (pErr.GetString() ?? "GLB-Export fehlgeschlagen") : "GLB-Export fehlgeschlagen";
                        if (!string.IsNullOrEmpty(reqId) && _pendingGlbExports.TryRemove(reqId, out var tcs))
                        {
                            tcs.TrySetException(new InvalidOperationException(err));
                        }
                        else
                        {
                            ShowToast(err, isError: true);
                        }
                        break;
                    }

                    case "dimensionsUpdated":
                    {
                        double wMm = root.TryGetProperty("widthMm", out var pW) ? pW.GetDouble() : 0;
                        double hMm = root.TryGetProperty("heightMm", out var pH) ? pH.GetDouble() : 0;
                        double dMm = root.TryGetProperty("depthMm", out var pD) ? pD.GetDouble() : 0;
                        string unitLabel = root.TryGetProperty("unitLabel", out var pU) ? (pU.GetString() ?? "") : "";
                        StatWidthText.Text = FormatMillimeters(wMm);
                        StatHeightText.Text = FormatMillimeters(hMm);
                        StatDepthText.Text = FormatMillimeters(dMm);
                        UnitHintText.Text = unitLabel;
                        break;
                    }

                    case "modelError":
                    {
                        string err = root.TryGetProperty("message", out var pErr) ? (pErr.GetString() ?? "Unbekannter Fehler") : "Unbekannter Fehler";
                        StatusText.Text = $"Fehler beim Laden: {err}";
                        ShowToast($"Modell konnte nicht geladen werden: {err}", isError: true);
                        _selfTestLoadTcs?.TrySetException(new InvalidDataException(err));
                        break;
                    }

                    case "screenshotReady":
                    {
                        string reqId = root.TryGetProperty("requestId", out var pId) ? (pId.GetString() ?? "") : "";
                        string dataUrl = root.TryGetProperty("dataUrl", out var pData) ? (pData.GetString() ?? "") : "";
                        if (!string.IsNullOrEmpty(reqId) && _pendingScreenshots.TryRemove(reqId, out var tcs))
                        {
                            const string prefix = "data:image/png;base64,";
                            if (dataUrl.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
                            {
                                byte[] pngBytes = Convert.FromBase64String(dataUrl.Substring(prefix.Length));
                                tcs.TrySetResult(pngBytes);
                            }
                            else
                            {
                                tcs.TrySetException(new InvalidOperationException("Ungültige PNG-Daten vom 3D-Renderer."));
                            }
                        }
                        break;
                    }
                }
            }
            catch (Exception ex)
            {
                _selfTestLoadTcs?.TrySetException(ex);
            }
        }

        private void SyncInitialStateToViewer()
        {
            PostToViewer(new { cmd = "setTheme", dark = _settings.DarkMode });
            PostToViewer(new { cmd = "setBackdrop", backdrop = _settings.BackdropPreset });
            PostToViewer(new { cmd = "setExposure", exposure = _settings.Exposure });
            PostToViewer(new { cmd = "setGrid", enabled = _settings.ShowGrid });
            PostToViewer(new { cmd = "setDimensions", enabled = _settings.ShowDimensions });
            PostToViewer(new { cmd = "setUnitMode", unitMode = _settings.UnitMode });
        }

        private void PostToViewer(object message)
        {
            if (!_isWebViewReady || !_isViewerJsReady || ViewportWebView.CoreWebView2 == null)
                return;

            string json = JsonSerializer.Serialize(message);
            ViewportWebView.CoreWebView2.PostWebMessageAsJson(json);
        }

        public async Task<LoadedModelStatsDto> LoadModelFileAsync(string rawFilePath)
        {
            if (!ModelSecurityValidator.TryValidateModelFile(rawFilePath, out string canonicalPath, out string errorMsg))
            {
                StatusText.Text = errorMsg;
                ShowToast(errorMsg, isError: true);
                throw new InvalidDataException(errorMsg);
            }

            await EnsureViewportInitializedAsync();

            _activeModelPath = canonicalPath;
            _activeModelSessionToken = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
            RefreshSiblingModelsList(canonicalPath);

            var fi = new FileInfo(canonicalPath);
            string ext = fi.Extension.ToLowerInvariant().TrimStart('.');
            CurrentFileTitleText.Text = fi.Name;
            Title = $"{fi.Name} · Funkenwerk 3D";
            StatFormatText.Text = "." + ext.ToUpperInvariant();
            StatFileSizeText.Text = FormatBytes(fi.Length);
            StatusText.Text = $"Lade {fi.Name}...";

            _settings.AddRecentFile(canonicalPath);
            _settings.Save();

            string mtlSibling = "";
            if (ext == "obj")
            {
                string candidateMtl = Path.ChangeExtension(canonicalPath, ".mtl");
                if (File.Exists(candidateMtl) && !ModelSecurityValidator.HasReparsePointInPath(candidateMtl))
                {
                    mtlSibling = Path.GetFileName(candidateMtl);
                }
            }

            var tcs = new TaskCompletionSource<LoadedModelStatsDto>(TaskCreationOptions.RunContinuationsAsynchronously);
            _selfTestLoadTcs = tcs;

            string encodedName = Uri.EscapeDataString(fi.Name);
            string modelUrl = $"https://{ModelVirtualHost}/{_activeModelSessionToken}/{encodedName}";
            PostToViewer(new
            {
                cmd = "loadModel",
                url = modelUrl,
                ext,
                mtlSibling
            });

            using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(25));
            using var reg = cts.Token.Register(() => tcs.TrySetCanceled());
            return await tcs.Task;
        }

        private void RefreshSiblingModelsList(string currentPath)
        {
            _siblingModelFiles.Clear();
            _siblingIndex = -1;

            try
            {
                string? dir = Path.GetDirectoryName(currentPath);
                if (!string.IsNullOrWhiteSpace(dir) && Directory.Exists(dir))
                {
                    _siblingModelFiles = Directory.EnumerateFiles(dir)
                        .Where(f => ModelSecurityValidator.SupportedModelExtensions.Contains(Path.GetExtension(f)))
                        .OrderBy(f => Path.GetFileName(f), StringComparer.OrdinalIgnoreCase)
                        .ToList();

                    _siblingIndex = _siblingModelFiles.FindIndex(f => string.Equals(f, currentPath, StringComparison.OrdinalIgnoreCase));
                }
            }
            catch { }

            if (_siblingModelFiles.Count > 0 && _siblingIndex >= 0)
            {
                FolderIndexText.Text = $"{_siblingIndex + 1} / {_siblingModelFiles.Count}";
                PrevModelBtn.IsEnabled = _siblingModelFiles.Count > 1;
                NextModelBtn.IsEnabled = _siblingModelFiles.Count > 1;
            }
            else
            {
                FolderIndexText.Text = "1 / 1";
                PrevModelBtn.IsEnabled = false;
                NextModelBtn.IsEnabled = false;
            }
        }

        private void PopulateMeshesInspector(JsonElement root)
        {
            MeshesListPanel.Children.Clear();
            if (!root.TryGetProperty("meshes", out var meshesArr) || meshesArr.ValueKind != JsonValueKind.Array || meshesArr.GetArrayLength() == 0)
            {
                MeshesListPanel.Children.Add(new TextBlock
                {
                    Text = "Keine einzelnen Bauteile",
                    FontSize = 11.5,
                    Foreground = (Brush)Resources["MutedTextBrush"],
                    Margin = new Thickness(4, 2, 4, 2)
                });
                return;
            }

            foreach (var item in meshesArr.EnumerateArray())
            {
                int idx = item.TryGetProperty("index", out var pIdx) ? pIdx.GetInt32() : 0;
                string name = item.TryGetProperty("name", out var pName) ? (pName.GetString() ?? $"Bauteil {idx + 1}") : $"Bauteil {idx + 1}";
                bool vis = !item.TryGetProperty("visible", out var pVis) || pVis.GetBoolean();

                var cb = new CheckBox
                {
                    Content = name,
                    IsChecked = vis,
                    Foreground = (Brush)Resources["PrimaryTextBrush"],
                    FontSize = 11.5,
                    Margin = new Thickness(4, 3, 4, 3),
                    Cursor = Cursors.Hand,
                    ToolTip = name
                };
                int capturedIdx = idx;
                cb.Checked += (_, _) => PostToViewer(new { cmd = "setMeshVisible", index = capturedIdx, visible = true });
                cb.Unchecked += (_, _) => PostToViewer(new { cmd = "setMeshVisible", index = capturedIdx, visible = false });
                MeshesListPanel.Children.Add(cb);
            }
        }

        private void PopulateAnimationsInspector(JsonElement root)
        {
            _suppressUiEvents = true;
            try
            {
                AnimationCombo.Items.Clear();
                if (!root.TryGetProperty("animations", out var animsArr) || animsArr.ValueKind != JsonValueKind.Array || animsArr.GetArrayLength() == 0)
                {
                    AnimationsSection.Visibility = Visibility.Collapsed;
                    return;
                }

                foreach (var a in animsArr.EnumerateArray())
                {
                    int idx = a.TryGetProperty("index", out var pIdx) ? pIdx.GetInt32() : 0;
                    string name = a.TryGetProperty("name", out var pName) ? (pName.GetString() ?? $"Clip {idx + 1}") : $"Clip {idx + 1}";
                    double dur = a.TryGetProperty("durationSec", out var pDur) ? pDur.GetDouble() : 0;
                    AnimationCombo.Items.Add(new ComboBoxItem
                    {
                        Content = $"{name} ({dur:0.0}s)",
                        Tag = idx
                    });
                }

                AnimationCombo.SelectedIndex = 0;
                _animPlaying = true;
                AnimPlayPauseBtn.Content = "⏸";
                AnimationsSection.Visibility = Visibility.Visible;
            }
            finally
            {
                _suppressUiEvents = false;
            }
        }

        private void PopulateRigInspector(JsonElement root, bool isRigged, int boneCount, bool showBones)
        {
            _suppressUiEvents = true;
            try
            {
                _currentIsRigged = isRigged;
                _showBonesOn = showBones;

                RigStatusBadgeText.Text = isRigged ? $"{boneCount} Knochen" : "Statisch";
                RigStatusBadgeText.Foreground = isRigged
                    ? (Brush)Resources["GoldBrush"]
                    : (Brush)Resources["SecondaryTextBrush"];

                RigActiveControlsPanel.Visibility = isRigged ? Visibility.Visible : Visibility.Collapsed;
                ExportGlbTopBtn.IsEnabled = isRigged;
                ShowBonesCheckBox.IsChecked = showBones;
                UpdateTogglePillVisual(ExoskeletonTopBtn, isRigged && showBones);

                PoseBoneCombo.Items.Clear();
                PoseBoneCombo.Items.Add(new ComboBoxItem
                {
                    Content = "Gelenk posieren...",
                    Tag = -1
                });

                if (isRigged && root.TryGetProperty("bones", out var bonesArr) && bonesArr.ValueKind == JsonValueKind.Array)
                {
                    foreach (var b in bonesArr.EnumerateArray())
                    {
                        int idx = b.TryGetProperty("index", out var pIdx) ? pIdx.GetInt32() : 0;
                        string bName = b.TryGetProperty("name", out var pName) ? (pName.GetString() ?? $"Bone_{idx}") : $"Bone_{idx}";
                        PoseBoneCombo.Items.Add(new ComboBoxItem
                        {
                            Content = $"{bName}",
                            Tag = idx
                        });
                    }
                }
                PoseBoneCombo.SelectedIndex = 0;
            }
            finally
            {
                _suppressUiEvents = false;
            }
        }

        // =====================================================================
        // THEME ENGINE (100 % LIGHT / DARK MODE CONSISTENCY)
        // =====================================================================
        public void ApplyTheme(bool dark)
        {
            _settings.DarkMode = dark;
            void SetBrush(string key, string hex)
            {
                var brush = (SolidColorBrush)new BrushConverter().ConvertFromString(hex)!;
                brush.Freeze();
                Resources[key] = brush;
            }

            if (dark)
            {
                SetBrush("AppBgBrush", "#0E121B");
                SetBrush("TopBarBgBrush", "#131824");
                SetBrush("SidebarBgBrush", "#131824");
                SetBrush("CardBgBrush", "#181F2E");
                SetBrush("BorderBrushColor", "#263044");
                SetBrush("PrimaryTextBrush", "#F3F4F6");
                SetBrush("SecondaryTextBrush", "#9CA3AF");
                SetBrush("MutedTextBrush", "#6B7280");
                SetBrush("HoverBgBrush", "#1F293D");
                SetBrush("ActivePillBgBrush", "#2A2315");
                ThemeToggleBtn.Content = "☀";
                ViewerToastBorder.Background = (SolidColorBrush)new BrushConverter().ConvertFromString("#E6131824")!;
                ViewerToastText.Foreground = Brushes.White;
            }
            else
            {
                SetBrush("AppBgBrush", "#F4F6F9");
                SetBrush("TopBarBgBrush", "#FFFFFF");
                SetBrush("SidebarBgBrush", "#FFFFFF");
                SetBrush("CardBgBrush", "#F8FAFC");
                SetBrush("BorderBrushColor", "#CBD5E1");
                SetBrush("PrimaryTextBrush", "#111827");
                SetBrush("SecondaryTextBrush", "#4B5563");
                SetBrush("MutedTextBrush", "#64748B");
                SetBrush("HoverBgBrush", "#EEF2F6");
                SetBrush("ActivePillBgBrush", "#FEF9EC");
                ThemeToggleBtn.Content = "🌙";
                ViewerToastBorder.Background = (SolidColorBrush)new BrushConverter().ConvertFromString("#F2FFFFFF")!;
                ViewerToastText.Foreground = (SolidColorBrush)new BrushConverter().ConvertFromString("#111827")!;
            }

            foreach (CheckBox cb in MeshesListPanel.Children.OfType<CheckBox>())
            {
                cb.Foreground = (Brush)Resources["PrimaryTextBrush"];
            }

            UpdateTogglePillVisual(DimToggleBtn, _settings.ShowDimensions);
            UpdateTogglePillVisual(WireframeToggleBtn, _wireframeOn);
            UpdateTogglePillVisual(TurntableToggleBtn, _turntableOn);
            UpdateTogglePillVisual(ExoskeletonTopBtn, _currentIsRigged && _showBonesOn);
            PostToViewer(new { cmd = "setTheme", dark });
        }

        private void UpdateTogglePillVisual(Button btn, bool active)
        {
            if (btn == null) return;
            if (active)
            {
                btn.Background = (Brush)Resources["ActivePillBgBrush"];
                btn.BorderBrush = (Brush)Resources["GoldBrush"];
                btn.Foreground = _settings.DarkMode
                    ? (Brush)Resources["GoldBrush"]
                    : (SolidColorBrush)new BrushConverter().ConvertFromString("#8A6414")!;
            }
            else
            {
                btn.ClearValue(Control.BackgroundProperty);
                btn.ClearValue(Control.BorderBrushProperty);
                btn.ClearValue(Control.ForegroundProperty);
            }
        }

        // =====================================================================
        // UI ACTIONS & SHORTCUTS
        // =====================================================================
        private async void OpenFile_Click(object sender, RoutedEventArgs e)
        {
            string defaultPrintDir = Path.Combine(AppContext.BaseDirectory, "Seelenfunke_3D_Druck");
            string? activeDir = !string.IsNullOrWhiteSpace(_activeModelPath) ? Path.GetDirectoryName(_activeModelPath) : null;
            var dlg = new OpenFileDialog
            {
                Title = "3D-Modell öffnen",
                InitialDirectory = (!string.IsNullOrWhiteSpace(activeDir) && Directory.Exists(activeDir))
                    ? activeDir
                    : (Directory.Exists(defaultPrintDir) ? defaultPrintDir : ""),
                Filter = "Alle 3D-Modelle (*.glb;*.gltf;*.stl;*.obj;*.3mf;*.ply;*.fbx)|*.glb;*.gltf;*.stl;*.obj;*.3mf;*.ply;*.fbx|glTF / GLB (*.glb;*.gltf)|*.glb;*.gltf|3D-Druck STL / 3MF (*.stl;*.3mf)|*.stl;*.3mf|OBJ / PLY / FBX (*.obj;*.ply;*.fbx)|*.obj;*.ply;*.fbx"
            };
            if (dlg.ShowDialog(this) == true)
            {
                try { await LoadModelFileAsync(dlg.FileName); } catch { }
            }
        }

        private async void PrevModel_Click(object sender, RoutedEventArgs e)
        {
            if (_siblingModelFiles.Count <= 1 || _siblingIndex < 0) return;
            int prev = (_siblingIndex - 1 + _siblingModelFiles.Count) % _siblingModelFiles.Count;
            try { await LoadModelFileAsync(_siblingModelFiles[prev]); } catch { }
        }

        private async void NextModel_Click(object sender, RoutedEventArgs e)
        {
            if (_siblingModelFiles.Count <= 1 || _siblingIndex < 0) return;
            int next = (_siblingIndex + 1) % _siblingModelFiles.Count;
            try { await LoadModelFileAsync(_siblingModelFiles[next]); } catch { }
        }

        private void ToggleDimensions_Click(object sender, RoutedEventArgs e)
        {
            _settings.ShowDimensions = !_settings.ShowDimensions;
            _settings.Save();
            UpdateTogglePillVisual(DimToggleBtn, _settings.ShowDimensions);
            PostToViewer(new { cmd = "setDimensions", enabled = _settings.ShowDimensions });
        }

        private void ToggleWireframe_Click(object sender, RoutedEventArgs e)
        {
            _wireframeOn = !_wireframeOn;
            UpdateTogglePillVisual(WireframeToggleBtn, _wireframeOn);
            PostToViewer(new { cmd = "setWireframe", enabled = _wireframeOn });
        }

        private void ToggleTurntable_Click(object sender, RoutedEventArgs e)
        {
            _turntableOn = !_turntableOn;
            UpdateTogglePillVisual(TurntableToggleBtn, _turntableOn);
            PostToViewer(new { cmd = "setAutoRotate", enabled = _turntableOn });
        }

        private void ResetCamera_Click(object sender, RoutedEventArgs e) =>
            PostToViewer(new { cmd = "setCameraView", view = "reset" });

        private void CamIso_Click(object sender, RoutedEventArgs e) =>
            PostToViewer(new { cmd = "setCameraView", view = "iso" });

        private void CamFront_Click(object sender, RoutedEventArgs e) =>
            PostToViewer(new { cmd = "setCameraView", view = "front" });

        private void CamBack_Click(object sender, RoutedEventArgs e) =>
            PostToViewer(new { cmd = "setCameraView", view = "back" });

        private void CamLeft_Click(object sender, RoutedEventArgs e) =>
            PostToViewer(new { cmd = "setCameraView", view = "left" });

        private void CamRight_Click(object sender, RoutedEventArgs e) =>
            PostToViewer(new { cmd = "setCameraView", view = "right" });

        private void CamTop_Click(object sender, RoutedEventArgs e) =>
            PostToViewer(new { cmd = "setCameraView", view = "top" });

        public async Task<byte[]> CaptureViewportPngBytesAsync(bool transparentBackground)
        {
            await EnsureViewportInitializedAsync();
            string reqId = Guid.NewGuid().ToString("N");
            var tcs = new TaskCompletionSource<byte[]>(TaskCreationOptions.RunContinuationsAsynchronously);
            _pendingScreenshots[reqId] = tcs;

            PostToViewer(new
            {
                cmd = "captureScreenshot",
                requestId = reqId,
                transparent = transparentBackground
            });

            using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(10));
            using var reg = cts.Token.Register(() =>
            {
                _pendingScreenshots.TryRemove(reqId, out _);
                tcs.TrySetCanceled();
            });
            return await tcs.Task;
        }

        private async void ExportPng_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_activeModelPath))
            {
                ShowToast("Bitte zuerst ein 3D-Modell öffnen.", isError: false);
                return;
            }

            string baseStem = Path.GetFileNameWithoutExtension(_activeModelPath);
            var dlg = new SaveFileDialog
            {
                Title = "3D-Ansicht als PNG speichern",
                Filter = "PNG-Bild (*.png)|*.png",
                FileName = $"{baseStem}_3D.png"
            };

            if (dlg.ShowDialog(this) == true)
            {
                try
                {
                    bool transparent = TransparentPngCheckBox.IsChecked == true;
                    byte[] pngBytes = await CaptureViewportPngBytesAsync(transparent);
                    await File.WriteAllBytesAsync(dlg.FileName, pngBytes);
                    ShowToast($"PNG gespeichert: {Path.GetFileName(dlg.FileName)}", isError: false);
                }
                catch (Exception ex)
                {
                    ShowToast($"PNG-Export fehlgeschlagen: {ex.Message}", isError: true);
                }
            }
        }

        private void SetDefaultApp_Click(object sender, RoutedEventArgs e)
        {
            WindowsDefault3DRegistrar.RegisterAsWindows3DViewer(out string msg, setDefaultForPrimary: true, setDefaultForSlicerFormats: false);
            ShowToast(msg, isError: false);
            WindowsDefault3DRegistrar.OpenWindowsDefaultAppsSettings();
        }

        public void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            ApplyTheme(!_settings.DarkMode);
            _settings.Save();
        }

        private void ToggleSidebar_Click(object sender, RoutedEventArgs e)
        {
            _settings.ShowSidebar = !_settings.ShowSidebar;
            _settings.Save();
            ApplySidebarVisibility(_settings.ShowSidebar);
        }

        private void ApplySidebarVisibility(bool visible)
        {
            InspectorSidebar.Visibility = visible ? Visibility.Visible : Visibility.Collapsed;
            SidebarColumn.Width = visible ? new GridLength(290) : new GridLength(0);
            UpdateTogglePillVisual(SidebarToggleBtn, visible);
        }

        private void BackdropCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressUiEvents) return;
            if (BackdropCombo.SelectedItem is ComboBoxItem item && item.Tag is string preset)
            {
                _settings.BackdropPreset = preset;
                _settings.Save();
                PostToViewer(new { cmd = "setBackdrop", backdrop = preset });
            }
        }

        private void UnitModeCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressUiEvents) return;
            if (UnitModeCombo.SelectedItem is ComboBoxItem item && item.Tag is string uMode)
            {
                _settings.UnitMode = uMode;
                _settings.Save();
                PostToViewer(new { cmd = "setUnitMode", unitMode = uMode });
            }
        }

        private void ExposureSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressUiEvents || ExposureValueText == null) return;
            _settings.Exposure = Math.Round(e.NewValue, 2);
            ExposureValueText.Text = _settings.Exposure.ToString("0.00", new CultureInfo("de-DE"));
            _settings.Save();
            PostToViewer(new { cmd = "setExposure", exposure = _settings.Exposure });
        }

        private void GridFloorCheckBox_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressUiEvents) return;
            _settings.ShowGrid = GridFloorCheckBox.IsChecked == true;
            _settings.Save();
            PostToViewer(new { cmd = "setGrid", enabled = _settings.ShowGrid });
        }

        private void AnimationCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressUiEvents) return;
            if (AnimationCombo.SelectedItem is ComboBoxItem item && item.Tag is int idx)
            {
                PostToViewer(new { cmd = "setAnimation", index = idx, playing = _animPlaying });
            }
        }

        private void AnimPlayPause_Click(object sender, RoutedEventArgs e)
        {
            _animPlaying = !_animPlaying;
            AnimPlayPauseBtn.Content = _animPlaying ? "⏸" : "▶";
            PostToViewer(new { cmd = "toggleAnimationPlay", playing = _animPlaying });
        }

        // =====================================================================
        // EXOSKELETON / AUTO-RIGGING, BONE POSING & .GLB EXPORT
        // =====================================================================
        public async Task<(int BoneCount, int ClipCount, string ProfileResolved)> GenerateExoskeletonAsync(
            string profile = "auto",
            string variant = "original",
            bool stripPedestal = true,
            bool showBones = true,
            bool autoPlay = true)
        {
            await EnsureViewportInitializedAsync();
            string reqId = Guid.NewGuid().ToString("N");
            var tcs = new TaskCompletionSource<(int, int, string)>(TaskCreationOptions.RunContinuationsAsynchronously);
            _pendingExoskeletons[reqId] = tcs;

            PostToViewer(new
            {
                cmd = "generateExoskeleton",
                requestId = reqId,
                profile,
                variant,
                stripPedestal,
                showBones,
                autoPlay
            });

            using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(45));
            using var reg = cts.Token.Register(() =>
            {
                _pendingExoskeletons.TryRemove(reqId, out _);
                tcs.TrySetCanceled();
            });
            return await tcs.Task;
        }

        public async Task<byte[]> ExportRiggedGlbBytesAsync(bool keepCurrentPose = false)
        {
            await EnsureViewportInitializedAsync();
            string reqId = Guid.NewGuid().ToString("N");
            var tcs = new TaskCompletionSource<byte[]>(TaskCreationOptions.RunContinuationsAsynchronously);
            _pendingGlbExports[reqId] = tcs;

            PostToViewer(new
            {
                cmd = "exportRiggedGlb",
                requestId = reqId,
                keepCurrentPose
            });

            using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(45));
            using var reg = cts.Token.Register(() =>
            {
                _pendingGlbExports.TryRemove(reqId, out _);
                tcs.TrySetCanceled();
            });
            return await tcs.Task;
        }

        internal static byte[] StampSeelenfunkeGlbMetadata(byte[] rawGlb)
        {
            if (rawGlb == null || rawGlb.Length < 20)
                return rawGlb ?? Array.Empty<byte>();

            uint magic = BitConverter.ToUInt32(rawGlb, 0);
            uint version = BitConverter.ToUInt32(rawGlb, 4);
            uint jsonChunkLen = BitConverter.ToUInt32(rawGlb, 12);
            uint jsonChunkType = BitConverter.ToUInt32(rawGlb, 16);

            if (magic != 0x46546C67u || version != 2u || jsonChunkType != 0x4E4F534Au || 20L + jsonChunkLen > rawGlb.Length)
                return rawGlb;

            try
            {
                string jsonText = Encoding.UTF8.GetString(rawGlb, 20, (int)jsonChunkLen);
                var node = JsonNode.Parse(jsonText) as JsonObject;
                if (node == null) return rawGlb;

                var assetObj = node["asset"] as JsonObject ?? new JsonObject();
                assetObj["version"] = "2.0";
                assetObj["generator"] = "Funkenwerk 3D Exoskeleton Studio (Mein-Seelenfunke)";
                assetObj["copyright"] = "© Mein-Seelenfunke (Alina Steinhauer)";
                node["asset"] = assetObj;

                byte[] newJsonRaw = Encoding.UTF8.GetBytes(node.ToJsonString());
                int paddedLen = (newJsonRaw.Length + 3) & ~3;
                int trailingOffset = 20 + (int)jsonChunkLen;
                int trailingLen = rawGlb.Length - trailingOffset;

                uint newTotalLen = (uint)(20 + paddedLen + trailingLen);
                byte[] outGlb = new byte[newTotalLen];
                BitConverter.GetBytes(0x46546C67u).CopyTo(outGlb, 0);
                BitConverter.GetBytes(2u).CopyTo(outGlb, 4);
                BitConverter.GetBytes(newTotalLen).CopyTo(outGlb, 8);
                BitConverter.GetBytes((uint)paddedLen).CopyTo(outGlb, 12);
                BitConverter.GetBytes(0x4E4F534Au).CopyTo(outGlb, 16);

                Buffer.BlockCopy(newJsonRaw, 0, outGlb, 20, newJsonRaw.Length);
                for (int i = newJsonRaw.Length; i < paddedLen; i++)
                    outGlb[20 + i] = 0x20;

                if (trailingLen > 0)
                    Buffer.BlockCopy(rawGlb, trailingOffset, outGlb, 20 + paddedLen, trailingLen);

                return outGlb;
            }
            catch
            {
                return rawGlb;
            }
        }

        private void ExoskeletonTopBtn_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_activeModelPath))
            {
                ShowToast("Bitte zuerst ein 3D-Modell öffnen.", isError: false);
                return;
            }

            if (!_currentIsRigged)
            {
                GenerateExoskeleton_Click(sender, e);
            }
            else
            {
                ShowBonesCheckBox.IsChecked = !(ShowBonesCheckBox.IsChecked == true);
            }
        }

        private async void GenerateExoskeleton_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_activeModelPath))
            {
                ShowToast("Bitte zuerst ein 3D-Modell öffnen.", isError: false);
                return;
            }

            string profile = (RigProfileCombo.SelectedItem as ComboBoxItem)?.Tag as string ?? "auto";
            string variant = (RigVariantCombo.SelectedItem as ComboBoxItem)?.Tag as string ?? "original";
            bool stripPedestal = StripPedestalCheckBox.IsChecked == true;

            GenerateRigBtn.IsEnabled = false;
            try
            {
                var res = await GenerateExoskeletonAsync(profile, variant, stripPedestal, showBones: true, autoPlay: true);
                ShowToast($"Exoskelett aktiv: {res.BoneCount} Knochen & {res.ClipCount} Animationen", isError: false);
            }
            catch (Exception ex)
            {
                ShowToast($"Exoskelett fehlgeschlagen: {ex.Message}", isError: true);
            }
            finally
            {
                GenerateRigBtn.IsEnabled = true;
            }
        }

        private void ShowBonesCheckBox_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressUiEvents) return;
            _showBonesOn = ShowBonesCheckBox.IsChecked == true;
            UpdateTogglePillVisual(ExoskeletonTopBtn, _currentIsRigged && _showBonesOn);
            PostToViewer(new { cmd = "setShowBones", enabled = _showBonesOn });
        }

        private void PoseBoneCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressUiEvents) return;
            if (PoseBoneCombo.SelectedItem is ComboBoxItem item && item.Tag is int boneIdx)
            {
                if (boneIdx >= 0 && ShowBonesCheckBox.IsChecked != true)
                {
                    _suppressUiEvents = true;
                    ShowBonesCheckBox.IsChecked = true;
                    _showBonesOn = true;
                    UpdateTogglePillVisual(ExoskeletonTopBtn, _currentIsRigged);
                    _suppressUiEvents = false;
                }
                PostToViewer(new { cmd = "selectBoneForPose", index = boneIdx });
            }
        }

        private void PoseReset_Click(object sender, RoutedEventArgs e)
        {
            _suppressUiEvents = true;
            if (PoseBoneCombo.Items.Count > 0)
                PoseBoneCombo.SelectedIndex = 0;
            _suppressUiEvents = false;
            PostToViewer(new { cmd = "resetPose" });
        }

        private async void SaveRiggedGlb_Click(object sender, RoutedEventArgs e)
        {
            if (!_currentIsRigged || string.IsNullOrWhiteSpace(_activeModelPath))
            {
                ShowToast("Bitte zuerst ein Exoskelett erzeugen.", isError: false);
                return;
            }

            string baseStem = Path.GetFileNameWithoutExtension(_activeModelPath);
            string variant = (RigVariantCombo.SelectedItem as ComboBoxItem)?.Tag as string ?? "original";
            string suffix = variant == "funkira_female" ? "_funkira_rigged" : (variant == "funki_male" ? "_funki_rigged" : "_rigged");
            if (baseStem.EndsWith("_rigged", StringComparison.OrdinalIgnoreCase))
                suffix = "";

            var dlg = new SaveFileDialog
            {
                Title = "Geriggtes .glb speichern",
                Filter = "glTF 2.0 Binär-Modell (*.glb)|*.glb",
                FileName = $"{baseStem}{suffix}.glb"
            };

            if (dlg.ShowDialog(this) == true)
            {
                try
                {
                    SaveRiggedGlbBtn.IsEnabled = false;
                    ExportGlbTopBtn.IsEnabled = false;
                    byte[] glbBytes = await ExportRiggedGlbBytesAsync(keepCurrentPose: false);
                    string tmpOut = dlg.FileName + ".tmp";
                    await File.WriteAllBytesAsync(tmpOut, glbBytes);
                    File.Move(tmpOut, dlg.FileName, overwrite: true);
                    ShowToast($"Geriggtes .glb gespeichert ({FormatBytes(glbBytes.Length)})", isError: false);
                }
                catch (Exception ex)
                {
                    ShowToast($".glb-Export fehlgeschlagen: {ex.Message}", isError: true);
                }
                finally
                {
                    SaveRiggedGlbBtn.IsEnabled = _currentIsRigged;
                    ExportGlbTopBtn.IsEnabled = _currentIsRigged;
                }
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
            if (e.Data.GetData(DataFormats.FileDrop) is string[] files && files.Length > 0)
            {
                e.Handled = true;
                try { await LoadModelFileAsync(files[0]); } catch { }
            }
        }

        private async void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (Keyboard.Modifiers == ModifierKeys.Control)
            {
                if (e.Key == Key.O)
                {
                    OpenFile_Click(sender, e);
                    e.Handled = true;
                }
                else if (e.Key == Key.S)
                {
                    ExportPng_Click(sender, e);
                    e.Handled = true;
                }
                else if (e.Key == Key.E && _currentIsRigged)
                {
                    SaveRiggedGlb_Click(sender, e);
                    e.Handled = true;
                }
            }
            else if (Keyboard.Modifiers == ModifierKeys.Alt)
            {
                if (e.Key == Key.Left && PrevModelBtn.IsEnabled)
                {
                    PrevModel_Click(sender, e);
                    e.Handled = true;
                }
                else if (e.Key == Key.Right && NextModelBtn.IsEnabled)
                {
                    NextModel_Click(sender, e);
                    e.Handled = true;
                }
            }
            else if (Keyboard.Modifiers == ModifierKeys.None && !(Keyboard.FocusedElement is TextBox))
            {
                if (e.Key == Key.R) { ResetCamera_Click(sender, e); e.Handled = true; }
                else if (e.Key == Key.W) { ToggleWireframe_Click(sender, e); e.Handled = true; }
                else if (e.Key == Key.M) { ToggleDimensions_Click(sender, e); e.Handled = true; }
                else if (e.Key == Key.E) { ExoskeletonTopBtn_Click(sender, e); e.Handled = true; }
                else if (e.Key == Key.Space) { ToggleTurntable_Click(sender, e); e.Handled = true; }
            }
            await Task.CompletedTask;
        }

        private void ShowToast(string message, bool isError)
        {
            ViewerToastBorder.BorderBrush = isError
                ? (SolidColorBrush)new BrushConverter().ConvertFromString("#EF4444")!
                : (Brush)Resources["GoldBrush"];
            ViewerToastText.Text = message;
            ViewerToastBorder.Visibility = Visibility.Visible;
            _toastTimer.Stop();
            _toastTimer.Start();
        }

        private static void SelectComboByTag(ComboBox combo, string? tag)
        {
            if (string.IsNullOrWhiteSpace(tag)) return;
            foreach (ComboBoxItem item in combo.Items.OfType<ComboBoxItem>())
            {
                if (string.Equals(item.Tag as string, tag, StringComparison.OrdinalIgnoreCase))
                {
                    combo.SelectedItem = item;
                    return;
                }
            }
        }

        private static string FormatMillimeters(double valMm)
        {
            var de = new CultureInfo("de-DE");
            if (valMm >= 10000.0)
                return (valMm / 1000.0).ToString("0.00", de) + " m";
            return valMm.ToString("0.0", de) + " mm";
        }

        private static string FormatBytes(long bytes)
        {
            var de = new CultureInfo("de-DE");
            if (bytes < 1024) return $"{bytes} B";
            if (bytes < 1024 * 1024) return (bytes / 1024.0).ToString("0.0", de) + " KB";
            return (bytes / (1024.0 * 1024.0)).ToString("0.00", de) + " MB";
        }
    }
}

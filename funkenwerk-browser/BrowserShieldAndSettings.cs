using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.AccessControl;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Web.WebView2.Core;

namespace FunkenwerkBrowser
{
    public enum BrowserPermissionDecision
    {
        Deny,
        Allow,
        PromptUser
    }

    public sealed class QuickBookmarkDto
    {
        public string Label { get; set; } = "";
        public string Url { get; set; } = "";
    }

    public sealed class BrowserSettings
    {
        public bool DarkMode { get; set; } = true;
        public bool StrictFortressMode { get; set; } = false;
        public bool MigratedOpenWebDefaultV2 { get; set; } = true;
        public bool MigratedChromeBookmarkTreeV3 { get; set; } = false;
        public bool MigratedChromeBookmarkTreeV4 { get; set; } = false;
        public bool MigratedChromeBookmarkTreeV5 { get; set; } = false;
        public DateTime? AirlockOpenUntilUtc { get; set; }
        public string HomeUrl { get; set; } = "https://www.google.com";
        public List<string> CustomAllowedDomains { get; set; } = new();
        public List<BookmarkNodeDto> BookmarkBarNodes { get; set; } = new();
        public List<QuickBookmarkDto> Bookmarks { get; set; } = new()
        {
            new() { Label = "Google", Url = "https://www.google.com" },
            new() { Label = "YouTube", Url = "https://www.youtube.com" },
            new() { Label = "Instagram", Url = "https://www.instagram.com" },
            new() { Label = "WhatsApp", Url = "https://web.whatsapp.com" },
            new() { Label = "Mein-Seelenfunke", Url = "https://mein-seelenfunke.de" },
            new() { Label = "Admin", Url = "https://mein-seelenfunke.de/admin/dashboard" },
            new() { Label = "Stage", Url = "https://stage.mein-seelenfunke.de" },
            new() { Label = "Lokal", Url = "http://127.0.0.1" },
            new() { Label = "FYRST", Url = "https://www.fyrst.de" },
            new() { Label = "DHL", Url = "https://geschaeftskunden.dhl.de" }
        };
    }

    public static class BrowserShieldAndSettings
    {
        private static string? _dataDirOverride;
        private static string? _lastLockedDataDir;

        private sealed class DataDirScope : IDisposable
        {
            private readonly string? _previousOverride;
            public DataDirScope(string tempDir)
            {
                _previousOverride = _dataDirOverride;
                _dataDirOverride = tempDir;
                _lastLockedDataDir = null;
                EnsureDataDirLockedDown();
            }

            public void Dispose()
            {
                _dataDirOverride = _previousOverride;
                _lastLockedDataDir = null;
            }
        }

        private static readonly string[] BuiltInAllowedSuffixes =
        {
            "mein-seelenfunke.de",
            "seelenfunke.test",
            "127.0.0.1",
            "localhost",
            "fyrst.de",
            "postbank.de",
            "deutsche-bank.de",
            "stripe.com",
            "stripecdn.com",
            "dhl.de",
            "dhl.com",
            "gluecksfieber.de",
            "printequipment.de",
            "westpack.com",
            "karton.eu",
            "kartonfritze.de",
            "amazon.de",
            "media-amazon.com",
            "ssl-images-amazon.com",
            "elster.de",
            "mittwald.de",
            "mittwald.io",
            "whatsapp.com",
            "whatsapp.net",
            "github.com",
            "githubusercontent.com",
            "laravel.com",
            "packagist.org",
            "npmjs.com",
            "cloudflare.com",
            "jsdelivr.net",
            "unpkg.com",
            "fonts.googleapis.com",
            "fonts.gstatic.com",
            "googleapis.com",
            "gstatic.com",
            "google.com",
            "youtube.com",
            "youtu.be",
            "ytimg.com",
            "googlevideo.com",
            "ggpht.com",
            "googleusercontent.com",
            "youtube-nocookie.com",
            "instagram.com",
            "cdninstagram.com",
            "fbcdn.net"
        };

        private static readonly string[] AdAndTrackerSuffixes =
        {
            "doubleclick.net",
            "googlesyndication.com",
            "googleadservices.com",
            "google-analytics.com",
            "googletagmanager.com",
            "adservice.google.com",
            "connect.facebook.net",
            "graph.facebook.com",
            "clarity.ms",
            "hotjar.com",
            "criteo.com",
            "criteo.net",
            "taboola.com",
            "outbrain.com",
            "adnxs.com",
            "amazon-adsystem.com",
            "scorecardresearch.com",
            "quantserve.com",
            "rubiconproject.com",
            "pubmatic.com",
            "openx.net",
            "moatads.com",
            "adsrvr.org",
            "smartadserver.com",
            "teads.tv"
        };

        public static string DefaultProductionDataDir =>
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkBrowser");

        public static string DataDir
        {
            get
            {
                string dir = !string.IsNullOrWhiteSpace(_dataDirOverride)
                    ? _dataDirOverride!
                    : DefaultProductionDataDir;

                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                }

                if (!string.Equals(_lastLockedDataDir, dir, StringComparison.OrdinalIgnoreCase))
                {
                    LockDownDirectoryAcl(dir);
                    _lastLockedDataDir = dir;
                }

                return dir;
            }
        }

        public static string WebViewUserDataDir => Path.Combine(DataDir, "WebViewProfile");
        public static string SettingsPath => Path.Combine(DataDir, "browser_settings.json");

        public static IDisposable UseTemporaryDataDirScope(string tempDir) => new DataDirScope(tempDir);

        public static void EnsureDataDirLockedDown()
        {
            string dir = !string.IsNullOrWhiteSpace(_dataDirOverride)
                ? _dataDirOverride!
                : DefaultProductionDataDir;

            if (!Directory.Exists(dir))
                Directory.CreateDirectory(dir);

            LockDownDirectoryAcl(dir);
            _lastLockedDataDir = dir;

            if (File.Exists(SettingsPath))
                LockDownFileAcl(SettingsPath);
            if (File.Exists(BrowserAutofillAndVaultEngine.VaultFilePath))
                LockDownFileAcl(BrowserAutofillAndVaultEngine.VaultFilePath);
        }

        public static BrowserSettings LoadSettings()
        {
            try
            {
                if (File.Exists(SettingsPath))
                {
                    LockDownFileAcl(SettingsPath);
                    using var rawDoc = JsonDocument.Parse(File.ReadAllText(SettingsPath, Encoding.UTF8));
                    bool alreadyMigrated = rawDoc.RootElement.TryGetProperty("MigratedOpenWebDefaultV2", out var mp) && mp.ValueKind == JsonValueKind.True;
                    bool chromeTreeMigratedV5 = rawDoc.RootElement.TryGetProperty("MigratedChromeBookmarkTreeV5", out var cp5) && cp5.ValueKind == JsonValueKind.True;

                    var loaded = JsonSerializer.Deserialize<BrowserSettings>(rawDoc.RootElement.GetRawText());
                    if (loaded != null)
                    {
                        loaded.BookmarkBarNodes ??= new List<BookmarkNodeDto>();
                        loaded.CustomAllowedDomains ??= new List<string>();

                        bool needsSave = false;
                        if (!alreadyMigrated)
                        {
                            loaded.StrictFortressMode = false;
                            loaded.MigratedOpenWebDefaultV2 = true;
                            needsSave = true;
                        }

                        // Once MigratedChromeBookmarkTreeV5 == true, NEVER overwrite user bookmarks from Chrome
                        // even if the user intentionally keeps flat bookmarks with 0 folders!
                        if (!chromeTreeMigratedV5)
                        {
                            loaded.BookmarkBarNodes = BrowserAutofillAndVaultEngine.ImportChromeBookmarkTree();
                            loaded.MigratedChromeBookmarkTreeV3 = true;
                            loaded.MigratedChromeBookmarkTreeV4 = true;
                            loaded.MigratedChromeBookmarkTreeV5 = true;
                            needsSave = true;
                        }

                        if (needsSave) SaveSettings(loaded);
                        return loaded;
                    }
                }
            }
            catch { }

            var fresh = new BrowserSettings
            {
                MigratedChromeBookmarkTreeV3 = true,
                MigratedChromeBookmarkTreeV4 = true,
                MigratedChromeBookmarkTreeV5 = true,
                BookmarkBarNodes = BrowserAutofillAndVaultEngine.ImportChromeBookmarkTree()
            };
            SaveSettings(fresh);
            return fresh;
        }

        public static void SaveSettings(BrowserSettings s)
        {
            try
            {
                _ = DataDir;
                string tmp = SettingsPath + ".tmp";
                byte[] bytes = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(s, new JsonSerializerOptions { WriteIndented = true }));
                using (var fs = new FileStream(tmp, FileMode.Create, FileAccess.Write, FileShare.None))
                {
                    LockDownFileAcl(tmp);
                    fs.Write(bytes, 0, bytes.Length);
                    fs.Flush(true);
                }
                LockDownFileAcl(tmp);
                File.Move(tmp, SettingsPath, overwrite: true);
                LockDownFileAcl(SettingsPath);
            }
            catch { }
        }

        // =====================================================================
        // BOOKMARK TREE SEARCH, MOVE & CYCLE PROTECTION
        // =====================================================================
        public static bool ContainsDescendantId(BookmarkNodeDto? candidateParent, string? targetId, HashSet<string>? visited = null)
        {
            if (candidateParent == null || string.IsNullOrWhiteSpace(targetId))
                return false;

            visited ??= new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            if (!visited.Add(candidateParent.Id))
                return true;

            if (string.Equals(candidateParent.Id, targetId, StringComparison.OrdinalIgnoreCase))
                return true;

            if (candidateParent.Children == null)
                return false;

            foreach (var child in candidateParent.Children)
            {
                if (string.Equals(child.Id, targetId, StringComparison.OrdinalIgnoreCase))
                    return true;
                if (child.IsFolder && ContainsDescendantId(child, targetId, visited))
                    return true;
            }
            return false;
        }

        public static bool TryMoveNodeIntoFolder(List<BookmarkNodeDto> rootBarNodes, string draggedId, string targetFolderId)
        {
            if (rootBarNodes == null || string.IsNullOrWhiteSpace(draggedId) || string.IsNullOrWhiteSpace(targetFolderId))
                return false;

            var (foundDragged, draggedParentList, _) = FindNodeAndParent(rootBarNodes, draggedId);
            if (foundDragged == null || draggedParentList == null)
                return false;

            if (string.Equals(targetFolderId, "ROOT_BAR", StringComparison.OrdinalIgnoreCase))
            {
                draggedParentList.Remove(foundDragged);
                rootBarNodes.Add(foundDragged);
                return true;
            }

            var (targetFolder, _, _) = FindNodeAndParent(rootBarNodes, targetFolderId);
            if (targetFolder == null || !targetFolder.IsFolder)
                return false;

            // Block moving a folder into itself or any of its descendants
            if (ContainsDescendantId(foundDragged, targetFolder.Id))
                return false;

            draggedParentList.Remove(foundDragged);
            targetFolder.Children ??= new List<BookmarkNodeDto>();
            targetFolder.Children.Add(foundDragged);
            return true;
        }

        public static bool MoveNodeRelativeToTarget(List<BookmarkNodeDto> rootBarNodes, string draggedId, string targetNodeId, bool insertAfter = false)
        {
            if (rootBarNodes == null || string.IsNullOrWhiteSpace(draggedId) || string.IsNullOrWhiteSpace(targetNodeId))
                return false;
            if (string.Equals(draggedId, targetNodeId, StringComparison.OrdinalIgnoreCase))
                return false;

            var (foundDragged, draggedParentList, _) = FindNodeAndParent(rootBarNodes, draggedId);
            var (foundTarget, targetParentList, targetParentFolder) = FindNodeAndParent(rootBarNodes, targetNodeId);
            if (foundDragged == null || draggedParentList == null || foundTarget == null || targetParentList == null)
                return false;

            // Block moving a folder relative to itself or any node inside its own subtree
            if (ContainsDescendantId(foundDragged, foundTarget.Id))
                return false;
            if (targetParentFolder != null && ContainsDescendantId(foundDragged, targetParentFolder.Id))
                return false;

            draggedParentList.Remove(foundDragged);
            int targetIdx = targetParentList.IndexOf(foundTarget);
            if (targetIdx < 0)
            {
                targetParentList.Add(foundDragged);
            }
            else
            {
                int insertIdx = insertAfter ? targetIdx + 1 : targetIdx;
                insertIdx = Math.Clamp(insertIdx, 0, targetParentList.Count);
                targetParentList.Insert(insertIdx, foundDragged);
            }
            return true;
        }

        public static (BookmarkNodeDto? Node, List<BookmarkNodeDto>? ParentList, BookmarkNodeDto? ParentFolder) FindNodeAndParent(
            List<BookmarkNodeDto> currentList,
            string targetId,
            BookmarkNodeDto? currentParentFolder = null,
            HashSet<string>? visited = null)
        {
            visited ??= new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            foreach (var item in currentList)
            {
                if (!visited.Add(item.Id)) continue;

                if (string.Equals(item.Id, targetId, StringComparison.OrdinalIgnoreCase))
                    return (item, currentList, currentParentFolder);

                if (item.IsFolder && item.Children != null)
                {
                    var sub = FindNodeAndParent(item.Children, targetId, item, visited);
                    if (sub.Node != null) return sub;
                }
            }
            return (null, null, null);
        }

        public static BookmarkNodeDto? FindBookmarkByUrl(IEnumerable<BookmarkNodeDto> nodes, string url, HashSet<string>? visited = null)
        {
            if (string.IsNullOrWhiteSpace(url)) return null;
            visited ??= new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            string cleanTarget = url.Trim().TrimEnd('/');
            foreach (var n in nodes)
            {
                if (!visited.Add(n.Id)) continue;
                if (n.IsFolder)
                {
                    if (n.Children == null) continue;
                    var found = FindBookmarkByUrl(n.Children, url, visited);
                    if (found != null) return found;
                }
                else if (string.Equals((n.Url ?? "").Trim().TrimEnd('/'), cleanTarget, StringComparison.OrdinalIgnoreCase))
                {
                    return n;
                }
            }
            return null;
        }

        public static List<(string FolderId, string DisplayPath, List<BookmarkNodeDto> TargetList)> CollectAllFolderTargets(
            List<BookmarkNodeDto> rootBarNodes,
            string? excludeSubtreeId = null)
        {
            var list = new List<(string FolderId, string DisplayPath, List<BookmarkNodeDto> TargetList)>
            {
                ("ROOT_BAR", "★ Lesezeichenleiste (Hauptebene)", rootBarNodes)
            };

            var visited = new HashSet<string>(StringComparer.OrdinalIgnoreCase);

            void Walk(IEnumerable<BookmarkNodeDto> nodes, string prefix)
            {
                foreach (var n in nodes)
                {
                    if (!n.IsFolder) continue;
                    if (!visited.Add(n.Id)) continue;
                    if (!string.IsNullOrEmpty(excludeSubtreeId) && string.Equals(n.Id, excludeSubtreeId, StringComparison.OrdinalIgnoreCase))
                        continue;

                    string folderTitle = string.IsNullOrWhiteSpace(n.Name) ? "Ordner" : n.Name;
                    string path = string.IsNullOrEmpty(prefix) ? $"📁 {folderTitle}" : $"{prefix} › {folderTitle}";
                    n.Children ??= new List<BookmarkNodeDto>();
                    list.Add((n.Id, path, n.Children));
                    Walk(n.Children, path);
                }
            }

            Walk(rootBarNodes, "");
            return list;
        }

        // =====================================================================
        // FORTRESS ALLOWLIST, AD/TRACKER SHIELD, FILE:// GUARD & PERMISSIONS
        // =====================================================================
        public static bool IsAirlockTemporarilyOpen(BrowserSettings s, out TimeSpan remaining)
        {
            remaining = TimeSpan.Zero;
            if (!s.StrictFortressMode)
            {
                remaining = TimeSpan.FromHours(24);
                return true;
            }
            if (s.AirlockOpenUntilUtc.HasValue)
            {
                var diff = s.AirlockOpenUntilUtc.Value - DateTime.UtcNow;
                if (diff.TotalSeconds > 0)
                {
                    remaining = diff;
                    return true;
                }
                s.AirlockOpenUntilUtc = null;
            }
            return false;
        }

        public static bool IsAdOrTrackerUri(string? rawUri)
        {
            if (string.IsNullOrWhiteSpace(rawUri)) return false;
            if (!Uri.TryCreate(rawUri, UriKind.Absolute, out var uri)) return false;
            string host = uri.Host.ToLowerInvariant();

            if (AdAndTrackerSuffixes.Any(suf => host == suf || host.EndsWith("." + suf, StringComparison.Ordinal)))
                return true;

            string path = uri.AbsolutePath.ToLowerInvariant();
            if (path.Contains("/pixel", StringComparison.Ordinal) &&
                (host.Contains("tiktok", StringComparison.Ordinal) ||
                 host.Contains("facebook", StringComparison.Ordinal) ||
                 host.Contains("analytics", StringComparison.Ordinal)))
            {
                return true;
            }

            return false;
        }

        public static bool IsAllowedNavigationUri(BrowserSettings s, string? rawUri) =>
            IsAllowedNavigationUri(s, rawUri, out _);

        public static bool IsAllowedNavigationUri(BrowserSettings s, string? rawUri, out string blockedHost) =>
            IsNavigationAllowedByFortress(s, rawUri, out blockedHost);

        public static bool IsNavigationAllowedByFortress(BrowserSettings s, string? rawUri, out string blockedHost)
        {
            blockedHost = "";
            if (string.IsNullOrWhiteSpace(rawUri)) return true;

            string trimmed = rawUri.Trim();
            if (trimmed.StartsWith("file:", StringComparison.OrdinalIgnoreCase))
            {
                blockedHost = "file://";
                return false;
            }

            if (trimmed.StartsWith("about:", StringComparison.OrdinalIgnoreCase) ||
                trimmed.StartsWith("data:", StringComparison.OrdinalIgnoreCase) ||
                trimmed.StartsWith("blob:", StringComparison.OrdinalIgnoreCase) ||
                trimmed.StartsWith("devtools:", StringComparison.OrdinalIgnoreCase) ||
                trimmed.StartsWith("edge://", StringComparison.OrdinalIgnoreCase) ||
                trimmed.StartsWith("chrome://", StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }

            if (!Uri.TryCreate(trimmed, UriKind.Absolute, out var uri))
                return false;

            if (uri.Scheme.Equals("file", StringComparison.OrdinalIgnoreCase))
            {
                blockedHost = "file://";
                return false;
            }

            if (!uri.Scheme.Equals(Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase) &&
                !uri.Scheme.Equals(Uri.UriSchemeHttp, StringComparison.OrdinalIgnoreCase))
            {
                blockedHost = uri.Scheme;
                return false;
            }

            string host = uri.Host.ToLowerInvariant();
            if (IsAdOrTrackerUri(trimmed))
            {
                blockedHost = host;
                return false;
            }

            if (IsAirlockTemporarilyOpen(s, out _))
                return true;

            bool builtInOk = BuiltInAllowedSuffixes.Any(suf => host == suf || host.EndsWith("." + suf, StringComparison.Ordinal));
            if (builtInOk) return true;

            bool customOk = s.CustomAllowedDomains.Any(dom =>
            {
                string clean = dom.Trim().ToLowerInvariant();
                return !string.IsNullOrEmpty(clean) && (host == clean || host.EndsWith("." + clean, StringComparison.Ordinal));
            });

            if (customOk) return true;

            blockedHost = host;
            return false;
        }

        public static BrowserPermissionDecision EvaluatePermissionRequest(string? requestUri, CoreWebView2PermissionKind kind)
        {
            if (string.IsNullOrWhiteSpace(requestUri))
                return BrowserPermissionDecision.Deny;

            if (!Uri.TryCreate(requestUri.Trim(), UriKind.Absolute, out var uri) || string.IsNullOrWhiteSpace(uri.Host))
                return BrowserPermissionDecision.Deny;

            bool isHttps = string.Equals(uri.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase);
            bool isLoopback = BrowserAutofillAndVaultEngine.IsLoopbackHost(uri.Host);
            bool isHttpLoopback = string.Equals(uri.Scheme, Uri.UriSchemeHttp, StringComparison.OrdinalIgnoreCase) && isLoopback;

            // Immediately deny insecure non-loopback HTTP or non-web schemes
            if (!isHttps && !isHttpLoopback)
                return BrowserPermissionDecision.Deny;

            // Deny untrusted permission types by default
            if (kind is not (CoreWebView2PermissionKind.Camera or CoreWebView2PermissionKind.Microphone))
                return BrowserPermissionDecision.Deny;

            // Auto-allow Camera/Mic ONLY for exact trusted Seelenfunke origins and local loopback
            if (isLoopback)
                return BrowserPermissionDecision.Allow;

            if (isHttps && uri.IsDefaultPort &&
                (string.Equals(uri.Host, "mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase) ||
                 string.Equals(uri.Host, "stage.mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase)))
            {
                return BrowserPermissionDecision.Allow;
            }

            // External HTTPS site: require explicit user confirmation
            return BrowserPermissionDecision.PromptUser;
        }

        public static string NormalizeUserUrlOrSearch(string input)
        {
            string raw = (input ?? "").Trim();
            if (string.IsNullOrEmpty(raw)) return "https://mein-seelenfunke.de";

            if (raw.StartsWith("javascript:", StringComparison.OrdinalIgnoreCase) ||
                raw.StartsWith("vbscript:", StringComparison.OrdinalIgnoreCase))
            {
                return "about:blank";
            }

            // Preserve file:// or rooted local filesystem paths as file:// URIs so IsAllowedNavigationUri blocks them cleanly
            if (raw.StartsWith("file:", StringComparison.OrdinalIgnoreCase))
                return raw;

            if ((raw.Length >= 3 && char.IsLetter(raw[0]) && raw[1] == ':' && (raw[2] == '\\' || raw[2] == '/')) ||
                raw.StartsWith(@"\\", StringComparison.Ordinal))
            {
                return "file:///" + raw.TrimStart('\\', '/').Replace('\\', '/');
            }

            if (raw.StartsWith("http://", StringComparison.OrdinalIgnoreCase) ||
                raw.StartsWith("https://", StringComparison.OrdinalIgnoreCase) ||
                raw.StartsWith("edge://", StringComparison.OrdinalIgnoreCase))
            {
                return raw;
            }

            if (raw.StartsWith("chrome://", StringComparison.OrdinalIgnoreCase))
                return "edge://" + raw.Substring("chrome://".Length);

            if (Uri.TryCreate("http://" + raw, UriKind.Absolute, out var candidateLocal) &&
                (BrowserAutofillAndVaultEngine.IsLoopbackHost(candidateLocal.Host) ||
                 candidateLocal.Host.EndsWith(".test", StringComparison.OrdinalIgnoreCase) ||
                 string.Equals(candidateLocal.Host, "seelenfunke.test", StringComparison.OrdinalIgnoreCase)))
            {
                return "http://" + raw;
            }

            if (!raw.Contains(' ') && raw.Contains('.'))
                return "https://" + raw;

            return "https://www.google.com/search?q=" + Uri.EscapeDataString(raw);
        }

        // =====================================================================
        // NTFS ACL HARDENING FOR DATADIR, VAULT, SETTINGS & STAGED SQLITE FILES
        // =====================================================================
        public static void LockDownDirectoryAcl(string dirPath)
        {
            try
            {
                var di = new DirectoryInfo(dirPath);
                if (!di.Exists) return;
                var user = WindowsIdentity.GetCurrent().User;
                if (user == null) return;

                var sec = new DirectorySecurity();
                sec.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                sec.AddAccessRule(new FileSystemAccessRule(
                    user,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(
                    new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null),
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(
                    new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null),
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));
                di.SetAccessControl(sec);
            }
            catch { }
        }

        public static void LockDownFileAcl(string filePath)
        {
            try
            {
                var fi = new FileInfo(filePath);
                if (!fi.Exists) return;
                var user = WindowsIdentity.GetCurrent().User;
                if (user == null) return;

                var sec = new FileSecurity();
                sec.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                sec.AddAccessRule(new FileSystemAccessRule(
                    user,
                    FileSystemRights.FullControl,
                    InheritanceFlags.None,
                    PropagationFlags.None,
                    AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(
                    new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null),
                    FileSystemRights.FullControl,
                    InheritanceFlags.None,
                    PropagationFlags.None,
                    AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(
                    new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null),
                    FileSystemRights.FullControl,
                    InheritanceFlags.None,
                    PropagationFlags.None,
                    AccessControlType.Allow));
                fi.SetAccessControl(sec);
            }
            catch { }
        }
    }

    public static class BrowserSelfTestRunner
    {
        public static bool RunSelfTest(string? customReportPath = null)
        {
            try { Console.OutputEncoding = Encoding.UTF8; } catch { }

            string tempTestDir = Path.Combine(
                Path.GetTempPath(),
                "FunkenwerkBrowser_SelfTest_" + Guid.NewGuid().ToString("N"));

            var logLines = new List<string>();
            var tests = new List<(string Name, Func<bool> Test)>();

            void AddTest(string name, Func<bool> fn) => tests.Add((name, fn));

            // 1. Embedded Browser Icons
            AddTest("Browser-Ressourcen (funkenwerk_browser.ico & .png)", () =>
            {
                var s1 = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_browser.ico"));
                var s2 = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_browser.png"));
                return s1 != null && s1.Stream.Length > 100 && s2 != null && s2.Stream.Length > 100;
            });

            // 2. Tracker & Ad-Blocker URL & Domain Matching (Positive + Negative)
            AddTest("Ad- & Tracker-Blocker (Domains, Subdomains & Pixel-Filter)", () =>
            {
                bool pos = BrowserShieldAndSettings.IsAdOrTrackerUri("https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js") &&
                           BrowserShieldAndSettings.IsAdOrTrackerUri("https://www.google-analytics.com/collect?v=2") &&
                           BrowserShieldAndSettings.IsAdOrTrackerUri("https://stats.g.doubleclick.net/r/collect") &&
                           BrowserShieldAndSettings.IsAdOrTrackerUri("https://www.clarity.ms/tag/abc") &&
                           BrowserShieldAndSettings.IsAdOrTrackerUri("https://analytics.tiktok.com/pixel/events");

                bool neg = !BrowserShieldAndSettings.IsAdOrTrackerUri("https://mein-seelenfunke.de/shop") &&
                           !BrowserShieldAndSettings.IsAdOrTrackerUri("https://www.fyrst.de/login") &&
                           !BrowserShieldAndSettings.IsAdOrTrackerUri("https://not-doubleclick.net/index.html") &&
                           !BrowserShieldAndSettings.IsAdOrTrackerUri("not-a-valid-uri");
                return pos && neg;
            });

            // 3. Fortress Mode Allowlist & Open Web Default
            AddTest("Festungs-Modus Allowlist vs. Offenes Web Standard", () =>
            {
                var openSettings = new BrowserSettings { StrictFortressMode = false };
                bool openOk = BrowserShieldAndSettings.IsAllowedNavigationUri(openSettings, "https://www.youtube.com/watch?v=1") &&
                              BrowserShieldAndSettings.IsAllowedNavigationUri(openSettings, "https://wikipedia.org/wiki/Laser");

                var fortress = new BrowserSettings { StrictFortressMode = true };
                bool allowBuiltIn = BrowserShieldAndSettings.IsAllowedNavigationUri(fortress, "https://mein-seelenfunke.de/admin/dashboard") &&
                                    BrowserShieldAndSettings.IsAllowedNavigationUri(fortress, "https://stage.mein-seelenfunke.de") &&
                                    BrowserShieldAndSettings.IsAllowedNavigationUri(fortress, "https://www.fyrst.de/login");
                bool blockForeign = !BrowserShieldAndSettings.IsAllowedNavigationUri(fortress, "https://untrusted-casino.example.com/", out string host) &&
                                    host == "untrusted-casino.example.com";

                fortress.CustomAllowedDomains.Add("custom-supplier.de");
                bool allowCustom = BrowserShieldAndSettings.IsAllowedNavigationUri(fortress, "https://shop.custom-supplier.de/items");

                return openOk && allowBuiltIn && blockForeign && allowCustom;
            });

            // 4. 30-Minute Airlock Expiry
            AddTest("30-Minuten-Zeitschleuse Aktivierung & Auto-Ablauf", () =>
            {
                var s = new BrowserSettings
                {
                    StrictFortressMode = true,
                    AirlockOpenUntilUtc = DateTime.UtcNow.AddMinutes(30)
                };
                bool openNow = BrowserShieldAndSettings.IsAirlockTemporarilyOpen(s, out TimeSpan rem) &&
                               rem.TotalMinutes > 29 &&
                               BrowserShieldAndSettings.IsAllowedNavigationUri(s, "https://wikipedia.org");

                s.AirlockOpenUntilUtc = DateTime.UtcNow.AddSeconds(-5);
                bool closedAfter = !BrowserShieldAndSettings.IsAirlockTemporarilyOpen(s, out _) &&
                                   s.AirlockOpenUntilUtc == null &&
                                   !BrowserShieldAndSettings.IsAllowedNavigationUri(s, "https://wikipedia.org");
                return openNow && closedAfter;
            });

            // 5. file:// URI & Local Path Block (BRW-SEC-04)
            AddTest("Lokale file:// URI- & Dateipfad-Blockade (BRW-SEC-04)", () =>
            {
                var openMode = new BrowserSettings { StrictFortressMode = false };
                var airlockMode = new BrowserSettings { StrictFortressMode = true, AirlockOpenUntilUtc = DateTime.UtcNow.AddMinutes(30) };

                bool blockDirectFile = !BrowserShieldAndSettings.IsAllowedNavigationUri(openMode, "file:///C:/Windows/win.ini", out string bh1) && bh1 == "file://";
                bool blockAirlockFile = !BrowserShieldAndSettings.IsAllowedNavigationUri(airlockMode, "file:///etc/passwd", out string bh2) && bh2 == "file://";
                string normalizedWinPath = BrowserShieldAndSettings.NormalizeUserUrlOrSearch(@"C:\Windows\win.ini");
                bool blockWinPath = normalizedWinPath.StartsWith("file://", StringComparison.OrdinalIgnoreCase) &&
                                    !BrowserShieldAndSettings.IsAllowedNavigationUri(openMode, normalizedWinPath);
                bool blockJsUri = BrowserShieldAndSettings.NormalizeUserUrlOrSearch("javascript:alert(1)") == "about:blank";

                return blockDirectFile && blockAirlockFile && blockWinPath && blockJsUri;
            });

            // 6. SmartScreen Active & WebView2BrowserArguments Hardening (BRW-SEC-04)
            AddTest("SmartScreen aktiv & Browser-Startargumente gehärtet (BRW-SEC-04)", () =>
            {
                return !MainWindow.WebViewBrowserArguments.Contains("msSmartScreenProtection", StringComparison.OrdinalIgnoreCase) &&
                       MainWindow.WebViewBrowserArguments.Contains("AutofillServerCommunication", StringComparison.Ordinal);
            });

            // 7. Isolated Self-Test DataDir Verification
            AddTest("Isolierter Selbsttest-Profilordner schützt echte Benutzerdaten", () =>
            {
                string activeDir = BrowserShieldAndSettings.DataDir;
                string prodDir = BrowserShieldAndSettings.DefaultProductionDataDir;
                return Directory.Exists(activeDir) &&
                       string.Equals(Path.GetFullPath(activeDir), Path.GetFullPath(tempTestDir), StringComparison.OrdinalIgnoreCase) &&
                       !string.Equals(Path.GetFullPath(activeDir), Path.GetFullPath(prodDir), StringComparison.OrdinalIgnoreCase);
            });

            // 8. NTFS ACL Verification on DataDir, browser_settings.json & credentials_vault.json (BRW-SEC-05)
            AddTest("Restriktive NTFS-ACLs auf DataDir, Settings & Vault (BRW-SEC-05)", () =>
            {
                var s = new BrowserSettings { MigratedChromeBookmarkTreeV5 = true };
                BrowserShieldAndSettings.SaveSettings(s);

                var v = new BrowserVaultData { ImportedFromChromeV1 = true };
                BrowserAutofillAndVaultEngine.UpsertLoginCredential(v, "https://mein-seelenfunke.de", "alina@mein-seelenfunke.de", "AclVaultSecret!2026");
                BrowserAutofillAndVaultEngine.SaveVault(v);

                var currentUserSid = WindowsIdentity.GetCurrent().User;
                if (currentUserSid == null) return false;
                var systemSid = new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null);
                var adminsSid = new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null);
                var usersSid = new SecurityIdentifier(WellKnownSidType.BuiltinUsersSid, null);
                var everyoneSid = new SecurityIdentifier(WellKnownSidType.WorldSid, null);
                var authUsersSid = new SecurityIdentifier(WellKnownSidType.AuthenticatedUserSid, null);

                bool VerifyRules(CommonObjectSecurity sec)
                {
                    if (!sec.AreAccessRulesProtected) return false;
                    var rules = sec.GetAccessRules(includeExplicit: true, includeInherited: true, typeof(SecurityIdentifier))
                        .Cast<FileSystemAccessRule>()
                        .ToList();
                    if (rules.Count == 0) return false;

                    bool hasCurrentUser = rules.Any(r => r.AccessControlType == AccessControlType.Allow && r.IdentityReference.Equals(currentUserSid));
                    bool hasForbiddenBroadGroup = rules.Any(r =>
                        r.AccessControlType == AccessControlType.Allow &&
                        (r.IdentityReference.Equals(usersSid) ||
                         r.IdentityReference.Equals(everyoneSid) ||
                         r.IdentityReference.Equals(authUsersSid)));
                    bool onlyAllowedPrincipals = rules
                        .Where(r => r.AccessControlType == AccessControlType.Allow)
                        .All(r => r.IdentityReference.Equals(currentUserSid) ||
                                  r.IdentityReference.Equals(systemSid) ||
                                  r.IdentityReference.Equals(adminsSid));

                    return hasCurrentUser && !hasForbiddenBroadGroup && onlyAllowedPrincipals;
                }

                bool dirOk = VerifyRules(new DirectoryInfo(BrowserShieldAndSettings.DataDir).GetAccessControl());
                bool settingsOk = File.Exists(BrowserShieldAndSettings.SettingsPath) &&
                                  VerifyRules(new FileInfo(BrowserShieldAndSettings.SettingsPath).GetAccessControl());
                bool vaultOk = File.Exists(BrowserAutofillAndVaultEngine.VaultFilePath) &&
                               VerifyRules(new FileInfo(BrowserAutofillAndVaultEngine.VaultFilePath).GetAccessControl());

                return dirOk && settingsOk && vaultOk;
            });

            // 9. DPAPI + AES-256-GCM FWBVAULT1 Roundtrip & Random Nonce
            AddTest("FWBVAULT1 DPAPI + AES-256-GCM Roundtrip & Zufalls-Nonce", () =>
            {
                string secret = "Seelenfunke_Ümläut_Vault_2026!#€";
                string enc1 = BrowserAutofillAndVaultEngine.ProtectSecret(secret);
                string enc2 = BrowserAutofillAndVaultEngine.ProtectSecret(secret);
                string dec1 = BrowserAutofillAndVaultEngine.UnprotectSecret(enc1);
                string dec2 = BrowserAutofillAndVaultEngine.UnprotectSecret(enc2);
                return enc1.StartsWith("FWBVAULT1:", StringComparison.Ordinal) &&
                       enc2.StartsWith("FWBVAULT1:", StringComparison.Ordinal) &&
                       !string.Equals(enc1, enc2, StringComparison.Ordinal) &&
                       dec1 == secret &&
                       dec2 == secret;
            });

            // 10. FWBVAULT1 Tampered Tag, Ciphertext & Header Rejection
            AddTest("FWBVAULT1 Manipulations-Abwehr (Auth-Tag, Cipher & Header)", () =>
            {
                string enc = BrowserAutofillAndVaultEngine.ProtectSecret("TamperCheckSecret!99");
                byte[] raw = Convert.FromBase64String(enc.Substring("FWBVAULT1:".Length));

                // Flip last byte (ciphertext)
                byte[] flippedCipher = (byte[])raw.Clone();
                flippedCipher[^1] ^= 0xFF;
                string badCipher = "FWBVAULT1:" + Convert.ToBase64String(flippedCipher);

                // Flip byte in tag area (before cipherLen + cipher)
                int keyLen = BitConverter.ToInt32(raw, 0);
                byte[] flippedTag = (byte[])raw.Clone();
                flippedTag[4 + keyLen + 12 + 3] ^= 0xAA;
                string badTag = "FWBVAULT1:" + Convert.ToBase64String(flippedTag);

                return BrowserAutofillAndVaultEngine.UnprotectSecret(badCipher) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(badTag) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret("FWBVAULT0:AAAA") == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret("FWBVAULT1:!!!not_base64!!!") == "";
            });

            // 11. FWBVAULT1 Oversized & Negative Length Guard (BRW-SEC-05)
            AddTest("FWBVAULT1 Bounds-Check für keyLen (1..4096) & cipherLen (0..131072)", () =>
            {
                static string BuildMalformedToken(int keyLen, int actualKeyBytes, int cipherLen, int actualCipherBytes)
                {
                    using var ms = new MemoryStream();
                    using var bw = new BinaryWriter(ms);
                    bw.Write(keyLen);
                    bw.Write(new byte[actualKeyBytes]);
                    bw.Write(new byte[12]); // nonce
                    bw.Write(new byte[16]); // tag
                    bw.Write(cipherLen);
                    bw.Write(new byte[actualCipherBytes]);
                    return "FWBVAULT1:" + Convert.ToBase64String(ms.ToArray());
                }

                string zeroKey = BuildMalformedToken(0, 10, 8, 8);
                string negKey = BuildMalformedToken(-42, 10, 8, 8);
                string hugeKey = BuildMalformedToken(4097, 32, 8, 8);
                string maxIntKey = BuildMalformedToken(int.MaxValue, 32, 8, 8);
                string negCipher = BuildMalformedToken(32, 32, -1, 8);
                string hugeCipher = BuildMalformedToken(32, 32, 131073, 16);
                string mismatchCipher = BuildMalformedToken(32, 32, 64, 8);

                return BrowserAutofillAndVaultEngine.UnprotectSecret(zeroKey) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(negKey) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(hugeKey) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(maxIntKey) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(negCipher) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(hugeCipher) == "" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(mismatchCipher) == "";
            });

            // 12. Chrome SQLite Staging in DataDir & Zero-Fill Delete (BRW-SEC-05)
            AddTest("SQLite-Staging in DataDir mit ACL-Schutz & Zero-Fill-Delete (BRW-SEC-05)", () =>
            {
                string srcPath = Path.Combine(BrowserShieldAndSettings.DataDir, "mock_source.db");
                File.WriteAllText(srcPath, "SENSITIVE_SQLITE_PAYLOAD_1234567890", Encoding.UTF8);

                bool staged = BrowserAutofillAndVaultEngine.StageFileInLockedDataDir(srcPath, "test", out string stagedPath);
                if (!staged || !File.Exists(stagedPath)) return false;

                bool inDataDir = string.Equals(
                    Path.GetFullPath(Path.GetDirectoryName(stagedPath)!),
                    Path.GetFullPath(BrowserShieldAndSettings.DataDir),
                    StringComparison.OrdinalIgnoreCase);

                bool aclProtected = new FileInfo(stagedPath).GetAccessControl().AreAccessRulesProtected;
                File.WriteAllText(stagedPath + "-wal", "WAL_DATA", Encoding.UTF8);

                BrowserAutofillAndVaultEngine.SecureZeroDeleteFile(stagedPath);
                BrowserAutofillAndVaultEngine.SecureZeroDeleteFile(srcPath);

                return inDataDir && aclProtected && !File.Exists(stagedPath) && !File.Exists(stagedPath + "-wal");
            });

            // 13. NormalizeStrictOrigin Verification (BRW-SEC-01)
            AddTest("NormalizeStrictOrigin Schema-, Host- & Port-Prüfung (BRW-SEC-01)", () =>
            {
                return BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("https://Mein-Seelenfunke.de:443/login?x=1") == "https://mein-seelenfunke.de" &&
                       BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("https://mein-seelenfunke.de:8443/admin") == "https://mein-seelenfunke.de:8443" &&
                       BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("http://127.0.0.1:8000/login") == "http://127.0.0.1:8000" &&
                       BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("http://localhost/app") == "http://localhost" &&
                       BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("http://[::1]:5173/test") == "http://[::1]:5173" &&
                       BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("http://mein-seelenfunke.de/login") == "" &&
                       BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("http://127.0.0.1.evil.com/login") == "" &&
                       BrowserAutofillAndVaultEngine.NormalizeStrictOrigin("file:///C:/test.html") == "";
            });

            // 14. Strict Origin Login Matching vs Downgrade / Subdomain / Port / Reverse-Parent Leaks (BRW-SEC-01)
            AddTest("Striktes Login-Origin-Matching gegen Downgrade/Subdomain/Port-Leaks", () =>
            {
                var sfEntry = new SavedLoginEntryDto
                {
                    OriginUrl = "https://mein-seelenfunke.de",
                    Host = "mein-seelenfunke.de",
                    Username = "kontakt@mein-seelenfunke.de",
                    EncryptedPassword = BrowserAutofillAndVaultEngine.ProtectSecret("SfPass!1")
                };

                var adminSubEntry = new SavedLoginEntryDto
                {
                    OriginUrl = "https://admin.mein-seelenfunke.de",
                    Host = "admin.mein-seelenfunke.de",
                    Username = "admin@mein-seelenfunke.de",
                    EncryptedPassword = BrowserAutofillAndVaultEngine.ProtectSecret("AdminPass!2")
                };

                bool exactMatch = BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(sfEntry, "https://mein-seelenfunke.de/admin/login");
                bool rejectHttpDowngrade = !BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(sfEntry, "http://mein-seelenfunke.de/admin/login");
                bool rejectSubdomain = !BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(sfEntry, "https://evil.mein-seelenfunke.de/login");
                bool rejectPortMismatch = !BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(sfEntry, "https://mein-seelenfunke.de:8443/login");
                bool rejectReverseParent = !BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(adminSubEntry, "https://mein-seelenfunke.de/login");

                return exactMatch && rejectHttpDowngrade && rejectSubdomain && rejectPortMismatch && rejectReverseParent;
            });

            // 15. Legacy Bare-Host Entry Compatibility ONLY on HTTPS Port 443 (BRW-SEC-01)
            AddTest("Legacy Bare-Host Einträge matchen nur auf HTTPS Standardport 443", () =>
            {
                var legacy = new SavedLoginEntryDto
                {
                    OriginUrl = "",
                    Host = "mein-seelenfunke.de",
                    Username = "legacy@mein-seelenfunke.de"
                };

                return BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(legacy, "https://mein-seelenfunke.de/login") &&
                       !BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(legacy, "http://mein-seelenfunke.de/login") &&
                       !BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(legacy, "https://mein-seelenfunke.de:8443/login") &&
                       !BrowserAutofillAndVaultEngine.DoesLoginMatchStrictOrigin(legacy, "https://sub.mein-seelenfunke.de/login");
            });

            // 16. PII Isolation & Zero-Click Password Protection (BRW-SEC-01)
            AddTest("PII-Isolation & Kein Zero-Click-Autofill bei NavigationCompleted (BRW-SEC-01)", () =>
            {
                var vault = new BrowserVaultData { ImportedFromChromeV1 = true };
                BrowserAutofillAndVaultEngine.UpsertLoginCredential(
                    vault,
                    "https://mein-seelenfunke.de/login",
                    "kontakt@mein-seelenfunke.de",
                    "UltraGeheim_2026!");

                string passiveJs = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(
                    vault,
                    "https://mein-seelenfunke.de/login",
                    forceFillForms: false);

                bool zeroLeakOnLoad = !passiveJs.Contains("UltraGeheim_2026!", StringComparison.Ordinal) &&
                                      !passiveJs.Contains("Steinhauer", StringComparison.OrdinalIgnoreCase) &&
                                      !passiveJs.Contains("Carl-Goerdeler", StringComparison.OrdinalIgnoreCase) &&
                                      !passiveJs.Contains("1966864", StringComparison.Ordinal) &&
                                      !passiveJs.Contains("kontakt@mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase) &&
                                      !passiveJs.Contains("tryAutofillLogin", StringComparison.Ordinal) &&
                                      !passiveJs.Contains("origin: window.location.origin", StringComparison.Ordinal);

                string activeJs = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(
                    vault,
                    "https://mein-seelenfunke.de/checkout",
                    forceFillForms: true);

                bool activeIncludesWhenRequested = activeJs.Contains("UltraGeheim_2026!", StringComparison.Ordinal) &&
                                                   activeJs.Contains("Steinhauer", StringComparison.Ordinal) &&
                                                   activeJs.Contains("Carl-Goerdeler-Ring 26", StringComparison.Ordinal);

                string subdomainActiveJs = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(
                    vault,
                    "https://evil.mein-seelenfunke.de/login",
                    forceFillForms: true);

                bool noPasswordOnSubdomain = !subdomainActiveJs.Contains("UltraGeheim_2026!", StringComparison.Ordinal);

                return zeroLeakOnLoad && activeIncludesWhenRequested && noPasswordOnSubdomain;
            });

            // 17. JS Hidden Input & Subframe Protection Checks (BRW-SEC-01)
            AddTest("JS Input-Sichtbarkeits- & Top-Window-Prüfung (BRW-SEC-01)", () =>
            {
                var vault = new BrowserVaultData { ImportedFromChromeV1 = true };
                string js = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(
                    vault,
                    "https://mein-seelenfunke.de/login",
                    forceFillForms: true);

                return js.Contains("window.top !== window.self", StringComparison.Ordinal) &&
                       js.Contains("rect.width < 20", StringComparison.Ordinal) &&
                       js.Contains("rect.height < 12", StringComparison.Ordinal) &&
                       js.Contains("style.visibility === 'hidden'", StringComparison.Ordinal) &&
                       js.Contains("style.display === 'none'", StringComparison.Ordinal) &&
                       js.Contains("parseFloat(style.opacity || '1') <= 0.05", StringComparison.Ordinal);
            });

            // 18. WebMessage Origin Spoofing Validator (BRW-SEC-02)
            AddTest("WebMessage Origin-Validator gegen Spoofing & HTTP (BRW-SEC-02)", () =>
            {
                bool rejectHttpExternal = !BrowserAutofillAndVaultEngine.TryValidateWebMessageLoginOrigin(
                    "http://mein-seelenfunke.de/login",
                    "http://mein-seelenfunke.de/login",
                    out _);

                bool rejectLoopbackPrefixSpoof = !BrowserAutofillAndVaultEngine.TryValidateWebMessageLoginOrigin(
                    "http://127.0.0.1.evil.com/login",
                    "http://127.0.0.1.evil.com/login",
                    out _);

                bool rejectCrossFrameMismatch = !BrowserAutofillAndVaultEngine.TryValidateWebMessageLoginOrigin(
                    "https://attacker.example.com/iframe",
                    "https://mein-seelenfunke.de/login",
                    out _);

                bool rejectPortMismatch = !BrowserAutofillAndVaultEngine.TryValidateWebMessageLoginOrigin(
                    "https://mein-seelenfunke.de:8443/login",
                    "https://mein-seelenfunke.de/login",
                    out _);

                bool acceptValidHttps = BrowserAutofillAndVaultEngine.TryValidateWebMessageLoginOrigin(
                    "https://mein-seelenfunke.de/login",
                    "https://mein-seelenfunke.de/admin",
                    out string origin1) && origin1 == "https://mein-seelenfunke.de";

                bool acceptValidLoopback = BrowserAutofillAndVaultEngine.TryValidateWebMessageLoginOrigin(
                    "http://127.0.0.1:8000/login",
                    "http://127.0.0.1:8000/dashboard",
                    out string origin2) && origin2 == "http://127.0.0.1:8000";

                return rejectHttpExternal && rejectLoopbackPrefixSpoof && rejectCrossFrameMismatch &&
                       rejectPortMismatch && acceptValidHttps && acceptValidLoopback;
            });

            // 19. PermissionRequested Camera/Mic Policy Helper (BRW-SEC-03)
            AddTest("PermissionRequested Kamera/Mikrofon-Policy (BRW-SEC-03)", () =>
            {
                bool allowSf = BrowserShieldAndSettings.EvaluatePermissionRequest("https://mein-seelenfunke.de/studio", CoreWebView2PermissionKind.Camera) == BrowserPermissionDecision.Allow;
                bool allowStage = BrowserShieldAndSettings.EvaluatePermissionRequest("https://stage.mein-seelenfunke.de/call", CoreWebView2PermissionKind.Microphone) == BrowserPermissionDecision.Allow;
                bool allowLocal = BrowserShieldAndSettings.EvaluatePermissionRequest("http://127.0.0.1:8000/cam", CoreWebView2PermissionKind.Camera) == BrowserPermissionDecision.Allow &&
                                  BrowserShieldAndSettings.EvaluatePermissionRequest("http://localhost/mic", CoreWebView2PermissionKind.Microphone) == BrowserPermissionDecision.Allow;

                bool denyPrefixSpoof = BrowserShieldAndSettings.EvaluatePermissionRequest("http://127.0.0.1.evil.com/cam", CoreWebView2PermissionKind.Camera) == BrowserPermissionDecision.Deny;
                bool denyInsecureSf = BrowserShieldAndSettings.EvaluatePermissionRequest("http://mein-seelenfunke.de/cam", CoreWebView2PermissionKind.Camera) == BrowserPermissionDecision.Deny;
                bool promptExternalHttps = BrowserShieldAndSettings.EvaluatePermissionRequest("https://example.com/meet", CoreWebView2PermissionKind.Camera) == BrowserPermissionDecision.PromptUser;
                bool promptUntrustedSubdomain = BrowserShieldAndSettings.EvaluatePermissionRequest("https://sub.mein-seelenfunke.de/cam", CoreWebView2PermissionKind.Camera) == BrowserPermissionDecision.PromptUser;
                bool denyUntrustedKind = BrowserShieldAndSettings.EvaluatePermissionRequest("https://mein-seelenfunke.de", CoreWebView2PermissionKind.Geolocation) == BrowserPermissionDecision.Deny;

                return allowSf && allowStage && allowLocal && denyPrefixSpoof && denyInsecureSf &&
                       promptExternalHttps && promptUntrustedSubdomain && denyUntrustedKind;
            });

            // 20. Bookmark Tree Cycle Guard (A -> B -> C) & Serialization
            AddTest("Lesezeichen-Ordner Zyklus-Schutz (A -> B -> C) & JSON-Serialisierung", () =>
            {
                var itemX = new BookmarkNodeDto { Name = "Shop", Type = "url", Url = "https://mein-seelenfunke.de" };
                var folderC = new BookmarkNodeDto { Name = "C", Type = "folder", Children = new List<BookmarkNodeDto> { itemX } };
                var folderB = new BookmarkNodeDto { Name = "B", Type = "folder", Children = new List<BookmarkNodeDto> { folderC } };
                var folderA = new BookmarkNodeDto { Name = "A", Type = "folder", Children = new List<BookmarkNodeDto> { folderB } };
                var siblingD = new BookmarkNodeDto { Name = "D", Type = "folder", Children = new List<BookmarkNodeDto>() };
                var root = new List<BookmarkNodeDto> { folderA, siblingD };

                bool descCheck = BrowserShieldAndSettings.ContainsDescendantId(folderA, folderA.Id) &&
                                 BrowserShieldAndSettings.ContainsDescendantId(folderA, folderB.Id) &&
                                 BrowserShieldAndSettings.ContainsDescendantId(folderA, folderC.Id) &&
                                 !BrowserShieldAndSettings.ContainsDescendantId(folderA, siblingD.Id);

                bool blockIntoSelf = !BrowserShieldAndSettings.TryMoveNodeIntoFolder(root, folderA.Id, folderA.Id);
                bool blockIntoDescendant = !BrowserShieldAndSettings.TryMoveNodeIntoFolder(root, folderA.Id, folderC.Id);
                bool blockRelDescendantFolder = !BrowserShieldAndSettings.MoveNodeRelativeToTarget(root, folderA.Id, folderC.Id);
                bool blockRelDescendantItem = !BrowserShieldAndSettings.MoveNodeRelativeToTarget(root, folderA.Id, itemX.Id);

                bool validMove = BrowserShieldAndSettings.TryMoveNodeIntoFolder(root, siblingD.Id, folderC.Id);
                string json = JsonSerializer.Serialize(root);

                return descCheck && blockIntoSelf && blockIntoDescendant && blockRelDescendantFolder &&
                       blockRelDescendantItem && validMove && json.Contains("\"Name\":\"C\"", StringComparison.Ordinal);
            });

            // 21. Flat Bookmark Persistence Across SaveSettings + LoadSettings (MigratedChromeBookmarkTreeV5)
            AddTest("Flache Lesezeichen bleiben ohne Chrome-Overwrite erhalten (V5-Flag)", () =>
            {
                var flatSettings = new BrowserSettings
                {
                    MigratedOpenWebDefaultV2 = true,
                    MigratedChromeBookmarkTreeV3 = true,
                    MigratedChromeBookmarkTreeV4 = true,
                    MigratedChromeBookmarkTreeV5 = true,
                    BookmarkBarNodes = new List<BookmarkNodeDto>
                    {
                        new() { Name = "NurSeelenfunke", Type = "url", Url = "https://mein-seelenfunke.de" },
                        new() { Name = "", Type = "url", Url = "https://www.fyrst.de" }
                    }
                };
                BrowserShieldAndSettings.SaveSettings(flatSettings);

                var reloaded = BrowserShieldAndSettings.LoadSettings();
                return reloaded.MigratedChromeBookmarkTreeV5 &&
                       reloaded.BookmarkBarNodes.Count == 2 &&
                       reloaded.BookmarkBarNodes.All(n => !n.IsFolder) &&
                       reloaded.BookmarkBarNodes[0].Name == "NurSeelenfunke" &&
                       !reloaded.BookmarkBarNodes[1].HasVisibleName;
            });

            // 22. CSV Login Import Parser Edge Cases (Quoted Commas, Escaped Quotes, CRLF, HTTP Filtering)
            AddTest("CSV-Passwort-Import (Quotes, Kommas, \"\", CRLF & HTTP-Filterung)", () =>
            {
                string csvPath = Path.Combine(BrowserShieldAndSettings.DataDir, "edge_cases.csv");
                string csvContent =
                    "name,url,username,password,note\r\n" +
                    "\"Mein-Seelenfunke, Admin\",https://mein-seelenfunke.de/admin/login,\"alina,admin\",\"S3cr3t,\"\"Gold\"\"!\",\"Notiz, 1\"\r\n" +
                    "LocalDev,http://127.0.0.1:8080/auth,dev@local,LocalLoop!2026,\r\n" +
                    "InsecureHttp,http://insecure-site.example.com/login,baduser,ShouldNeverImport!,\r\n";

                File.WriteAllText(csvPath, csvContent, Encoding.UTF8);
                var testVault = new BrowserVaultData { ImportedFromChromeV1 = true };
                int imported = BrowserAutofillAndVaultEngine.ImportPasswordsFromCsvFile(testVault, csvPath);
                BrowserAutofillAndVaultEngine.SecureZeroDeleteFile(csvPath);

                var sf = testVault.Logins.FirstOrDefault(x => x.OriginUrl == "https://mein-seelenfunke.de");
                var loc = testVault.Logins.FirstOrDefault(x => x.OriginUrl == "http://127.0.0.1:8080");
                bool noHttpExternal = !testVault.Logins.Any(x => x.Host.Contains("insecure-site", StringComparison.OrdinalIgnoreCase));

                return imported == 2 &&
                       sf != null &&
                       sf.Username == "alina,admin" &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(sf.EncryptedPassword) == "S3cr3t,\"Gold\"!" &&
                       loc != null &&
                       BrowserAutofillAndVaultEngine.UnprotectSecret(loc.EncryptedPassword) == "LocalLoop!2026" &&
                       noHttpExternal;
            });

            // 23. Monogram & Globe Favicon Rendering (int.MinValue Hash Safety + Distinct Gold/Silver Cache)
            AddTest("Favicon-Engine (int.MinValue Hash-Sicherheit & Gold/Silber-Globus-Cache)", () =>
            {
                int safeMin = BrowserAutofillAndVaultEngine.MaskNonNegativeHash(int.MinValue);
                if (safeMin != 0) return false;

                var goldGlobe1 = BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon(null);
                var goldGlobe2 = BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon("");
                var silverGlobe1 = BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon("edge://settings");
                var silverGlobe2 = BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon("chrome://version");
                var mono = BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon("https://mein-seelenfunke.de");
                var folder = BrowserAutofillAndVaultEngine.GetFolderBadgeIcon();

                byte[] goldPixels = new byte[24 * 24 * 4];
                byte[] silverPixels = new byte[24 * 24 * 4];
                goldGlobe1.CopyPixels(goldPixels, 24 * 4, 0);
                silverGlobe1.CopyPixels(silverPixels, 24 * 4, 0);

                return goldGlobe1.IsFrozen &&
                       silverGlobe1.IsFrozen &&
                       mono.IsFrozen &&
                       folder.IsFrozen &&
                       ReferenceEquals(goldGlobe1, goldGlobe2) &&
                       ReferenceEquals(silverGlobe1, silverGlobe2) &&
                       !ReferenceEquals(goldGlobe1, silverGlobe1) &&
                       goldPixels.Any(b => b != 0) &&
                       silverPixels.Any(b => b != 0) &&
                       !goldPixels.SequenceEqual(silverPixels);
            });

            // 24. Headless XAML MainWindow Instantiation & Light/Dark Theme Smoke Check on STA Thread
            AddTest("Headless XAML MainWindow-Instanziierung & Light/Dark-Mode (STA)", () =>
            {
                var win = new MainWindow();
                try
                {
                    win.SetThemeForSelfTest(false);
                    var lightBg = (win.Resources["BgCanvasBrush"] as SolidColorBrush)?.Color;
                    win.SetThemeForSelfTest(true);
                    var darkBg = (win.Resources["BgCanvasBrush"] as SolidColorBrush)?.Color;

                    return win.Title == "Browser" &&
                           lightBg.HasValue &&
                           darkBg.HasValue &&
                           lightBg.Value != darkBg.Value;
                }
                finally
                {
                    win.Close();
                }
            });

            // 25. LoginSavePromptBar Confirmation & Overwrite Protection UI State Machine (BRW-SEC-02)
            AddTest("LoginSavePromptBar Bestätigungs- & Überschreibschutz-Flow (BRW-SEC-02)", () =>
            {
                var win = new MainWindow();
                try
                {
                    win.VaultForSelfTest.Logins.Clear();

                    // Step 1: New credential requires explicit user confirmation before entering vault
                    win.QueueLoginSaveConfirmationPrompt("https://mein-seelenfunke.de", "alina@mein-seelenfunke.de", "InitialSecret!1");
                    bool promptVisible1 = win.LoginSavePromptBar.Visibility == System.Windows.Visibility.Visible;
                    bool notSavedBeforeConfirm = win.VaultForSelfTest.Logins.Count == 0;
                    win.ConfirmLoginSavePromptForSelfTest();
                    bool collapsedAfterConfirm = win.LoginSavePromptBar.Visibility == System.Windows.Visibility.Collapsed;
                    bool savedAfterConfirm = win.VaultForSelfTest.Logins.Count == 1 &&
                        BrowserAutofillAndVaultEngine.UnprotectSecret(win.VaultForSelfTest.Logins[0].EncryptedPassword) == "InitialSecret!1";

                    // Step 2: Dismissing overwrite prompt preserves existing credential untouched
                    win.QueueLoginSaveConfirmationPrompt("https://mein-seelenfunke.de", "alina@mein-seelenfunke.de", "SpoofedOverwrite!2");
                    bool promptVisibleOverwrite = win.LoginSavePromptBar.Visibility == System.Windows.Visibility.Visible &&
                                                  win.LoginSavePromptTitleTxt.Text.Contains("überschreiben", StringComparison.OrdinalIgnoreCase);
                    win.DismissLoginSavePromptForSelfTest();
                    bool preservedOnDismiss = BrowserAutofillAndVaultEngine.UnprotectSecret(win.VaultForSelfTest.Logins[0].EncryptedPassword) == "InitialSecret!1";

                    // Step 3: Explicit confirm updates credential
                    win.QueueLoginSaveConfirmationPrompt("https://mein-seelenfunke.de", "alina@mein-seelenfunke.de", "ConfirmedUpdate!3");
                    win.ConfirmLoginSavePromptForSelfTest();
                    bool updatedOnConfirm = BrowserAutofillAndVaultEngine.UnprotectSecret(win.VaultForSelfTest.Logins[0].EncryptedPassword) == "ConfirmedUpdate!3";

                    return promptVisible1 && notSavedBeforeConfirm && collapsedAfterConfirm && savedAfterConfirm &&
                           promptVisibleOverwrite && preservedOnDismiss && updatedOnConfirm;
                }
                finally
                {
                    win.Close();
                }
            });

            int pass = 0;
            int total = tests.Count;

            try
            {
                using (BrowserShieldAndSettings.UseTemporaryDataDirScope(tempTestDir))
                {
                    for (int i = 0; i < tests.Count; i++)
                    {
                        var (name, fn) = tests[i];
                        bool ok = false;
                        string? errDetail = null;
                        try
                        {
                            ok = fn();
                        }
                        catch (Exception ex)
                        {
                            errDetail = $"{ex.GetType().Name}: {ex.Message}";
                        }

                        string line = ok
                            ? $"[PASS] {i + 1:00}/{total:00} {name}"
                            : (errDetail == null
                                ? $"[FAIL] {i + 1:00}/{total:00} {name}"
                                : $"[FAIL] {i + 1:00}/{total:00} {name} ({errDetail})");

                        Console.WriteLine(line);
                        logLines.Add(line);
                        if (ok) pass++;
                    }
                }
            }
            finally
            {
                try
                {
                    if (Directory.Exists(tempTestDir))
                        Directory.Delete(tempTestDir, recursive: true);
                }
                catch { }
            }

            string summary = $"FUNKENWERK BROWSER SELF-TEST: {pass}/{total} TESTS BESTANDEN.";
            Console.WriteLine(summary);
            logLines.Add(summary);

            if (!string.IsNullOrWhiteSpace(customReportPath))
            {
                try { File.WriteAllLines(customReportPath, logLines, Encoding.UTF8); } catch { }
            }

            return total >= 20 && pass == total;
        }
    }
}

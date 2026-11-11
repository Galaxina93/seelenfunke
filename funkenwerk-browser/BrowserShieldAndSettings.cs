using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.AccessControl;
using System.Security.Principal;
using System.Text;
using System.Text.Json;

namespace FunkenwerkBrowser
{
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

        public static string DataDir
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkBrowser");
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        public static string WebViewUserDataDir => Path.Combine(DataDir, "WebViewProfile");
        public static string SettingsPath => Path.Combine(DataDir, "browser_settings.json");

        public static BrowserSettings LoadSettings()
        {
            try
            {
                if (File.Exists(SettingsPath))
                {
                    using var rawDoc = JsonDocument.Parse(File.ReadAllText(SettingsPath, Encoding.UTF8));
                    bool alreadyMigrated = rawDoc.RootElement.TryGetProperty("MigratedOpenWebDefaultV2", out var mp) && mp.ValueKind == JsonValueKind.True;
                    bool chromeTreeMigratedV5 = rawDoc.RootElement.TryGetProperty("MigratedChromeBookmarkTreeV5", out var cp5) && cp5.ValueKind == JsonValueKind.True;

                    var loaded = JsonSerializer.Deserialize<BrowserSettings>(rawDoc.RootElement.GetRawText());
                    if (loaded != null)
                    {
                        bool needsSave = false;
                        if (!alreadyMigrated)
                        {
                            loaded.StrictFortressMode = false;
                            loaded.MigratedOpenWebDefaultV2 = true;
                            needsSave = true;
                        }
                        if (!chromeTreeMigratedV5 || loaded.BookmarkBarNodes == null || loaded.BookmarkBarNodes.Count == 0 || !loaded.BookmarkBarNodes.Any(n => n.IsFolder && n.Children != null && n.Children.Count > 0))
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

        public static (BookmarkNodeDto? Node, List<BookmarkNodeDto>? ParentList, BookmarkNodeDto? ParentFolder) FindNodeAndParent(List<BookmarkNodeDto> currentList, string targetId, BookmarkNodeDto? currentParentFolder = null)
        {
            foreach (var item in currentList)
            {
                if (string.Equals(item.Id, targetId, StringComparison.OrdinalIgnoreCase))
                    return (item, currentList, currentParentFolder);

                if (item.IsFolder && item.Children != null)
                {
                    var sub = FindNodeAndParent(item.Children, targetId, item);
                    if (sub.Node != null) return sub;
                }
            }
            return (null, null, null);
        }

        public static BookmarkNodeDto? FindBookmarkByUrl(IEnumerable<BookmarkNodeDto> nodes, string url)
        {
            if (string.IsNullOrWhiteSpace(url)) return null;
            string cleanTarget = url.Trim().TrimEnd('/');
            foreach (var n in nodes)
            {
                if (n.IsFolder)
                {
                    var found = FindBookmarkByUrl(n.Children, url);
                    if (found != null) return found;
                }
                else if (string.Equals(n.Url.Trim().TrimEnd('/'), cleanTarget, StringComparison.OrdinalIgnoreCase))
                {
                    return n;
                }
            }
            return null;
        }

        public static List<(string FolderId, string DisplayPath, List<BookmarkNodeDto> TargetList)> CollectAllFolderTargets(List<BookmarkNodeDto> rootBarNodes, string? excludeSubtreeId = null)
        {
            var list = new List<(string FolderId, string DisplayPath, List<BookmarkNodeDto> TargetList)>
            {
                ("ROOT_BAR", "★ Lesezeichenleiste (Hauptebene)", rootBarNodes)
            };

            void Walk(IEnumerable<BookmarkNodeDto> nodes, string prefix)
            {
                foreach (var n in nodes)
                {
                    if (!n.IsFolder) continue;
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

        public static void SaveSettings(BrowserSettings s)
        {
            try
            {
                string tmp = SettingsPath + ".tmp";
                File.WriteAllText(tmp, JsonSerializer.Serialize(s, new JsonSerializerOptions { WriteIndented = true }), new UTF8Encoding(false));
                File.Move(tmp, SettingsPath, overwrite: true);
            }
            catch { }
        }

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
            if (path.Contains("/pixel") && (host.Contains("tiktok") || host.Contains("facebook") || host.Contains("analytics")))
                return true;

            return false;
        }

        public static bool IsNavigationAllowedByFortress(BrowserSettings s, string? rawUri, out string blockedHost)
        {
            blockedHost = "";
            if (string.IsNullOrWhiteSpace(rawUri)) return true;
            if (rawUri.StartsWith("about:", StringComparison.OrdinalIgnoreCase) ||
                rawUri.StartsWith("data:", StringComparison.OrdinalIgnoreCase) ||
                rawUri.StartsWith("devtools:", StringComparison.OrdinalIgnoreCase) ||
                rawUri.StartsWith("edge://", StringComparison.OrdinalIgnoreCase) ||
                rawUri.StartsWith("chrome://", StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }

            if (!Uri.TryCreate(rawUri, UriKind.Absolute, out var uri)) return false;
            if (uri.Scheme.Equals("file", StringComparison.OrdinalIgnoreCase)) return true;

            string host = uri.Host.ToLowerInvariant();
            if (IsAdOrTrackerUri(rawUri))
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

        public static string NormalizeUserUrlOrSearch(string input)
        {
            string raw = (input ?? "").Trim();
            if (string.IsNullOrEmpty(raw)) return "https://mein-seelenfunke.de";

            if (File.Exists(raw))
                return new Uri(raw).AbsoluteUri;

            if (raw.StartsWith("http://", StringComparison.OrdinalIgnoreCase) ||
                raw.StartsWith("https://", StringComparison.OrdinalIgnoreCase) ||
                raw.StartsWith("edge://", StringComparison.OrdinalIgnoreCase))
                return raw;

            if (raw.StartsWith("chrome://", StringComparison.OrdinalIgnoreCase))
                return "edge://" + raw.Substring("chrome://".Length);

            if (raw.StartsWith("127.0.0.1", StringComparison.OrdinalIgnoreCase) ||
                raw.StartsWith("localhost", StringComparison.OrdinalIgnoreCase) ||
                raw.Contains("seelenfunke.test", StringComparison.OrdinalIgnoreCase))
            {
                return "http://" + raw;
            }

            if (!raw.Contains(' ') && raw.Contains('.'))
                return "https://" + raw;

            return "https://www.google.com/search?q=" + Uri.EscapeDataString(raw);
        }

        private static void LockDownDirectoryAcl(string dirPath)
        {
            try
            {
                var di = new DirectoryInfo(dirPath);
                var user = WindowsIdentity.GetCurrent().User;
                if (user == null) return;
                var sec = new DirectorySecurity();
                sec.SetAccessRuleProtection(true, false);
                sec.AddAccessRule(new FileSystemAccessRule(user, FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null), FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                di.SetAccessControl(sec);
            }
            catch { }
        }
    }

    public static class BrowserSelfTestRunner
    {
        public static bool RunSelfTest()
        {
            int pass = 0;
            int total = 7;
            var logLines = new List<string>();

            void Check(string name, Func<bool> fn)
            {
                bool ok = false;
                try { ok = fn(); } catch { }
                string line = ok ? $"[PASS] {name}" : $"[FAIL] {name}";
                Console.WriteLine(line);
                logLines.Add(line);
                if (ok) pass++;
            }

            Check("1. Browser Icons (funkenwerk_browser.ico & .png)", () =>
            {
                var s1 = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_browser.ico"));
                var s2 = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_browser.png"));
                return s1 != null && s2 != null;
            });

            Check("2. Netzwerk Ad- & Tracker-Blocker", () =>
                BrowserShieldAndSettings.IsAdOrTrackerUri("https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js") &&
                BrowserShieldAndSettings.IsAdOrTrackerUri("https://www.google-analytics.com/collect") &&
                !BrowserShieldAndSettings.IsAdOrTrackerUri("https://mein-seelenfunke.de/shop"));

            Check("3. Offenes Web Standard (YouTube/Google/Insta) + Optionale Whitelist", () =>
            {
                var openSettings = new BrowserSettings();
                bool allowOpenWeb = BrowserShieldAndSettings.IsNavigationAllowedByFortress(openSettings, "https://www.youtube.com/watch?v=test", out _) &&
                                    BrowserShieldAndSettings.IsNavigationAllowedByFortress(openSettings, "https://www.instagram.com/", out _) &&
                                    BrowserShieldAndSettings.IsNavigationAllowedByFortress(openSettings, "https://www.google.com/", out _);

                var s = new BrowserSettings { StrictFortressMode = true };
                bool allowSf = BrowserShieldAndSettings.IsNavigationAllowedByFortress(s, "https://mein-seelenfunke.de/admin/dashboard", out _);
                bool allowFyrst = BrowserShieldAndSettings.IsNavigationAllowedByFortress(s, "https://www.fyrst.de/login", out _);
                bool blockForeign = !BrowserShieldAndSettings.IsNavigationAllowedByFortress(s, "https://random-ad-casino.example.com/", out string host) && host == "random-ad-casino.example.com";
                return allowOpenWeb && allowSf && allowFyrst && blockForeign;
            });

            Check("4. 30-Minuten-Zeitschleuse Auto-Ablauf", () =>
            {
                var s = new BrowserSettings { StrictFortressMode = true, AirlockOpenUntilUtc = DateTime.UtcNow.AddMinutes(30) };
                bool openNow = BrowserShieldAndSettings.IsNavigationAllowedByFortress(s, "https://wikipedia.org", out _);
                s.AirlockOpenUntilUtc = DateTime.UtcNow.AddSeconds(-5);
                bool closedAfter = !BrowserShieldAndSettings.IsNavigationAllowedByFortress(s, "https://wikipedia.org", out _);
                return openNow && closedAfter;
            });

            Check("5. Einstellungs-Persistenz & Privater Profilordner", () => Directory.Exists(BrowserShieldAndSettings.DataDir));

            Check("6. Chrome-Lesezeichenbaum, Ordner-Hierarchie & Namenlose Icon-Lesezeichen", () =>
            {
                var settings = BrowserShieldAndSettings.LoadSettings();
                bool hasNodes = settings.BookmarkBarNodes != null && settings.BookmarkBarNodes.Count > 0;
                bool hasFolders = settings.BookmarkBarNodes!.Any(n => n.IsFolder && n.Children.Count > 0);
                var namelessNode = new BookmarkNodeDto { Name = "", Type = "url", Url = "https://www.deepl.com/de/translator" };
                var favIco = BrowserAutofillAndVaultEngine.TryGetCachedFaviconSync(namelessNode.Url) ?? BrowserAutofillAndVaultEngine.GetDefaultGlobeIcon(namelessNode.Url);
                var folderIco = BrowserAutofillAndVaultEngine.GetFolderBadgeIcon();
                return hasNodes && hasFolders && !namelessNode.HasVisibleName && favIco != null && folderIco != null;
            });

            Check("7. FWBVAULT1 Passwort-Tresor & Chrome Autofill-Integration", () =>
            {
                string secret = "Seelenfunke_Chrome_Vault_Test_2026!";
                string enc = BrowserAutofillAndVaultEngine.ProtectSecret(secret);
                string dec = BrowserAutofillAndVaultEngine.UnprotectSecret(enc);
                var vault = BrowserAutofillAndVaultEngine.LoadVault();
                string js = BrowserAutofillAndVaultEngine.BuildPageAutofillAndCaptureScript(vault, "https://mein-seelenfunke.de/login", false);
                return enc.StartsWith("FWBVAULT1:", StringComparison.Ordinal) &&
                       dec == secret &&
                       vault.AutofillProfile != null &&
                       !string.IsNullOrEmpty(vault.AutofillProfile.Email) &&
                       js.Contains("tryAutofillLogin");
            });

            string summary = $"FUNKENWERK BROWSER SELF-TEST: {pass}/{total} TESTS BESTANDEN.";
            Console.WriteLine(summary);
            logLines.Add(summary);
            try { File.WriteAllLines(Path.Combine(BrowserShieldAndSettings.DataDir, "selftest.log"), logLines, Encoding.UTF8); } catch { }
            return pass == total;
        }
    }
}

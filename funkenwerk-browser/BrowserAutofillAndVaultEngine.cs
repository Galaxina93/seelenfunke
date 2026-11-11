using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Media;
using System.Windows.Media.Imaging;

namespace FunkenwerkBrowser
{
    public sealed class BookmarkNodeDto
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string Name { get; set; } = "";
        public string Type { get; set; } = "url"; // "url" | "folder"
        public string Url { get; set; } = "";
        public List<BookmarkNodeDto> Children { get; set; } = new();

        public bool IsFolder => string.Equals(Type, "folder", StringComparison.OrdinalIgnoreCase);
        public bool HasVisibleName => !string.IsNullOrEmpty(Name);
    }

    public sealed class SavedLoginEntryDto
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string OriginUrl { get; set; } = "";
        public string Host { get; set; } = "";
        public string Username { get; set; } = "";
        public string EncryptedPassword { get; set; } = "";
        public DateTime UpdatedAtUtc { get; set; } = DateTime.UtcNow;

        public bool HasPassword => !string.IsNullOrEmpty(EncryptedPassword);
        public string StatusBadge => HasPassword ? "✓ Passwort gespeichert" : "Benutzername (Chrome)";
    }

    public sealed class AutofillProfileDto
    {
        public string FullName { get; set; } = "Alina Steinhauer";
        public string FirstName { get; set; } = "Alina";
        public string LastName { get; set; } = "Steinhauer";
        public string Company { get; set; } = "Mein-Seelenfunke";
        public string Street { get; set; } = "Carl-Goerdeler-Ring 26";
        public string Zip { get; set; } = "38518";
        public string City { get; set; } = "Gifhorn";
        public string Country { get; set; } = "Deutschland";
        public string Email { get; set; } = "kontakt@mein-seelenfunke.de";
        public string Phone { get; set; } = "+49 1590 1966864";
        public Dictionary<string, string> ExtraFieldValues { get; set; } = new(StringComparer.OrdinalIgnoreCase);
    }

    public sealed class BrowserVaultData
    {
        public bool ImportedFromChromeV1 { get; set; }
        public AutofillProfileDto AutofillProfile { get; set; } = new();
        public List<SavedLoginEntryDto> Logins { get; set; } = new();
    }

    public static class BrowserAutofillAndVaultEngine
    {
        private static readonly byte[] VaultEntropy = Encoding.UTF8.GetBytes("FunkenwerkBrowser::FWBVAULT1::Seelenfunke::2026");
        private static readonly ConcurrentDictionary<string, BitmapSource> MemoryIconCache = new(StringComparer.OrdinalIgnoreCase);
        private static BitmapSource? _folderBadgeIcon;
        private static BitmapSource? _defaultGlobeIcon;

        private static readonly HttpClient FaviconHttp = new(new SocketsHttpHandler
        {
            ConnectTimeout = TimeSpan.FromSeconds(4),
            AllowAutoRedirect = true,
            AutomaticDecompression = System.Net.DecompressionMethods.All
        })
        {
            Timeout = TimeSpan.FromSeconds(5)
        };

        public static string FaviconCacheDir
        {
            get
            {
                string dir = Path.Combine(BrowserShieldAndSettings.DataDir, "FaviconCache");
                if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
                return dir;
            }
        }

        public static string VaultFilePath => Path.Combine(BrowserShieldAndSettings.DataDir, "credentials_vault.json");

        // =====================================================================
        // 1. DPAPI + AES-256-GCM PASSWORD VAULT (FWBVAULT1)
        // =====================================================================
        public static string ProtectSecret(string plainText)
        {
            if (string.IsNullOrEmpty(plainText)) return "";
            try
            {
                byte[] plainBytes = Encoding.UTF8.GetBytes(plainText);
                byte[] aesKey = RandomNumberGenerator.GetBytes(32);
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] cipher = new byte[plainBytes.Length];
                byte[] tag = new byte[16];

                using (var aes = new AesGcm(aesKey, 16))
                {
                    aes.Encrypt(nonce, plainBytes, cipher, tag, VaultEntropy);
                }

                byte[] wrappedKey = ProtectedData.Protect(aesKey, VaultEntropy, DataProtectionScope.CurrentUser);
                CryptographicOperations.ZeroMemory(aesKey);

                using var ms = new MemoryStream();
                using var bw = new BinaryWriter(ms);
                bw.Write(wrappedKey.Length);
                bw.Write(wrappedKey);
                bw.Write(nonce);
                bw.Write(tag);
                bw.Write(cipher.Length);
                bw.Write(cipher);
                return "FWBVAULT1:" + Convert.ToBase64String(ms.ToArray());
            }
            catch
            {
                return "";
            }
        }

        public static string UnprotectSecret(string protectedToken)
        {
            if (string.IsNullOrWhiteSpace(protectedToken)) return "";
            try
            {
                if (!protectedToken.StartsWith("FWBVAULT1:", StringComparison.Ordinal))
                    return "";

                byte[] raw = Convert.FromBase64String(protectedToken.Substring("FWBVAULT1:".Length));
                using var ms = new MemoryStream(raw);
                using var br = new BinaryReader(ms);
                int keyLen = br.ReadInt32();
                byte[] wrappedKey = br.ReadBytes(keyLen);
                byte[] nonce = br.ReadBytes(12);
                byte[] tag = br.ReadBytes(16);
                int cipherLen = br.ReadInt32();
                byte[] cipher = br.ReadBytes(cipherLen);

                byte[] aesKey = ProtectedData.Unprotect(wrappedKey, VaultEntropy, DataProtectionScope.CurrentUser);
                byte[] plain = new byte[cipherLen];
                using (var aes = new AesGcm(aesKey, 16))
                {
                    aes.Decrypt(nonce, cipher, tag, plain, VaultEntropy);
                }
                CryptographicOperations.ZeroMemory(aesKey);
                return Encoding.UTF8.GetString(plain);
            }
            catch
            {
                return "";
            }
        }

        public static BrowserVaultData LoadVault()
        {
            try
            {
                if (File.Exists(VaultFilePath))
                {
                    var loaded = JsonSerializer.Deserialize<BrowserVaultData>(File.ReadAllText(VaultFilePath, Encoding.UTF8));
                    if (loaded != null)
                    {
                        loaded.AutofillProfile ??= new AutofillProfileDto();
                        loaded.Logins ??= new List<SavedLoginEntryDto>();
                        if (!loaded.ImportedFromChromeV1)
                        {
                            ImportChromeAutofillAndLogins(loaded);
                            loaded.ImportedFromChromeV1 = true;
                            SaveVault(loaded);
                        }
                        return loaded;
                    }
                }
            }
            catch { }

            var fresh = new BrowserVaultData { ImportedFromChromeV1 = true };
            ImportChromeAutofillAndLogins(fresh);
            SaveVault(fresh);
            return fresh;
        }

        public static void SaveVault(BrowserVaultData vault)
        {
            try
            {
                string tmp = VaultFilePath + ".tmp";
                File.WriteAllText(tmp, JsonSerializer.Serialize(vault, new JsonSerializerOptions { WriteIndented = true }), new UTF8Encoding(false));
                File.Move(tmp, VaultFilePath, overwrite: true);
            }
            catch { }
        }

        public static void UpsertLoginCredential(BrowserVaultData vault, string urlOrOrigin, string username, string plainPassword)
        {
            string host = ExtractHost(urlOrOrigin);
            if (string.IsNullOrWhiteSpace(host) || string.IsNullOrWhiteSpace(username)) return;

            string cleanUser = username.Trim();
            var existing = vault.Logins.FirstOrDefault(x =>
                string.Equals(x.Host, host, StringComparison.OrdinalIgnoreCase) &&
                string.Equals(x.Username, cleanUser, StringComparison.OrdinalIgnoreCase));

            if (existing == null)
            {
                existing = new SavedLoginEntryDto
                {
                    OriginUrl = string.IsNullOrWhiteSpace(urlOrOrigin) ? $"https://{host}" : urlOrOrigin,
                    Host = host,
                    Username = cleanUser
                };
                vault.Logins.Insert(0, existing);
            }

            if (!string.IsNullOrEmpty(plainPassword))
                existing.EncryptedPassword = ProtectSecret(plainPassword);

            existing.UpdatedAtUtc = DateTime.UtcNow;
            SaveVault(vault);
        }

        public static int ImportPasswordsFromCsvFile(BrowserVaultData vault, string csvFilePath)
        {
            if (!File.Exists(csvFilePath)) return 0;
            int count = 0;
            try
            {
                string[] lines = File.ReadAllLines(csvFilePath, Encoding.UTF8);
                if (lines.Length <= 1) return 0;

                // Chrome CSV header: name,url,username,password,note
                string[] headers = SplitCsvLine(lines[0]).Select(h => h.Trim().ToLowerInvariant()).ToArray();
                int idxUrl = Array.FindIndex(headers, h => h is "url" or "origin" or "website" or "login_uri");
                int idxUser = Array.FindIndex(headers, h => h is "username" or "user" or "benutzername" or "login_username");
                int idxPass = Array.FindIndex(headers, h => h is "password" or "passwort" or "login_password");

                if (idxUrl < 0) idxUrl = 1;
                if (idxUser < 0) idxUser = 2;
                if (idxPass < 0) idxPass = 3;

                for (int i = 1; i < lines.Length; i++)
                {
                    if (string.IsNullOrWhiteSpace(lines[i])) continue;
                    var cols = SplitCsvLine(lines[i]);
                    if (cols.Count <= Math.Max(idxUrl, Math.Max(idxUser, idxPass))) continue;

                    string url = cols[idxUrl].Trim();
                    string user = cols[idxUser].Trim();
                    string pass = cols[idxPass];
                    if (string.IsNullOrEmpty(url) || (string.IsNullOrEmpty(user) && string.IsNullOrEmpty(pass))) continue;

                    UpsertLoginCredential(vault, url, user, pass);
                    count++;
                }
            }
            catch { }
            return count;
        }

        private static List<string> SplitCsvLine(string line)
        {
            var result = new List<string>();
            var sb = new StringBuilder();
            bool inQuotes = false;
            for (int i = 0; i < line.Length; i++)
            {
                char c = line[i];
                if (c == '"')
                {
                    if (inQuotes && i + 1 < line.Length && line[i + 1] == '"')
                    {
                        sb.Append('"');
                        i++;
                    }
                    else
                    {
                        inQuotes = !inQuotes;
                    }
                }
                else if (c == ',' && !inQuotes)
                {
                    result.Add(sb.ToString());
                    sb.Clear();
                }
                else
                {
                    sb.Append(c);
                }
            }
            result.Add(sb.ToString());
            return result;
        }

        // =====================================================================
        // 2. CHROME BOOKMARKS, FAVICONS, AUTOFILL & LOGINS IMPORTER
        // =====================================================================
        public static string ChromeUserDataDir =>
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Google", "Chrome", "User Data");

        public static string GetActiveChromeProfileName()
        {
            try
            {
                if (Directory.Exists(ChromeUserDataDir))
                {
                    var latestBookmarkFile = Directory.GetDirectories(ChromeUserDataDir)
                        .Select(dir => new FileInfo(Path.Combine(dir, "Bookmarks")))
                        .Where(fi => fi.Exists && fi.Length > 20)
                        .OrderByDescending(fi => fi.LastWriteTimeUtc)
                        .FirstOrDefault();

                    if (latestBookmarkFile?.Directory != null)
                        return latestBookmarkFile.Directory.Name;
                }
            }
            catch { }

            string p1 = Path.Combine(ChromeUserDataDir, "Profile 1", "Bookmarks");
            if (File.Exists(p1)) return "Profile 1";
            return "Default";
        }

        private static string ReadAllTextShared(string path)
        {
            using var fs = new FileStream(path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
            using var sr = new StreamReader(fs, Encoding.UTF8);
            return sr.ReadToEnd();
        }

        private static bool CopyFileSharedReadWrite(string sourcePath, string destPath)
        {
            try
            {
                using var src = new FileStream(sourcePath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
                using var dst = new FileStream(destPath, FileMode.Create, FileAccess.Write, FileShare.None);
                src.CopyTo(dst);
                return true;
            }
            catch
            {
                return false;
            }
        }

        public static List<BookmarkNodeDto> ImportChromeBookmarkTree()
        {
            var barNodes = new List<BookmarkNodeDto>();
            try
            {
                string activeProfile = GetActiveChromeProfileName();
                string activeBookmarksPath = Path.Combine(ChromeUserDataDir, activeProfile, "Bookmarks");

                if (File.Exists(activeBookmarksPath))
                {
                    barNodes.AddRange(ParseChromeBookmarksFile(activeBookmarksPath));
                }

                // Extract cached favicons from Chrome's Favicons SQLite DBs right away (works even while Chrome is open)
                ExtractFaviconsFromChromeDatabases(barNodes);
            }
            catch { }

            if (barNodes.Count == 0)
            {
                barNodes.Add(new BookmarkNodeDto { Name = "Mein-Seelenfunke", Type = "url", Url = "https://mein-seelenfunke.de" });
                barNodes.Add(new BookmarkNodeDto { Name = "Admin", Type = "url", Url = "https://mein-seelenfunke.de/admin/dashboard" });
                barNodes.Add(new BookmarkNodeDto { Name = "Google", Type = "url", Url = "https://www.google.com" });
                barNodes.Add(new BookmarkNodeDto { Name = "YouTube", Type = "url", Url = "https://www.youtube.com" });
                barNodes.Add(new BookmarkNodeDto { Name = "", Type = "url", Url = "https://www.deepl.com/translator" });
                barNodes.Add(new BookmarkNodeDto { Name = "", Type = "url", Url = "https://www.fyrst.de" });
            }

            if (!barNodes.Any(n => n.IsFolder && n.Children != null && n.Children.Count > 0))
            {
                barNodes.Add(new BookmarkNodeDto
                {
                    Name = "Manufaktur & Lieferanten",
                    Type = "folder",
                    Children = new List<BookmarkNodeDto>
                    {
                        new() { Name = "Glücksfieber", Type = "url", Url = "https://www.gluecksfieber.de" },
                        new() { Name = "PrintEquipment", Type = "url", Url = "https://www.printequipment.de" },
                        new() { Name = "Westpack", Type = "url", Url = "https://www.westpack.com" },
                        new() { Name = "Karton.eu", Type = "url", Url = "https://www.karton.eu" },
                        new() { Name = "DHL Geschäftskunden", Type = "url", Url = "https://geschaeftskunden.dhl.de" }
                    }
                });
            }

            return barNodes;
        }

        private static List<BookmarkNodeDto> ParseChromeBookmarksFile(string filePath)
        {
            var result = new List<BookmarkNodeDto>();
            try
            {
                using var doc = JsonDocument.Parse(ReadAllTextShared(filePath));
                if (!doc.RootElement.TryGetProperty("roots", out var roots)) return result;

                if (roots.TryGetProperty("bookmark_bar", out var bar) &&
                    bar.TryGetProperty("children", out var children) &&
                    children.ValueKind == JsonValueKind.Array)
                {
                    foreach (var child in children.EnumerateArray())
                    {
                        var node = ParseChromeNodeRecursive(child);
                        if (node != null) result.Add(node);
                    }
                }
            }
            catch { }
            return result;
        }

        private static BookmarkNodeDto? ParseChromeNodeRecursive(JsonElement el)
        {
            string type = el.TryGetProperty("type", out var tp) ? (tp.GetString() ?? "url") : "url";
            string name = el.TryGetProperty("name", out var np) ? (np.GetString() ?? "") : "";

            if (string.Equals(type, "folder", StringComparison.OrdinalIgnoreCase))
            {
                var folder = new BookmarkNodeDto
                {
                    Name = name,
                    Type = "folder"
                };
                if (el.TryGetProperty("children", out var ch) && ch.ValueKind == JsonValueKind.Array)
                {
                    foreach (var sub in ch.EnumerateArray())
                    {
                        var childNode = ParseChromeNodeRecursive(sub);
                        if (childNode != null) folder.Children.Add(childNode);
                    }
                }
                return folder;
            }
            else
            {
                string url = el.TryGetProperty("url", out var up) ? (up.GetString() ?? "") : "";
                if (string.IsNullOrWhiteSpace(url)) return null;
                return new BookmarkNodeDto
                {
                    Name = name, // Preserves "" for icon-only bookmarks!
                    Type = "url",
                    Url = url
                };
            }
        }

        public static void ImportChromeAutofillAndLogins(BrowserVaultData vault)
        {
            try
            {
                byte[]? chromeAesKey = TryReadChromeLocalStateKey();

                foreach (string profile in new[] { "Default", "Profile 1" })
                {
                    string profDir = Path.Combine(ChromeUserDataDir, profile);
                    if (!Directory.Exists(profDir)) continue;

                    // 1. Import Web Data (Autofill + Addresses)
                    string webDataPath = Path.Combine(profDir, "Web Data");
                    if (File.Exists(webDataPath))
                    {
                        ImportChromeWebDataSqlite(webDataPath, vault.AutofillProfile);
                    }

                    // 2. Import Login Data (Saved Login Origins, Usernames, and v10/DPAPI passwords if available)
                    string loginDataPath = Path.Combine(profDir, "Login Data");
                    if (File.Exists(loginDataPath))
                    {
                        ImportChromeLoginDataSqlite(loginDataPath, vault, chromeAesKey);
                    }
                }
            }
            catch { }
        }

        private static byte[]? TryReadChromeLocalStateKey()
        {
            try
            {
                string localStatePath = Path.Combine(ChromeUserDataDir, "Local State");
                if (!File.Exists(localStatePath)) return null;
                using var doc = JsonDocument.Parse(File.ReadAllText(localStatePath, Encoding.UTF8));
                if (doc.RootElement.TryGetProperty("os_crypt", out var osc) &&
                    osc.TryGetProperty("encrypted_key", out var ek))
                {
                    string? b64 = ek.GetString();
                    if (string.IsNullOrWhiteSpace(b64)) return null;
                    byte[] raw = Convert.FromBase64String(b64);
                    if (raw.Length > 5 && Encoding.ASCII.GetString(raw, 0, 5) == "DPAPI")
                    {
                        byte[] dpapiBlob = raw.Skip(5).ToArray();
                        return ProtectedData.Unprotect(dpapiBlob, null, DataProtectionScope.CurrentUser);
                    }
                }
            }
            catch { }
            return null;
        }

        private static void ImportChromeWebDataSqlite(string webDataPath, AutofillProfileDto profile)
        {
            string tmp = Path.Combine(Path.GetTempPath(), "fwb_wd_" + Guid.NewGuid().ToString("N") + ".db");
            try
            {
                if (!CopyFileSharedReadWrite(webDataPath, tmp)) return;
                if (WinSqliteNative.Open(tmp, out IntPtr db))
                {
                    try
                    {
                        // Read autofill table (name, value)
                        if (WinSqliteNative.Prepare(db, "SELECT name, value FROM autofill ORDER BY count DESC LIMIT 350;", out IntPtr stmt))
                        {
                            try
                            {
                                while (WinSqliteNative.Step(stmt))
                                {
                                    string fieldName = WinSqliteNative.ColumnText(stmt, 0).Trim();
                                    string fieldVal = WinSqliteNative.ColumnText(stmt, 1).Trim();
                                    if (!string.IsNullOrEmpty(fieldName) && !string.IsNullOrEmpty(fieldVal) && !profile.ExtraFieldValues.ContainsKey(fieldName))
                                    {
                                        profile.ExtraFieldValues[fieldName] = fieldVal;
                                    }
                                }
                            }
                            finally
                            {
                                WinSqliteNative.FinalizeStmt(stmt);
                            }
                        }

                        // Read address_type_tokens (type, value)
                        if (WinSqliteNative.Prepare(db, "SELECT type, value FROM address_type_tokens WHERE value != '';", out IntPtr addrStmt))
                        {
                            try
                            {
                                while (WinSqliteNative.Step(addrStmt))
                                {
                                    int type = WinSqliteNative.ColumnInt(addrStmt, 0);
                                    string val = WinSqliteNative.ColumnText(addrStmt, 1).Trim();
                                    if (string.IsNullOrEmpty(val)) continue;

                                    switch (type)
                                    {
                                        case 3 when string.IsNullOrEmpty(profile.FirstName): profile.FirstName = val; break;
                                        case 5 when string.IsNullOrEmpty(profile.LastName): profile.LastName = val; break;
                                        case 7 when string.IsNullOrEmpty(profile.FullName): profile.FullName = val; break;
                                        case 9 when string.IsNullOrEmpty(profile.Email): profile.Email = val; break;
                                        case 14 when string.IsNullOrEmpty(profile.Phone): profile.Phone = val; break;
                                        case 32 when string.IsNullOrEmpty(profile.Street): profile.Street = val; break;
                                        case 33 when string.IsNullOrEmpty(profile.City): profile.City = val; break;
                                        case 35 when string.IsNullOrEmpty(profile.Zip): profile.Zip = val; break;
                                        case 60 when string.IsNullOrEmpty(profile.Company): profile.Company = val; break;
                                    }
                                }
                            }
                            finally
                            {
                                WinSqliteNative.FinalizeStmt(addrStmt);
                            }
                        }
                    }
                    finally
                    {
                        WinSqliteNative.Close(db);
                    }
                }
            }
            catch { }
            finally
            {
                try { File.Delete(tmp); } catch { }
            }
        }

        private static void ImportChromeLoginDataSqlite(string loginDataPath, BrowserVaultData vault, byte[]? chromeAesKey)
        {
            string tmp = Path.Combine(Path.GetTempPath(), "fwb_ld_" + Guid.NewGuid().ToString("N") + ".db");
            try
            {
                if (!CopyFileSharedReadWrite(loginDataPath, tmp)) return;
                if (WinSqliteNative.Open(tmp, out IntPtr db))
                {
                    try
                    {
                        const string sql = "SELECT origin_url, username_value, password_value FROM logins WHERE blacklisted_by_user = 0 ORDER BY date_last_used DESC;";
                        if (WinSqliteNative.Prepare(db, sql, out IntPtr stmt))
                        {
                            try
                            {
                                while (WinSqliteNative.Step(stmt))
                                {
                                    string originUrl = WinSqliteNative.ColumnText(stmt, 0).Trim();
                                    string username = WinSqliteNative.ColumnText(stmt, 1).Trim();
                                    byte[]? pwdBlob = WinSqliteNative.ColumnBlob(stmt, 2);

                                    string host = ExtractHost(originUrl);
                                    if (string.IsNullOrEmpty(host) || string.IsNullOrEmpty(username)) continue;

                                    string decryptedPwd = TryDecryptChromePasswordBlob(pwdBlob, chromeAesKey);

                                    var existing = vault.Logins.FirstOrDefault(x =>
                                        string.Equals(x.Host, host, StringComparison.OrdinalIgnoreCase) &&
                                        string.Equals(x.Username, username, StringComparison.OrdinalIgnoreCase));

                                    if (existing == null)
                                    {
                                        vault.Logins.Add(new SavedLoginEntryDto
                                        {
                                            OriginUrl = originUrl,
                                            Host = host,
                                            Username = username,
                                            EncryptedPassword = !string.IsNullOrEmpty(decryptedPwd) ? ProtectSecret(decryptedPwd) : ""
                                        });
                                    }
                                    else if (!existing.HasPassword && !string.IsNullOrEmpty(decryptedPwd))
                                    {
                                        existing.EncryptedPassword = ProtectSecret(decryptedPwd);
                                    }
                                }
                            }
                            finally
                            {
                                WinSqliteNative.FinalizeStmt(stmt);
                            }
                        }
                    }
                    finally
                    {
                        WinSqliteNative.Close(db);
                    }
                }
            }
            catch { }
            finally
            {
                try { File.Delete(tmp); } catch { }
            }
        }

        private static string TryDecryptChromePasswordBlob(byte[]? blob, byte[]? aesKey)
        {
            if (blob == null || blob.Length < 4) return "";
            try
            {
                string prefix = Encoding.ASCII.GetString(blob, 0, 3);
                if (prefix == "v10" && aesKey != null && blob.Length > 31)
                {
                    byte[] nonce = blob.Skip(3).Take(12).ToArray();
                    byte[] tag = blob.Skip(blob.Length - 16).ToArray();
                    int cipherLen = blob.Length - 3 - 12 - 16;
                    byte[] cipher = blob.Skip(15).Take(cipherLen).ToArray();
                    byte[] plain = new byte[cipherLen];
                    using var aes = new AesGcm(aesKey, 16);
                    aes.Decrypt(nonce, cipher, tag, plain);
                    return Encoding.UTF8.GetString(plain);
                }
                if (prefix != "v10" && prefix != "v20")
                {
                    byte[] plain = ProtectedData.Unprotect(blob, null, DataProtectionScope.CurrentUser);
                    return Encoding.UTF8.GetString(plain);
                }
            }
            catch { }
            return "";
        }

        // =====================================================================
        // 3. WEBSITE FAVICON ENGINE (CHROME DB + LOCAL CACHE + LIVE FETCH)
        // =====================================================================
        public static void ExtractFaviconsFromChromeDatabases(IEnumerable<BookmarkNodeDto> rootNodes)
        {
            var urls = new List<string>();
            CollectAllUrlsRecursive(rootNodes, urls);
            if (urls.Count == 0) return;

            foreach (string profile in new[] { "Profile 1", "Default" })
            {
                string favDbPath = Path.Combine(ChromeUserDataDir, profile, "Favicons");
                if (!File.Exists(favDbPath)) continue;

                string tmp = Path.Combine(Path.GetTempPath(), "fwb_fav_" + Guid.NewGuid().ToString("N") + ".db");
                try
                {
                    if (!CopyFileSharedReadWrite(favDbPath, tmp)) continue;
                    if (WinSqliteNative.Open(tmp, out IntPtr db))
                    {
                        try
                        {
                            foreach (string url in urls)
                            {
                                string host = ExtractHost(url);
                                if (string.IsNullOrEmpty(host)) continue;
                                string cacheFile = GetHostFaviconCachePath(host);
                                if (File.Exists(cacheFile)) continue;

                                string safeHost = host.Replace("'", "");
                                string sql = $"SELECT fb.image_data FROM icon_mapping im JOIN favicon_bitmaps fb ON im.icon_id = fb.icon_id WHERE im.page_url LIKE '%{safeHost}%' ORDER BY fb.width DESC LIMIT 1;";
                                if (WinSqliteNative.Prepare(db, sql, out IntPtr stmt))
                                {
                                    try
                                    {
                                        if (WinSqliteNative.Step(stmt))
                                        {
                                            byte[]? imgBytes = WinSqliteNative.ColumnBlob(stmt, 0);
                                            if (imgBytes != null && imgBytes.Length > 64)
                                            {
                                                File.WriteAllBytes(cacheFile, imgBytes);
                                            }
                                        }
                                    }
                                    finally
                                    {
                                        WinSqliteNative.FinalizeStmt(stmt);
                                    }
                                }
                            }
                        }
                        finally
                        {
                            WinSqliteNative.Close(db);
                        }
                    }
                }
                catch { }
                finally
                {
                    try { File.Delete(tmp); } catch { }
                }
            }
        }

        private static void CollectAllUrlsRecursive(IEnumerable<BookmarkNodeDto> nodes, List<string> target)
        {
            foreach (var n in nodes)
            {
                if (n.IsFolder)
                    CollectAllUrlsRecursive(n.Children, target);
                else if (!string.IsNullOrWhiteSpace(n.Url))
                    target.Add(n.Url);
            }
        }

        public static string ExtractHost(string? rawUrl)
        {
            if (string.IsNullOrWhiteSpace(rawUrl)) return "";
            if (Uri.TryCreate(rawUrl.Trim(), UriKind.Absolute, out var uri) && !string.IsNullOrEmpty(uri.Host))
                return uri.Host.ToLowerInvariant();
            if (Uri.TryCreate("https://" + rawUrl.Trim(), UriKind.Absolute, out var uri2) && !string.IsNullOrEmpty(uri2.Host))
                return uri2.Host.ToLowerInvariant();
            return "";
        }

        public static string GetHostFaviconCachePath(string host)
        {
            string clean = string.Concat(host.ToLowerInvariant().Select(c => char.IsLetterOrDigit(c) || c == '.' || c == '-' ? c : '_'));
            if (string.IsNullOrEmpty(clean)) clean = "default";
            return Path.Combine(FaviconCacheDir, clean + ".png");
        }

        public static BitmapSource GetFolderBadgeIcon()
        {
            if (_folderBadgeIcon != null) return _folderBadgeIcon;
            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                // Chrome-authentic clean outline folder icon (matches Screenshot 1)
                var pen = new Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(196, 199, 197)), 1.75)
                {
                    StartLineCap = PenLineCap.Round,
                    EndLineCap = PenLineCap.Round,
                    LineJoin = PenLineJoin.Round
                };
                var geom = Geometry.Parse("M 2,5.5 L 9.2,5.5 L 11.4,8 L 22,8 C 22.6,8 23,8.4 23,9 L 23,19 C 23,19.6 22.6,20 22,20 L 2,20 C 1.4,20 1,19.6 1,19 L 1,6.5 C 1,5.9 1.4,5.5 2,5.5 Z");
                dc.DrawGeometry(Brushes.Transparent, pen, geom);
            }
            var rtb = new RenderTargetBitmap(24, 24, 96, 96, PixelFormats.Pbgra32);
            rtb.Render(dv);
            rtb.Freeze();
            _folderBadgeIcon = rtb;
            return rtb;
        }

        public static BitmapSource GetDefaultGlobeIcon(string? url = null)
        {
            if (!string.IsNullOrWhiteSpace(url) &&
                (url.StartsWith("chrome://", StringComparison.OrdinalIgnoreCase) ||
                 url.StartsWith("edge://", StringComparison.OrdinalIgnoreCase)))
            {
                if (_defaultGlobeIcon != null) return _defaultGlobeIcon;
                var dvChrome = new DrawingVisual();
                using (var dc = dvChrome.RenderOpen())
                {
                    var pen = new Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(196, 199, 197)), 1.75);
                    dc.DrawEllipse(null, pen, new Point(12, 12), 9, 9);
                    dc.DrawLine(pen, new Point(3, 12), new Point(21, 12));
                    dc.DrawEllipse(null, pen, new Point(12, 12), 4.5, 9);
                }
                var rtbChrome = new RenderTargetBitmap(24, 24, 96, 96, PixelFormats.Pbgra32);
                rtbChrome.Render(dvChrome);
                rtbChrome.Freeze();
                _defaultGlobeIcon = rtbChrome;
                return rtbChrome;
            }

            string host = ExtractHost(url);
            if (!string.IsNullOrEmpty(host))
            {
                string key = "monogram:" + host;
                if (MemoryIconCache.TryGetValue(key, out var cachedMono)) return cachedMono;

                string cleanHost = host.StartsWith("www.", StringComparison.OrdinalIgnoreCase) ? host.Substring(4) : host;
                char letter = char.ToUpperInvariant(cleanHost.FirstOrDefault(char.IsLetterOrDigit));
                if (letter == '\0') letter = 'W';

                // Deterministic noble color per host
                int hash = Math.Abs(cleanHost.GetHashCode());
                var palette = new[]
                {
                    System.Windows.Media.Color.FromRgb(59, 130, 246),
                    System.Windows.Media.Color.FromRgb(197, 160, 89),
                    System.Windows.Media.Color.FromRgb(16, 185, 129),
                    System.Windows.Media.Color.FromRgb(239, 68, 68),
                    System.Windows.Media.Color.FromRgb(168, 85, 247),
                    System.Windows.Media.Color.FromRgb(245, 158, 11)
                };
                var baseCol = palette[hash % palette.Length];

                var dv = new DrawingVisual();
                using (var dc = dv.RenderOpen())
                {
                    dc.DrawRoundedRectangle(new SolidColorBrush(baseCol), null, new Rect(1, 1, 22, 22), 6, 6);
                    var ft = new FormattedText(
                        letter.ToString(),
                        System.Globalization.CultureInfo.InvariantCulture,
                        FlowDirection.LeftToRight,
                        new Typeface(new FontFamily("Segoe UI"), FontStyles.Normal, FontWeights.Bold, FontStretches.Normal),
                        13.5,
                        Brushes.White,
                        1.0);
                    dc.DrawText(ft, new Point((24 - ft.Width) * 0.5, (24 - ft.Height) * 0.5 - 0.5));
                }
                var rtb = new RenderTargetBitmap(24, 24, 96, 96, PixelFormats.Pbgra32);
                rtb.Render(dv);
                rtb.Freeze();
                MemoryIconCache[key] = rtb;
                return rtb;
            }

            if (_defaultGlobeIcon != null) return _defaultGlobeIcon;
            var dvG = new DrawingVisual();
            using (var dc = dvG.RenderOpen())
            {
                var pen = new Pen(new SolidColorBrush(System.Windows.Media.Color.FromRgb(197, 160, 89)), 1.8);
                dc.DrawEllipse(null, pen, new Point(12, 12), 9, 9);
                dc.DrawLine(pen, new Point(3, 12), new Point(21, 12));
                dc.DrawEllipse(null, pen, new Point(12, 12), 4.5, 9);
            }
            var rtbG = new RenderTargetBitmap(24, 24, 96, 96, PixelFormats.Pbgra32);
            rtbG.Render(dvG);
            rtbG.Freeze();
            _defaultGlobeIcon = rtbG;
            return rtbG;
        }

        public static BitmapSource? TryGetCachedFaviconSync(string url)
        {
            string host = ExtractHost(url);
            if (string.IsNullOrEmpty(host)) return null;

            if (MemoryIconCache.TryGetValue(host, out var mem))
                return mem;

            string file = GetHostFaviconCachePath(host);
            if (File.Exists(file))
            {
                try
                {
                    byte[] bytes = File.ReadAllBytes(file);
                    var bmp = DecodeBitmapBytesFrozen(bytes);
                    if (bmp != null)
                    {
                        MemoryIconCache[host] = bmp;
                        return bmp;
                    }
                }
                catch { }
            }
            return null;
        }

        public static async Task<BitmapSource?> GetOrFetchFaviconAsync(string url)
        {
            var sync = TryGetCachedFaviconSync(url);
            if (sync != null) return sync;

            string host = ExtractHost(url);
            if (string.IsNullOrEmpty(host)) return null;

            // Avoid network calls for private LAN IPs if offline
            string[] candidates = host is "127.0.0.1" or "localhost" || host.StartsWith("192.168.")
                ? new[] { $"http://{host}/favicon.ico" }
                : new[]
                {
                    $"https://www.google.com/s2/favicons?domain={Uri.EscapeDataString(host)}&sz=64",
                    $"https://{host}/favicon.ico"
                };

            foreach (string endpoint in candidates)
            {
                try
                {
                    byte[] bytes = await FaviconHttp.GetByteArrayAsync(endpoint);
                    if (bytes != null && bytes.Length > 64)
                    {
                        var decoded = DecodeBitmapBytesFrozen(bytes);
                        if (decoded != null)
                        {
                            try { File.WriteAllBytes(GetHostFaviconCachePath(host), bytes); } catch { }
                            MemoryIconCache[host] = decoded;
                            return decoded;
                        }
                    }
                }
                catch { }
            }

            return null;
        }

        public static void StoreFaviconStreamForUrl(string url, Stream iconStream)
        {
            string host = ExtractHost(url);
            if (string.IsNullOrEmpty(host)) return;
            try
            {
                using var ms = new MemoryStream();
                iconStream.CopyTo(ms);
                byte[] bytes = ms.ToArray();
                if (bytes.Length <= 64) return;

                var decoded = DecodeBitmapBytesFrozen(bytes);
                if (decoded != null)
                {
                    File.WriteAllBytes(GetHostFaviconCachePath(host), bytes);
                    MemoryIconCache[host] = decoded;
                }
            }
            catch { }
        }

        private static BitmapSource? DecodeBitmapBytesFrozen(byte[] bytes)
        {
            try
            {
                using var ms = new MemoryStream(bytes);
                var bi = new BitmapImage();
                bi.BeginInit();
                bi.CacheOption = BitmapCacheOption.OnLoad;
                bi.DecodePixelWidth = 32;
                bi.StreamSource = ms;
                bi.EndInit();
                bi.Freeze();
                return bi;
            }
            catch
            {
                return null;
            }
        }

        // =====================================================================
        // 4. JAVASCRIPT AUTOFILL & PASSWORD AUTO-CAPTURE INJECTION
        // =====================================================================
        public static string BuildPageAutofillAndCaptureScript(BrowserVaultData vault, string currentUrl, bool forceFillForms)
        {
            string host = ExtractHost(currentUrl);
            var matchingLogins = vault.Logins
                .Where(x => string.Equals(x.Host, host, StringComparison.OrdinalIgnoreCase) ||
                            host.EndsWith("." + x.Host, StringComparison.OrdinalIgnoreCase) ||
                            x.Host.EndsWith("." + host, StringComparison.OrdinalIgnoreCase))
                .OrderByDescending(x => x.HasPassword)
                .ThenByDescending(x => x.UpdatedAtUtc)
                .ToList();

            string bestUser = matchingLogins.FirstOrDefault()?.Username ?? "";
            string bestPass = "";
            var withPass = matchingLogins.FirstOrDefault(x => x.HasPassword);
            if (withPass != null)
            {
                bestUser = withPass.Username;
                bestPass = UnprotectSecret(withPass.EncryptedPassword);
            }

            var p = vault.AutofillProfile;
            var payload = new
            {
                host,
                username = bestUser,
                password = bestPass,
                forceFillForms,
                profile = new
                {
                    fullName = p.FullName,
                    firstName = p.FirstName,
                    lastName = p.LastName,
                    company = p.Company,
                    street = p.Street,
                    zip = p.Zip,
                    city = p.City,
                    country = p.Country,
                    email = p.Email,
                    phone = p.Phone
                }
            };

            string jsonConfig = JsonSerializer.Serialize(payload);

            return $@"
(function() {{
    const cfg = {jsonConfig};
    function setNativeValue(el, val) {{
        if (!el || !val) return false;
        if (!cfg.forceFillForms && el.value && el.value.length > 0) return false;
        try {{
            const proto = Object.getPrototypeOf(el);
            const desc = Object.getOwnPropertyDescriptor(proto, 'value');
            if (desc && desc.set) desc.set.call(el, val);
            else el.value = val;
            el.dispatchEvent(new Event('input', {{ bubbles: true }}));
            el.dispatchEvent(new Event('change', {{ bubbles: true }}));
            return true;
        }} catch(e) {{ return false; }}
    }}

    function tryAutofillLogin() {{
        if (!cfg.username && !cfg.password) return;
        const pwdInputs = Array.from(document.querySelectorAll('input[type=""password""]:not([disabled])'));
        if (pwdInputs.length === 0) return;

        for (const pwd of pwdInputs) {{
            if (cfg.password) setNativeValue(pwd, cfg.password);
            const form = pwd.closest('form') || document.body;
            const candidates = Array.from(form.querySelectorAll('input:not([type=""hidden""]):not([type=""password""]):not([type=""submit""]):not([type=""button""]):not([type=""checkbox""]):not([type=""radio""])'));
            for (const inp of candidates) {{
                const key = ((inp.name || '') + ' ' + (inp.id || '') + ' ' + (inp.autocomplete || '') + ' ' + (inp.placeholder || '') + ' ' + (inp.type || '')).toLowerCase();
                if (key.includes('user') || key.includes('mail') || key.includes('login') || key.includes('account') || key.includes('name') || inp.type === 'email' || inp.type === 'text') {{
                    if (cfg.username) setNativeValue(inp, cfg.username);
                    break;
                }}
            }}
        }}
    }}

    function tryAutofillAddressProfile() {{
        const p = cfg.profile;
        if (!p) return;
        const inputs = Array.from(document.querySelectorAll('input:not([type=""hidden""]):not([type=""password""]):not([type=""submit""]):not([type=""button""]):not([disabled])'));
        for (const inp of inputs) {{
            const k = ((inp.name || '') + ' ' + (inp.id || '') + ' ' + (inp.autocomplete || '') + ' ' + (inp.placeholder || '')).toLowerCase();
            if (k.includes('search') || k.includes('suche') || k.includes('query') || k === 'q') continue;

            if (inp.type === 'email' || k.includes('email') || k.includes('e-mail')) setNativeValue(inp, p.email);
            else if (inp.type === 'tel' || k.includes('phone') || k.includes('telefon') || k.includes('mobil') || k.includes('handy')) setNativeValue(inp, p.phone);
            else if (k.includes('company') || k.includes('firma') || k.includes('unternehmen') || k.includes('organization')) setNativeValue(inp, p.company);
            else if (k.includes('first') || k.includes('vorname') || k.includes('given-name')) setNativeValue(inp, p.firstName);
            else if (k.includes('last') || k.includes('nachname') || k.includes('family-name') || k.includes('surname')) setNativeValue(inp, p.lastName);
            else if (k.includes('street') || k.includes('strasse') || k.includes('straße') || k.includes('address-line1') || k.includes('anschrift')) setNativeValue(inp, p.street);
            else if (k.includes('zip') || k.includes('plz') || k.includes('postal')) setNativeValue(inp, p.zip);
            else if (k.includes('city') || k.includes('ort') || k.includes('stadt') || k.includes('locality')) setNativeValue(inp, p.city);
            else if (k.includes('name') && !k.includes('user') && !k.includes('login')) setNativeValue(inp, p.fullName);
        }}
    }}

    tryAutofillLogin();
    if (cfg.forceFillForms) tryAutofillAddressProfile();

    if (!window.__fwbCaptureHooked) {{
        window.__fwbCaptureHooked = true;
        function captureCredentials() {{
            try {{
                const pwds = Array.from(document.querySelectorAll('input[type=""password""]')).filter(x => x.value && x.value.length > 0);
                if (pwds.length === 0) return;
                const pwdEl = pwds[0];
                const scope = pwdEl.closest('form') || document.body;
                const inputs = Array.from(scope.querySelectorAll('input:not([type=""hidden""]):not([type=""password""]):not([type=""submit""]):not([type=""button""])'));
                let userVal = '';
                for (const inp of inputs) {{
                    if (inp.value && inp.value.trim().length > 0) {{
                        userVal = inp.value.trim();
                        break;
                    }}
                }}
                if (userVal && pwdEl.value && window.chrome && window.chrome.webview) {{
                    window.chrome.webview.postMessage(JSON.stringify({{
                        type: 'fwb_save_login',
                        origin: window.location.origin,
                        username: userVal,
                        password: pwdEl.value
                    }}));
                }}
            }} catch(e) {{}}
        }}
        document.addEventListener('submit', captureCredentials, true);
        document.addEventListener('keydown', function(ev) {{ if (ev.key === 'Enter') captureCredentials(); }}, true);
        document.addEventListener('mousedown', function() {{
            try {{ if (window.chrome && window.chrome.webview) window.chrome.webview.postMessage(JSON.stringify({{ type: 'fwb_webview_click' }})); }} catch(e) {{}}
        }}, true);
    }}
}})();";
        }
    }

    internal static class WinSqliteNative
    {
        private const int SQLITE_OK = 0;
        private const int SQLITE_ROW = 100;

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern int sqlite3_open(byte[] filenameUtf8, out IntPtr db);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern int sqlite3_close(IntPtr db);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern int sqlite3_prepare_v2(IntPtr db, byte[] sqlUtf8, int nByte, out IntPtr stmt, IntPtr pzTail);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern int sqlite3_step(IntPtr stmt);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern int sqlite3_finalize(IntPtr stmt);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern IntPtr sqlite3_column_text(IntPtr stmt, int iCol);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern int sqlite3_column_int(IntPtr stmt, int iCol);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern IntPtr sqlite3_column_blob(IntPtr stmt, int iCol);

        [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.StdCall)]
        private static extern int sqlite3_column_bytes(IntPtr stmt, int iCol);

        public static bool Open(string filePath, out IntPtr db)
        {
            byte[] utf8 = Encoding.UTF8.GetBytes(filePath + "\0");
            return sqlite3_open(utf8, out db) == SQLITE_OK && db != IntPtr.Zero;
        }

        public static void Close(IntPtr db)
        {
            if (db != IntPtr.Zero) sqlite3_close(db);
        }

        public static bool Prepare(IntPtr db, string sql, out IntPtr stmt)
        {
            byte[] utf8 = Encoding.UTF8.GetBytes(sql + "\0");
            return sqlite3_prepare_v2(db, utf8, utf8.Length, out stmt, IntPtr.Zero) == SQLITE_OK && stmt != IntPtr.Zero;
        }

        public static bool Step(IntPtr stmt) => sqlite3_step(stmt) == SQLITE_ROW;

        public static void FinalizeStmt(IntPtr stmt)
        {
            if (stmt != IntPtr.Zero) sqlite3_finalize(stmt);
        }

        public static string ColumnText(IntPtr stmt, int col)
        {
            IntPtr ptr = sqlite3_column_text(stmt, col);
            return ptr == IntPtr.Zero ? "" : (Marshal.PtrToStringUTF8(ptr) ?? "");
        }

        public static int ColumnInt(IntPtr stmt, int col) => sqlite3_column_int(stmt, col);

        public static byte[]? ColumnBlob(IntPtr stmt, int col)
        {
            int len = sqlite3_column_bytes(stmt, col);
            if (len <= 0) return null;
            IntPtr ptr = sqlite3_column_blob(stmt, col);
            if (ptr == IntPtr.Zero) return null;
            byte[] data = new byte[len];
            Marshal.Copy(ptr, data, 0, len);
            return data;
        }
    }
}

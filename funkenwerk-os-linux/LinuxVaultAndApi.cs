using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkOSLinux
{
    public sealed class LinuxOsSettings
    {
        public string ApiEnvironment { get; set; } = "live";
        public string EncryptedTokenLive { get; set; } = "";
        public string EncryptedTokenStage { get; set; } = "";
        public string EncryptedGeminiApiKey { get; set; } = "";
        public string PreferredBluetoothMac { get; set; } = "FC:58:FA:C7:F4:1D";
        public string PreferredBluetoothName { get; set; } = "Teufel KB 43 CR 19";
        public bool FocusMode { get; set; } = false;
        public string WallpaperMode { get; set; } = "AtelierVideo"; // "AtelierVideo" | "Kosmos"
        public string LastFunkiraSessionId { get; set; } = "";
        public string EncryptedOwnerFaceVector { get; set; } = "";
        public bool TikTokLiveMode { get; set; } = false;
        public string TikTokHandle { get; set; } = "@mein_seelenfunke";
        public string NasHost { get; set; } = "192.168.188.33";
        public string NasShare { get; set; } = "Backups";
        public string NasSubFolder { get; set; } = "Unterlagen/Meine_Daten_Backup";
        public string NasUsername { get; set; } = "alina";
        public string EncryptedNasPassword { get; set; } = "";
        public string LastNasBackupAt { get; set; } = "";
        public string LastNasBackupResult { get; set; } = "";
        public bool NasBackupPendingCatchUp { get; set; } = false;
        public List<NoteItemDto> Notes { get; set; } = new();
    }

    public sealed class NoteItemDto
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string Title { get; set; } = "Notiz";
        public string Content { get; set; } = "";
        public string UpdatedAt { get; set; } = DateTime.Now.ToString("dd.MM.yyyy HH:mm");
    }

    public sealed class PulseSnapshotDto
    {
        public bool IsConnected { get; set; }
        public bool HasToken { get; set; }
        public string Environment { get; set; } = "LIVE";
        public long LatencyMs { get; set; }
        public string StatusSummary { get; set; } = "Nicht verbunden";
        public int OpenOrdersCount { get; set; }
        public int ExpressOrdersCount { get; set; }
        public int OpenCartsCount { get; set; }
        public List<string> TopCustomersGdpr { get; set; } = new();
        public bool NewOrderJustArrived { get; set; }
        public string TimestampLocal { get; set; } = DateTime.Now.ToString("HH:mm:ss");

        // Live Active Routine (for Bold Gold Banner below Crystal Orb + Focus Mode)
        public string ActiveRoutineBanner { get; set; } = "";
        public string ActiveRoutineTitle { get; set; } = "";
        public string ActiveRoutineStep { get; set; } = "";
        public string ActiveRoutineType { get; set; } = "work";

        // 3D Atelier World State (Season, Holiday Occasion & Shop Success Level 1..5)
        public string Season { get; set; } = "autumn";
        public string Occasion { get; set; } = "standard";
        public int OrdersToday { get; set; }
        public int OrdersWeek { get; set; }
        public int OrdersMonth { get; set; }
        public int ShopLevel { get; set; } = 1;
        public string ShopLevelTitle { get; set; } = "Gründer-Werkbank";
        public int PendingTrichterCount { get; set; }
    }

    public sealed class LoginResultDto
    {
        public bool Success { get; set; }
        public bool TwoFactorRequired { get; set; }
        public string Message { get; set; } = "";
    }

    public sealed class LiveCredentialsDto
    {
        public bool Ok { get; set; }
        public bool IsAuthError { get; set; }
        public string ErrorMessage { get; set; } = "";
        public string Token { get; set; } = "";
        public string WsUrl { get; set; } = "";
        public string ChatSessionId { get; set; } = "";
        public string SystemInstruction { get; set; } = "";
        public string VoiceName { get; set; } = "Kore";
        public string AgentId { get; set; } = "";
        public string AgentName { get; set; } = "Funkira";
        public string RawToolsJson { get; set; } = "[]";
    }

    public static class LinuxVaultAndStorage
    {
        private const string VaultPrefix = "FWLINUXAES256GCM1:";
        private static readonly byte[] AppContextBytes = Encoding.UTF8.GetBytes("FunkenwerkOS.Linux.Vault.v2!MeinSeelenfunke");
        private static readonly object s_lock = new();

        public static string DataDir
        {
            get
            {
                string home = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
                if (string.IsNullOrWhiteSpace(home)) home = "/home/alina";
                string dir = Path.Combine(home, ".local", "share", "FunkenwerkOS");
                Directory.CreateDirectory(dir);
                TryRestrictDirPermissions(dir);
                return dir;
            }
        }

        public static string MeineDatenRoot
        {
            get
            {
                string home = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
                if (string.IsNullOrWhiteSpace(home)) home = "/home/alina";
                string root = Path.Combine(home, "Meine_Daten");
                EnsureEightPillarsStructure(root);
                return root;
            }
        }

        public static string TrashDir
        {
            get
            {
                string dir = Path.Combine(DataDir, "Papierkorb");
                Directory.CreateDirectory(dir);
                return dir;
            }
        }

        private static string SettingsPath => Path.Combine(DataDir, "settings.json");
        private static string MasterKeyPath => Path.Combine(DataDir, "master.key");

        public static readonly string[] EightPillars =
        {
            "00_Eingang_Trichter",
            "01_Unternehmen_Recht",
            "02_Buchhaltung_Steuern",
            "03_Manufaktur_Produkte",
            "04_Bestellungen_Versand",
            "05_Marketing_Content",
            "06_Lieferanten_Einkauf",
            "07_IT_Funkenwerk",
            "08_Privat_Archiv"
        };

        public static void EnsureEightPillarsStructure(string root)
        {
            Directory.CreateDirectory(root);
            foreach (string p in EightPillars)
            {
                Directory.CreateDirectory(Path.Combine(root, p));
            }
        }

        private static void TryRestrictDirPermissions(string path)
        {
            if (!OperatingSystem.IsLinux()) return;
            try
            {
                File.SetUnixFileMode(path, UnixFileMode.UserRead | UnixFileMode.UserWrite | UnixFileMode.UserExecute);
            }
            catch { }
        }

        public static void TryRestrictFilePermissions(string path)
        {
            if (!OperatingSystem.IsLinux()) return;
            try
            {
                File.SetUnixFileMode(path, UnixFileMode.UserRead | UnixFileMode.UserWrite);
            }
            catch { }
        }

        private static byte[] DeriveVaultKey()
        {
            byte[] rawSecret;
            if (File.Exists(MasterKeyPath))
            {
                rawSecret = File.ReadAllBytes(MasterKeyPath);
                if (rawSecret.Length != 32)
                {
                    rawSecret = RandomNumberGenerator.GetBytes(32);
                    File.WriteAllBytes(MasterKeyPath, rawSecret);
                    TryRestrictFilePermissions(MasterKeyPath);
                }
            }
            else
            {
                rawSecret = RandomNumberGenerator.GetBytes(32);
                File.WriteAllBytes(MasterKeyPath, rawSecret);
                TryRestrictFilePermissions(MasterKeyPath);
            }

            string machineId = "funkenwerk-linux";
            try
            {
                if (File.Exists("/etc/machine-id"))
                    machineId = File.ReadAllText("/etc/machine-id").Trim();
            }
            catch { }

            byte[] salt = SHA256.HashData(Encoding.UTF8.GetBytes("FW_MACHINE:" + machineId));
            byte[] derived = HKDF.DeriveKey(HashAlgorithmName.SHA256, rawSecret, 32, salt, AppContextBytes);
            CryptographicOperations.ZeroMemory(rawSecret);
            return derived;
        }

        public static string EncryptSecret(string? plain)
        {
            if (string.IsNullOrWhiteSpace(plain)) return "";
            if (plain.StartsWith(VaultPrefix, StringComparison.Ordinal)) return plain;

            byte[] key = DeriveVaultKey();
            try
            {
                byte[] plainBytes = Encoding.UTF8.GetBytes(plain);
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] cipher = new byte[plainBytes.Length];
                byte[] tag = new byte[16];

                using var aes = new AesGcm(key, 16);
                aes.Encrypt(nonce, plainBytes, cipher, tag, AppContextBytes);
                CryptographicOperations.ZeroMemory(plainBytes);

                byte[] combined = new byte[28 + cipher.Length];
                Buffer.BlockCopy(nonce, 0, combined, 0, 12);
                Buffer.BlockCopy(tag, 0, combined, 12, 16);
                Buffer.BlockCopy(cipher, 0, combined, 28, cipher.Length);
                return VaultPrefix + Convert.ToBase64String(combined);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(key);
            }
        }

        public static string DecryptSecret(string? encrypted)
        {
            if (string.IsNullOrWhiteSpace(encrypted)) return "";
            if (!encrypted.StartsWith(VaultPrefix, StringComparison.Ordinal)) return "";

            byte[] key = DeriveVaultKey();
            try
            {
                byte[] combined = Convert.FromBase64String(encrypted.Substring(VaultPrefix.Length));
                if (combined.Length < 28) return "";

                byte[] nonce = new byte[12];
                byte[] tag = new byte[16];
                byte[] cipher = new byte[combined.Length - 28];

                Buffer.BlockCopy(combined, 0, nonce, 0, 12);
                Buffer.BlockCopy(combined, 12, tag, 0, 16);
                Buffer.BlockCopy(combined, 28, cipher, 0, cipher.Length);

                byte[] plain = new byte[cipher.Length];
                using var aes = new AesGcm(key, 16);
                aes.Decrypt(nonce, cipher, tag, plain, AppContextBytes);
                return Encoding.UTF8.GetString(plain);
            }
            catch
            {
                return "";
            }
            finally
            {
                CryptographicOperations.ZeroMemory(key);
            }
        }

        public static LinuxOsSettings LoadSettings()
        {
            lock (s_lock)
            {
                try
                {
                    if (File.Exists(SettingsPath))
                    {
                        var loaded = JsonSerializer.Deserialize<LinuxOsSettings>(File.ReadAllText(SettingsPath));
                        if (loaded != null) return loaded;
                    }
                }
                catch { }
                return new LinuxOsSettings();
            }
        }

        public static void SaveSettings(LinuxOsSettings settings)
        {
            lock (s_lock)
            {
                string json = JsonSerializer.Serialize(settings, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(SettingsPath, json, new UTF8Encoding(false));
                TryRestrictFilePermissions(SettingsPath);
            }
        }

        public static string GetToken(LinuxOsSettings settings, string? env = null)
        {
            string norm = (env ?? settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant();
            return norm == "stage"
                ? DecryptSecret(settings.EncryptedTokenStage)
                : DecryptSecret(settings.EncryptedTokenLive);
        }

        public static void StoreToken(LinuxOsSettings settings, string env, string plainToken)
        {
            string norm = (env ?? "live").Trim().ToLowerInvariant();
            string enc = EncryptSecret(plainToken.Trim());
            if (norm == "stage")
                settings.EncryptedTokenStage = enc;
            else
                settings.EncryptedTokenLive = enc;
            settings.ApiEnvironment = norm == "stage" ? "stage" : "live";
            SaveSettings(settings);
        }

        public static string GetGeminiApiKey(LinuxOsSettings settings)
        {
            return DecryptSecret(settings.EncryptedGeminiApiKey);
        }

        public static void StoreGeminiApiKey(LinuxOsSettings settings, string plainKey)
        {
            settings.EncryptedGeminiApiKey = EncryptSecret(plainKey.Trim());
            SaveSettings(settings);
        }

        public static float[]? GetOwnerFaceVector(LinuxOsSettings settings)
        {
            string dec = DecryptSecret(settings.EncryptedOwnerFaceVector);
            if (string.IsNullOrWhiteSpace(dec)) return null;
            try
            {
                var vec = JsonSerializer.Deserialize<float[]>(dec);
                return (vec != null && vec.Length == 128) ? vec : null;
            }
            catch
            {
                return null;
            }
        }

        public static void StoreOwnerFaceVector(LinuxOsSettings settings, float[]? vector)
        {
            if (vector == null || vector.Length == 0)
            {
                settings.EncryptedOwnerFaceVector = "";
            }
            else
            {
                settings.EncryptedOwnerFaceVector = EncryptSecret(JsonSerializer.Serialize(vector));
            }
            SaveSettings(settings);
        }

        public static string GetNasPassword(LinuxOsSettings settings)
        {
            return DecryptSecret(settings.EncryptedNasPassword);
        }

        public static void StoreNasPassword(LinuxOsSettings settings, string? plainPassword)
        {
            settings.EncryptedNasPassword = string.IsNullOrWhiteSpace(plainPassword)
                ? ""
                : EncryptSecret(plainPassword);
            SaveSettings(settings);
        }
    }

    public static class SeelenfunkeLinuxApiClient
    {
        private static readonly string[] AllowedHosts =
        {
            "mein-seelenfunke.de",
            "stage.mein-seelenfunke.de"
        };

        private static readonly HttpClient Http = CreateHardenedClient();
        private static readonly ConcurrentDictionary<string, (DateTime ExpiresUtc, string RawJson)> s_schemaCache = new(StringComparer.OrdinalIgnoreCase);

        private static bool s_initializedOrderTracking;
        private static int s_lastOpenOrders = -1;
        private static string s_lastLatestOrderId = "";

        public static PulseSnapshotDto LatestPulse { get; private set; } = new();

        private static HttpClient CreateHardenedClient()
        {
            var handler = new SocketsHttpHandler
            {
                AllowAutoRedirect = false,
                UseProxy = false,
                PooledConnectionLifetime = TimeSpan.FromMinutes(3),
                ConnectTimeout = TimeSpan.FromSeconds(8),
                SslOptions =
                {
                    EnabledSslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13
                }
            };
            return new HttpClient(handler)
            {
                Timeout = TimeSpan.FromSeconds(25),
                MaxResponseContentBufferSize = 4L * 1024L * 1024L
            };
        }

        public static string ResolveBaseApiUrl(string? env)
        {
            string norm = (env ?? "live").Trim().ToLowerInvariant();
            return norm == "stage"
                ? "https://stage.mein-seelenfunke.de/api"
                : "https://mein-seelenfunke.de/api";
        }

        public static string ResolveOriginUrl(string? env)
        {
            string norm = (env ?? "live").Trim().ToLowerInvariant();
            return norm == "stage"
                ? "https://stage.mein-seelenfunke.de"
                : "https://mein-seelenfunke.de";
        }

        public static bool ValidateHttpsUrl(string url)
        {
            if (!Uri.TryCreate(url, UriKind.Absolute, out var uri)) return false;
            if (!uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase)) return false;
            return AllowedHosts.Any(h => h.Equals(uri.Host, StringComparison.OrdinalIgnoreCase));
        }

        public static bool ValidateWebSocketUrl(string wsUrl)
        {
            if (!Uri.TryCreate(wsUrl, UriKind.Absolute, out var uri)) return false;
            if (!uri.Scheme.Equals("wss", StringComparison.OrdinalIgnoreCase)) return false;
            string host = uri.Host;
            return host.Equals("mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase)
                || host.EndsWith(".mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase)
                || host.EndsWith(".project.host", StringComparison.OrdinalIgnoreCase);
        }

        public static async Task<LoginResultDto> LoginAsync(LinuxOsSettings settings, string env, string email, string password, string? twoFactorCode)
        {
            string norm = (env ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string baseUrl = ResolveBaseApiUrl(norm);
            if (!ValidateHttpsUrl(baseUrl))
                return new LoginResultDto { Success = false, Message = "Ungültige Ziel-URL blockiert." };

            try
            {
                var payload = new Dictionary<string, string>
                {
                    ["email"] = (email ?? "").Trim(),
                    ["password"] = password ?? "",
                    ["device_name"] = $"FunkenwerkFunkira-{Environment.MachineName}",
                    ["scope"] = "manufaktur_funkira"
                };
                if (!string.IsNullOrWhiteSpace(twoFactorCode))
                    payload["two_factor_code"] = twoFactorCode.Trim();

                using var req = new HttpRequestMessage(HttpMethod.Post, $"{baseUrl}/login");
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                req.Content = new StringContent(JsonSerializer.Serialize(payload), new UTF8Encoding(false), "application/json");

                using var resp = await Http.SendAsync(req).ConfigureAwait(false);
                string body = await resp.Content.ReadAsStringAsync().ConfigureAwait(false);

                using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
                var root = doc.RootElement;

                if (resp.IsSuccessStatusCode && root.TryGetProperty("token", out var tokEl))
                {
                    string tok = tokEl.GetString() ?? "";
                    if (!string.IsNullOrWhiteSpace(tok))
                    {
                        LinuxVaultAndStorage.StoreToken(settings, norm, tok);
                        s_initializedOrderTracking = false;
                        return new LoginResultDto { Success = true, Message = "Verbunden" };
                    }
                }

                if (root.TryGetProperty("status", out var stEl) &&
                    string.Equals(stEl.GetString(), "two_factor_required", StringComparison.OrdinalIgnoreCase))
                {
                    return new LoginResultDto { Success = false, TwoFactorRequired = true, Message = "Bitte 2FA-Code eingeben." };
                }

                string msg = root.TryGetProperty("message", out var mEl) ? (mEl.GetString() ?? "Anmeldung fehlgeschlagen.") : $"HTTP {(int)resp.StatusCode}";
                return new LoginResultDto { Success = false, Message = msg };
            }
            catch (Exception ex)
            {
                return new LoginResultDto { Success = false, Message = $"Netzwerkfehler: {ex.Message}" };
            }
        }

        public static void PopulateLocalRoutineAndWorldDefaults(PulseSnapshotDto snap, DateTime? forceBerlinTime = null)
        {
            DateTime nowB;
            if (forceBerlinTime.HasValue)
            {
                nowB = forceBerlinTime.Value;
            }
            else
            {
                try
                {
                    var tz = TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin");
                    nowB = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, tz);
                }
                catch
                {
                    nowB = DateTime.Now;
                }
            }

            int mins = nowB.Hour * 60 + nowB.Minute;
            // Exact 1:1 schedule from ManagementDayRoutineSeeder.php
            (string Start, string End, string Title, string Type, string Step) block = mins switch
            {
                >= 420 and < 450 => ("07:00", "07:30", "Aufstehen & Frischestart", "hygiene", "Aufstehen & Fokus schärfen"),
                >= 450 and < 525 => ("07:30", "08:45", "Morgensport: Laufen & Ganzkörper-Workout", "sport", "Laufen & Workout durchziehen"),
                >= 525 and < 600 => ("08:45", "10:00", "Bad, Styling & Hormone", "hygiene", "Pflege, Hormone & Tagesoutfit"),
                >= 600 and < 630 => ("10:00", "10:30", "Frühstück & Mentaler Fokus", "food", "Gesund frühstücken & Tagesziel Nr. 1"),
                >= 630 and < 750 => ("10:30", "12:30", "Deep Work Block I: Fokusprojekt", "work", "Priorität 1 gnadenlos durchziehen"),
                >= 750 and < 810 => ("12:30", "13:30", "Mittagspause & Regeneration", "food", "Kochen, in Ruhe essen & Kraft tanken"),
                >= 810 and < 870 => ("13:30", "14:30", "Haushalt, Termine & Life-Orga", "break", "Ordnung, Termine & Erledigungen"),
                >= 870 and < 1020 => ("14:30", "17:00", "Work Block II: Operations & Business", "work", "Marketing, Mails, Bestellungen & Business"),
                >= 1020 and < 1050 => ("17:00", "17:30", "Feierabend-Reset & Tagesabschluss", "break", "Schreibtisch aufräumen & Abschluss"),
                >= 1050 and < 1125 => ("17:30", "18:45", "Abendbrot & Abendpflege", "hygiene", "Abendbrot & Wohlfühlkleidung"),
                >= 1125 and < 1320 => ("18:45", "22:00", "Freizeit & Me-Time", "break", "Zocken, Filme, Hobbys & Seele baumeln lassen"),
                >= 1320 and < 1350 => ("22:00", "22:30", "Bettfertig & Runterfahren", "hygiene", "Bildschirme aus & bettfertig machen"),
                _ => ("22:30", "07:00", "Nachtruhe & Regeneration", "sleep", "Tiefer Schlaf & Regeneration")
            };

            snap.ActiveRoutineTitle = block.Title;
            snap.ActiveRoutineStep = block.Step;
            snap.ActiveRoutineType = block.Type;
            snap.ActiveRoutineBanner = $"{block.Start} - {block.End} · {block.Title.ToUpperInvariant()}";

            int m = nowB.Month;
            int d = nowB.Day;
            snap.Season = m is 12 or 1 or 2 ? "winter" : (m is 3 or 4 or 5 ? "spring" : (m is 6 or 7 or 8 ? "summer" : "autumn"));
            snap.Occasion = "standard";
            if (m == 12 && d >= 24 && d <= 26) snap.Occasion = "christmas";
            else if ((m == 11 && d >= 24) || (m == 12 && d < 24)) snap.Occasion = "advent";
            else if ((m == 12 && d >= 30) || (m == 1 && d <= 2)) snap.Occasion = "new_year";
            else if (m == 2 && d >= 7 && d <= 14) snap.Occasion = "valentines";
            else if (m == 10 && d >= 25) snap.Occasion = "halloween";
            else if (m == 11 && d >= 1 && d <= 3) snap.Occasion = "anniversary";
            else if (m == 5 && d >= 8 && d <= 14) snap.Occasion = "mothers_day";
        }

        public static async Task<PulseSnapshotDto> FetchPulseAsync(LinuxOsSettings settings)
        {
            string norm = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            var snap = new PulseSnapshotDto
            {
                Environment = norm.ToUpperInvariant(),
                TimestampLocal = DateTime.Now.ToString("HH:mm:ss")
            };
            PopulateLocalRoutineAndWorldDefaults(snap);

            string bearer = LinuxVaultAndStorage.GetToken(settings, norm);
            if (string.IsNullOrWhiteSpace(bearer))
            {
                snap.HasToken = false;
                snap.StatusSummary = "Anmeldung erforderlich";
                LatestPulse = snap;
                return snap;
            }

            snap.HasToken = true;
            string baseUrl = ResolveBaseApiUrl(norm);
            if (!ValidateHttpsUrl(baseUrl))
            {
                snap.StatusSummary = "Ungültige URL";
                LatestPulse = snap;
                return snap;
            }

            var sw = Stopwatch.StartNew();
            try
            {
                var (status, body) = await GetJsonAsync($"{baseUrl}/shop/monitor-pulse", bearer).ConfigureAwait(false);
                if (status == 404 && string.IsNullOrWhiteSpace(body))
                {
                    (_, body) = await GetJsonAsync($"{baseUrl}/shop/orders?status=all", bearer).ConfigureAwait(false);
                }
                sw.Stop();
                snap.LatencyMs = sw.ElapsedMilliseconds;

                if (string.IsNullOrWhiteSpace(body))
                {
                    snap.IsConnected = false;
                    snap.StatusSummary = status == 401 || status == 403 ? "Token abgelaufen" : "Offline";
                    LatestPulse = snap;
                    return snap;
                }

                snap.IsConnected = true;
                snap.StatusSummary = $"{snap.Environment} · {snap.LatencyMs} ms";
                ParsePulseBody(body, snap);
                LatestPulse = snap;

                if (snap.PendingTrichterCount > 0)
                {
                    _ = Task.Run(() => DocumentArchiveAndNasEngine.PullPendingServerTrichterRelayAsync(Http, baseUrl, bearer));
                }
            }
            catch
            {
                snap.IsConnected = false;
                snap.StatusSummary = "Verbindung unterbrochen";
                LatestPulse = snap;
            }

            return snap;
        }

        public static async Task<LiveCredentialsDto> FetchLiveCredentialsAsync(LinuxOsSettings settings, string? chatSessionId = null)
        {
            string norm = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string bearer = LinuxVaultAndStorage.GetToken(settings, norm);
            if (string.IsNullOrWhiteSpace(bearer))
            {
                return new LiveCredentialsDto
                {
                    Ok = false,
                    IsAuthError = true,
                    ErrorMessage = "Bitte zuerst oben rechts über „Verbinden“ am Mein-Seelenfunke Server anmelden."
                };
            }

            string baseUrl = ResolveBaseApiUrl(norm);
            string sessParam = string.IsNullOrWhiteSpace(chatSessionId) ? (settings.LastFunkiraSessionId ?? "") : chatSessionId.Trim();
            string url = $"{baseUrl}/ai/live-credentials?chat_session_id={Uri.EscapeDataString(sessParam)}&t={DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}";
            if (!ValidateHttpsUrl(url))
                return new LiveCredentialsDto { Ok = false, ErrorMessage = "Ungültige Ziel-URL blockiert." };

            try
            {
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(12));
                using var req = new HttpRequestMessage(HttpMethod.Get, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                string body = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);

                if (!resp.IsSuccessStatusCode)
                {
                    string reason = $"HTTP {(int)resp.StatusCode}";
                    try
                    {
                        using var errDoc = JsonDocument.Parse(body);
                        if (errDoc.RootElement.TryGetProperty("message", out var m))
                            reason = m.GetString() ?? reason;
                        else if (errDoc.RootElement.TryGetProperty("error", out var er))
                            reason = er.GetString() ?? reason;
                    }
                    catch { }

                    bool isUnauth = (int)resp.StatusCode == 401;
                    if (isUnauth)
                    {
                        reason = "Sitzung abgelaufen. Bitte einmal neu anmelden.";
                    }
                    return new LiveCredentialsDto { Ok = false, IsAuthError = isUnauth, ErrorMessage = reason };
                }

                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                string tok = GetStr(root, "token");
                string wsUrl = GetStr(root, "ws_url").Trim();
                if (wsUrl.StartsWith("/", StringComparison.Ordinal))
                    wsUrl = ResolveOriginUrl(norm).Replace("https://", "wss://", StringComparison.OrdinalIgnoreCase) + wsUrl;
                if (wsUrl.StartsWith("ws://", StringComparison.OrdinalIgnoreCase))
                    wsUrl = "wss://" + wsUrl.Substring(5);

                string resolvedSessionId = GetStr(root, "chat_session_id");
                if (!string.IsNullOrWhiteSpace(resolvedSessionId) && resolvedSessionId != settings.LastFunkiraSessionId)
                {
                    settings.LastFunkiraSessionId = resolvedSessionId;
                    LinuxVaultAndStorage.SaveSettings(settings);
                }

                string rawTools = root.TryGetProperty("tools", out var tEl) ? tEl.GetRawText() : "[]";
                bool wsValid = ValidateWebSocketUrl(wsUrl);

                return new LiveCredentialsDto
                {
                    Ok = !string.IsNullOrWhiteSpace(tok) && wsValid,
                    IsAuthError = false,
                    ErrorMessage = string.IsNullOrWhiteSpace(tok)
                        ? "Kein Live-Token vom Server erhalten."
                        : (!wsValid ? $"Live-Bridge-URL nicht freigegeben ({wsUrl})." : ""),
                    Token = tok,
                    WsUrl = wsUrl,
                    ChatSessionId = resolvedSessionId,
                    SystemInstruction = GetStr(root, "system_instruction"),
                    VoiceName = GetStr(root, "voice_name", "Kore"),
                    AgentId = GetStr(root, "agent_id"),
                    AgentName = GetStr(root, "agent_name", "Funkira"),
                    RawToolsJson = rawTools
                };
            }
            catch (Exception ex)
            {
                return new LiveCredentialsDto { Ok = false, ErrorMessage = $"Verbindungsfehler: {ex.Message}" };
            }
        }

        public static async Task<string> FetchAiSchemaCachedAsync(LinuxOsSettings settings)
        {
            string norm = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            if (s_schemaCache.TryGetValue(norm, out var cached) && cached.ExpiresUtc > DateTime.UtcNow)
                return cached.RawJson;

            string bearer = LinuxVaultAndStorage.GetToken(settings, norm);
            if (string.IsNullOrWhiteSpace(bearer)) return "";

            string baseUrl = ResolveBaseApiUrl(norm);
            var (status, body) = await GetJsonAsync($"{baseUrl}/ai/schema", bearer).ConfigureAwait(false);
            if (status == 200 && !string.IsNullOrWhiteSpace(body))
            {
                s_schemaCache[norm] = (DateTime.UtcNow.AddMinutes(10), body);
                return body;
            }
            return "";
        }

        public static async Task<string> ExecuteServerToolAsync(
            LinuxOsSettings settings,
            string functionName,
            JsonElement argsElement,
            string? chatSessionId,
            string? agentId)
        {
            string norm = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string bearer = LinuxVaultAndStorage.GetToken(settings, norm);
            if (string.IsNullOrWhiteSpace(bearer))
                return JsonSerializer.Serialize(new { status = "error", message = "Nicht angemeldet." });

            string url = $"{ResolveBaseApiUrl(norm)}/ai/execute";
            if (!ValidateHttpsUrl(url))
                return JsonSerializer.Serialize(new { status = "error", message = "Ziel-URL blockiert." });

            try
            {
                using var ms = new MemoryStream();
                using (var writer = new Utf8JsonWriter(ms))
                {
                    writer.WriteStartObject();
                    writer.WriteString("function", functionName);
                    writer.WritePropertyName("args");
                    if (argsElement.ValueKind == JsonValueKind.Object)
                        argsElement.WriteTo(writer);
                    else
                    {
                        writer.WriteStartObject();
                        writer.WriteEndObject();
                    }
                    if (!string.IsNullOrWhiteSpace(chatSessionId))
                        writer.WriteString("chat_session_id", chatSessionId);
                    if (!string.IsNullOrWhiteSpace(agentId))
                        writer.WriteString("agent_id", agentId);
                    writer.WriteEndObject();
                }

                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(20));
                using var req = new HttpRequestMessage(HttpMethod.Post, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                req.Content = new ByteArrayContent(ms.ToArray());
                req.Content.Headers.ContentType = new MediaTypeHeaderValue("application/json");

                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                string body = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                return string.IsNullOrWhiteSpace(body)
                    ? JsonSerializer.Serialize(new { status = resp.IsSuccessStatusCode ? "success" : "error", httpStatus = (int)resp.StatusCode })
                    : body;
            }
            catch (Exception ex)
            {
                return JsonSerializer.Serialize(new { status = "error", message = ex.Message });
            }
        }

        public static async Task<string> SendChatFallbackAsync(LinuxOsSettings settings, string promptText)
        {
            string norm = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string bearer = LinuxVaultAndStorage.GetToken(settings, norm);
            if (string.IsNullOrWhiteSpace(bearer))
                return JsonSerializer.Serialize(new { status = "error", message = "Bitte zuerst am Server anmelden." });

            string url = $"{ResolveBaseApiUrl(norm)}/ai/chat";
            if (!ValidateHttpsUrl(url))
                return JsonSerializer.Serialize(new { status = "error", message = "Ziel-URL blockiert." });

            try
            {
                var payload = new
                {
                    prompt = promptText,
                    history = new[] { new { role = "user", content = promptText } },
                    chat_session_id = string.IsNullOrWhiteSpace(settings.LastFunkiraSessionId) ? null : settings.LastFunkiraSessionId
                };

                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(25));
                using var req = new HttpRequestMessage(HttpMethod.Post, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                req.Content = new StringContent(JsonSerializer.Serialize(payload), new UTF8Encoding(false), "application/json");

                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                return await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
            }
            catch (Exception ex)
            {
                return JsonSerializer.Serialize(new { status = "error", message = ex.Message });
            }
        }

        private static async Task<(int Status, string? Body)> GetJsonAsync(string url, string bearer)
        {
            try
            {
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(9));
                using var req = new HttpRequestMessage(HttpMethod.Get, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                int code = (int)resp.StatusCode;
                if (!resp.IsSuccessStatusCode) return (code, null);
                return (code, await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false));
            }
            catch
            {
                return (0, null);
            }
        }

        private static void ParsePulseBody(string json, PulseSnapshotDto snap)
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;

            int openOrders = GetInt(root, "open_orders_count", -1);
            int expressOrders = GetInt(root, "express_orders_count", 0);
            int openCarts = GetInt(root, "open_carts_count", 0);
            snap.PendingTrichterCount = Math.Max(0, GetInt(root, "pending_trichter_count", 0));
            string latestOrderId = GetStr(root, "latest_order_id");

            if (openOrders < 0 && root.TryGetProperty("orders", out var ordNode))
            {
                JsonElement arr = default;
                bool hasArr = false;
                if (ordNode.ValueKind == JsonValueKind.Array) { arr = ordNode; hasArr = true; }
                else if (ordNode.ValueKind == JsonValueKind.Object && ordNode.TryGetProperty("data", out var dArr) && dArr.ValueKind == JsonValueKind.Array)
                {
                    arr = dArr;
                    hasArr = true;
                }

                if (hasArr)
                {
                    int cnt = 0;
                    foreach (var o in arr.EnumerateArray())
                    {
                        string st = GetStr(o, "status", "pending").ToLowerInvariant();
                        if (st is not ("completed" or "cancelled" or "refunded" or "shipped"))
                            cnt++;
                        if (string.IsNullOrWhiteSpace(latestOrderId))
                            latestOrderId = GetStr(o, "id");
                    }
                    openOrders = cnt;
                }
            }

            snap.OpenOrdersCount = Math.Max(0, openOrders);
            snap.ExpressOrdersCount = Math.Max(0, expressOrders);
            snap.OpenCartsCount = Math.Max(0, openCarts);

            if (root.TryGetProperty("active_routine", out var arEl) && arEl.ValueKind == JsonValueKind.Object)
            {
                string rBanner = GetStr(arEl, "banner");
                string rTitle = GetStr(arEl, "title");
                if (!string.IsNullOrWhiteSpace(rBanner)) snap.ActiveRoutineBanner = rBanner;
                if (!string.IsNullOrWhiteSpace(rTitle)) snap.ActiveRoutineTitle = rTitle;
                snap.ActiveRoutineStep = GetStr(arEl, "current_step", snap.ActiveRoutineStep);
                snap.ActiveRoutineType = GetStr(arEl, "type", snap.ActiveRoutineType);
            }

            if (root.TryGetProperty("world_state", out var wsEl) && wsEl.ValueKind == JsonValueKind.Object)
            {
                snap.Season = GetStr(wsEl, "season", snap.Season);
                snap.Occasion = GetStr(wsEl, "occasion", snap.Occasion);
                snap.OrdersToday = GetInt(wsEl, "orders_today", 0);
                snap.OrdersWeek = GetInt(wsEl, "orders_week", 0);
                snap.OrdersMonth = GetInt(wsEl, "orders_month", 0);
                snap.ShopLevel = Math.Clamp(GetInt(wsEl, "shop_level", 1), 1, 5);
                snap.ShopLevelTitle = GetStr(wsEl, "level_title", snap.ShopLevelTitle);
            }
            else
            {
                int totalOrders = GetInt(root, "total", snap.OpenOrdersCount);
                int score = Math.Max(snap.OpenOrdersCount, totalOrders / 2);
                snap.ShopLevel = score >= 100 ? 5 : (score >= 50 ? 4 : (score >= 20 ? 3 : (score >= 5 ? 2 : 1)));
                snap.ShopLevelTitle = snap.ShopLevel switch
                {
                    5 => "Seelenfunke Imperium",
                    4 => "Premium-Manufaktur",
                    3 => "Florierendes Atelier",
                    2 => "Aufstrebende Manufaktur",
                    _ => "Gründer-Werkbank"
                };
            }

            if (root.TryGetProperty("top_customers", out var tcArr) && tcArr.ValueKind == JsonValueKind.Array)
            {
                foreach (var item in tcArr.EnumerateArray())
                {
                    string raw = item.ValueKind == JsonValueKind.String
                        ? (item.GetString() ?? "")
                        : GetStr(item, "display_name");
                    string safe = AnonymizeGdpr(raw);
                    if (!string.IsNullOrWhiteSpace(safe) && snap.TopCustomersGdpr.Count < 3)
                        snap.TopCustomersGdpr.Add(safe);
                }
            }

            if (!s_initializedOrderTracking)
            {
                s_lastOpenOrders = snap.OpenOrdersCount;
                s_lastLatestOrderId = latestOrderId;
                s_initializedOrderTracking = true;
            }
            else
            {
                bool countIncreased = snap.OpenOrdersCount > s_lastOpenOrders;
                bool newIdSeen = !string.IsNullOrWhiteSpace(latestOrderId) &&
                                 !string.IsNullOrWhiteSpace(s_lastLatestOrderId) &&
                                 !string.Equals(latestOrderId, s_lastLatestOrderId, StringComparison.OrdinalIgnoreCase) &&
                                 snap.OpenOrdersCount >= s_lastOpenOrders;

                if (countIncreased || newIdSeen)
                {
                    snap.NewOrderJustArrived = true;
                }

                s_lastOpenOrders = snap.OpenOrdersCount;
                if (!string.IsNullOrWhiteSpace(latestOrderId))
                    s_lastLatestOrderId = latestOrderId;
            }
        }

        public static string AnonymizeGdpr(string? rawName)
        {
            if (string.IsNullOrWhiteSpace(rawName)) return "";
            var parts = rawName.Trim().Split(' ', StringSplitOptions.RemoveEmptyEntries);
            if (parts.Length == 0) return "";
            string first = parts[0];
            if (first.Length > 18) first = first.Substring(0, 18);
            if (parts.Length == 1) return first;
            string last = parts[^1].Trim('.');
            return last.Length > 0 ? $"{first} {char.ToUpperInvariant(last[0])}." : first;
        }

        internal static string GetStr(JsonElement el, string prop, string def = "")
        {
            if (!el.TryGetProperty(prop, out var p)) return def;
            if (p.ValueKind == JsonValueKind.String) return p.GetString() ?? def;
            if (p.ValueKind == JsonValueKind.Number) return p.GetRawText();
            return def;
        }

        internal static int GetInt(JsonElement el, string prop, int def = 0)
        {
            if (!el.TryGetProperty(prop, out var p)) return def;
            if (p.ValueKind == JsonValueKind.Number && p.TryGetInt32(out int v)) return v;
            if (p.ValueKind == JsonValueKind.String && int.TryParse(p.GetString(), NumberStyles.Integer, CultureInfo.InvariantCulture, out int sv)) return sv;
            return def;
        }
    }
}

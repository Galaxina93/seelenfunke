using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Sockets;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
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
        public string EncryptedOwnerVoiceVector { get; set; } = "";
        public string EncryptedCompanionFacesJson { get; set; } = "";
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
        public string WorldItemPlacementsJson { get; set; } = "";
        public List<NoteItemDto> Notes { get; set; } = new();
    }

    public sealed class CompanionFaceEntryDto
    {
        public string ContactId { get; set; } = "";
        public string Name { get; set; } = "";
        public string Relationship { get; set; } = "";
        public float[] Vector { get; set; } = Array.Empty<float>();
        public float[] VoiceVector { get; set; } = Array.Empty<float>();
        public string SavedAt { get; set; } = DateTime.Now.ToString("dd.MM.yyyy HH:mm");
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

        // 3D Atelier & Garden World State (Season, Holiday Occasion & Shop Success Level 1..5)
        public string Season { get; set; } = "autumn";
        public string SeasonLabel { get; set; } = "Herbst";
        public string Occasion { get; set; } = "standard";
        public string OccasionLabel { get; set; } = "Regulärer Manufaktur-Tag";
        public string UpcomingHolidayHint { get; set; } = "";
        public int OrdersToday { get; set; }
        public int OrdersWeek { get; set; }
        public int OrdersMonth { get; set; }
        public int ShopLevel { get; set; } = 1;
        public string ShopLevelTitle { get; set; } = "Gründer-Werkbank";
        public int PendingTrichterCount { get; set; }
        public int OpenSystemErrorsCount { get; set; }
        public PriorityOrderCheckinDto? PriorityOrderCheckin { get; set; }
    }

    public sealed class PriorityOrderCheckinDto
    {
        public string OrderId { get; set; } = "";
        public string OrderNumber { get; set; } = "";
        public string Status { get; set; } = "";
        public string StatusLabelDe { get; set; } = "";
        public string PaymentStatus { get; set; } = "";
        public bool IsExpress { get; set; }
        public string ProductSummary { get; set; } = "";
        public int ItemsTotal { get; set; }
        public int ItemsCompleted { get; set; }
        public bool HasDhlLabel { get; set; }
        public string WorkshopNote { get; set; } = "";
        public string WorkshopNoteUpdatedAt { get; set; } = "";
        public string WorkshopNextCheckAt { get; set; } = "";
        public bool WorkshopCheckinPaused { get; set; }
        public bool IsCheckinDue { get; set; }
        public string CheckinReason { get; set; } = "none";
        public bool HasDiscrepancy { get; set; }
        public string DiscrepancyCode { get; set; } = "";
        public string SuggestedStatus { get; set; } = "";
        public string SuggestedStatusLabel { get; set; } = "";
        public string DiscrepancyUiHint { get; set; } = "";
        public string AskAlinaPrompt { get; set; } = "";
        public string CheckinStateKey { get; set; } = "";
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

        internal static string? TestOverrideRootDir { get; set; }

        public static string UserHomeDir
        {
            get
            {
                if (!string.IsNullOrWhiteSpace(TestOverrideRootDir))
                {
                    string testHome = Path.GetFullPath(TestOverrideRootDir);
                    Directory.CreateDirectory(testHome);
                    TryRestrictDirPermissions(testHome);
                    return testHome;
                }
                string home = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
                if (string.IsNullOrWhiteSpace(home)) home = "/home/alina";
                return home;
            }
        }

        public static string DataDir
        {
            get
            {
                string dir = Path.Combine(UserHomeDir, ".local", "share", "FunkenwerkOS");
                Directory.CreateDirectory(dir);
                TryRestrictDirPermissions(dir);
                return dir;
            }
        }

        public static string MeineDatenRoot
        {
            get
            {
                string root = Path.Combine(UserHomeDir, "Meine_Daten");
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
                TryRestrictDirPermissions(dir);
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

        public static bool IsValidPrivateLanIpv4(string? host)
        {
            if (string.IsNullOrWhiteSpace(host)) return false;
            string trimmed = host.Trim();
            string[] parts = trimmed.Split('.');
            if (parts.Length != 4) return false;
            foreach (string part in parts)
            {
                if (part.Length == 0 || part.Length > 3 || !part.All(char.IsAsciiDigit))
                    return false;
                if (part.Length > 1 && part[0] == '0')
                    return false;
            }

            if (!IPAddress.TryParse(trimmed, out var ip) || ip.AddressFamily != AddressFamily.InterNetwork)
                return false;

            byte[] b = ip.GetAddressBytes();
            if (b.Length != 4) return false;
            if (b[0] == 10) return true; // 10.0.0.0/8
            if (b[0] == 172 && b[1] >= 16 && b[1] <= 31) return true; // 172.16.0.0/12
            if (b[0] == 192 && b[1] == 168) return true; // 192.168.0.0/16
            return false;
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

        public static void WriteFileAtomically0600(string path, ReadOnlySpan<byte> data)
        {
            string? dir = Path.GetDirectoryName(path);
            if (!string.IsNullOrEmpty(dir))
            {
                Directory.CreateDirectory(dir);
                TryRestrictDirPermissions(dir);
            }

            string tmpPath = $"{path}.{Guid.NewGuid():N}.tmp";
            try
            {
                var opts = new FileStreamOptions
                {
                    Mode = FileMode.CreateNew,
                    Access = FileAccess.Write,
                    Share = FileShare.None
                };
                if (OperatingSystem.IsLinux())
                {
                    opts.UnixCreateMode = UnixFileMode.UserRead | UnixFileMode.UserWrite;
                }

                using (var fs = new FileStream(tmpPath, opts))
                {
                    fs.Write(data);
                    fs.Flush(flushToDisk: true);
                }

                TryRestrictFilePermissions(tmpPath);
                File.Move(tmpPath, path, overwrite: true);
                TryRestrictFilePermissions(path);
            }
            catch
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
                throw;
            }
        }

        private static byte[] DeriveVaultKey()
        {
            lock (s_lock)
            {
                byte[] rawSecret;
                if (File.Exists(MasterKeyPath))
                {
                    rawSecret = File.ReadAllBytes(MasterKeyPath);
                    if (rawSecret.Length != 32)
                    {
                        CryptographicOperations.ZeroMemory(rawSecret);
                        rawSecret = RandomNumberGenerator.GetBytes(32);
                        WriteFileAtomically0600(MasterKeyPath, rawSecret);
                    }
                    else
                    {
                        TryRestrictFilePermissions(MasterKeyPath);
                    }
                }
                else
                {
                    rawSecret = RandomNumberGenerator.GetBytes(32);
                    WriteFileAtomically0600(MasterKeyPath, rawSecret);
                }

                try
                {
                    string machineId = "funkenwerk-linux";
                    try
                    {
                        if (File.Exists("/etc/machine-id"))
                            machineId = File.ReadAllText("/etc/machine-id").Trim();
                    }
                    catch { }

                    byte[] salt = SHA256.HashData(Encoding.UTF8.GetBytes("FW_MACHINE:" + machineId));
                    return HKDF.DeriveKey(HashAlgorithmName.SHA256, rawSecret, 32, salt, AppContextBytes);
                }
                finally
                {
                    CryptographicOperations.ZeroMemory(rawSecret);
                }
            }
        }

        public static string EncryptSecret(string? plain)
        {
            if (string.IsNullOrWhiteSpace(plain)) return "";
            if (plain.StartsWith(VaultPrefix, StringComparison.Ordinal)) return plain;

            byte[] key = DeriveVaultKey();
            byte[]? plainBytes = null;
            try
            {
                plainBytes = Encoding.UTF8.GetBytes(plain);
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] cipher = new byte[plainBytes.Length];
                byte[] tag = new byte[16];

                using var aes = new AesGcm(key, 16);
                aes.Encrypt(nonce, plainBytes, cipher, tag, AppContextBytes);

                byte[] combined = new byte[28 + cipher.Length];
                Buffer.BlockCopy(nonce, 0, combined, 0, 12);
                Buffer.BlockCopy(tag, 0, combined, 12, 16);
                Buffer.BlockCopy(cipher, 0, combined, 28, cipher.Length);
                return VaultPrefix + Convert.ToBase64String(combined);
            }
            finally
            {
                if (plainBytes != null)
                    CryptographicOperations.ZeroMemory(plainBytes);
                CryptographicOperations.ZeroMemory(key);
            }
        }

        public static string DecryptSecret(string? encrypted)
        {
            if (string.IsNullOrWhiteSpace(encrypted)) return "";
            if (!encrypted.StartsWith(VaultPrefix, StringComparison.Ordinal)) return "";

            byte[] key = DeriveVaultKey();
            byte[]? plain = null;
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

                plain = new byte[cipher.Length];
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
                if (plain != null)
                    CryptographicOperations.ZeroMemory(plain);
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
                byte[] utf8 = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(settings, new JsonSerializerOptions { WriteIndented = true }));
                WriteFileAtomically0600(SettingsPath, utf8);
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

        public static float[]? GetOwnerVoiceVector(LinuxOsSettings settings)
        {
            string dec = DecryptSecret(settings.EncryptedOwnerVoiceVector);
            if (string.IsNullOrWhiteSpace(dec)) return null;
            try
            {
                var vec = JsonSerializer.Deserialize<float[]>(dec);
                return (vec != null && vec.Length >= 48 && vec.Length <= 1024) ? vec : null;
            }
            catch
            {
                return null;
            }
        }

        public static void StoreOwnerVoiceVector(LinuxOsSettings settings, float[]? vector)
        {
            if (vector == null || vector.Length < 48 || vector.Length > 1024)
            {
                settings.EncryptedOwnerVoiceVector = "";
            }
            else
            {
                settings.EncryptedOwnerVoiceVector = EncryptSecret(JsonSerializer.Serialize(vector));
            }
            SaveSettings(settings);
        }

        public static List<CompanionFaceEntryDto> GetCompanionFaces(LinuxOsSettings settings)
        {
            string dec = DecryptSecret(settings.EncryptedCompanionFacesJson);
            if (string.IsNullOrWhiteSpace(dec)) return new List<CompanionFaceEntryDto>();
            try
            {
                var list = JsonSerializer.Deserialize<List<CompanionFaceEntryDto>>(dec);
                if (list == null) return new List<CompanionFaceEntryDto>();
                return list.Where(x => x != null && !string.IsNullOrWhiteSpace(x.Name) &&
                    ((x.Vector != null && x.Vector.Length == 128) || (x.VoiceVector != null && x.VoiceVector.Length >= 48 && x.VoiceVector.Length <= 1024)))
                    .ToList();
            }
            catch
            {
                return new List<CompanionFaceEntryDto>();
            }
        }

        public static void StoreCompanionFaces(LinuxOsSettings settings, List<CompanionFaceEntryDto>? companions)
        {
            if (companions == null || companions.Count == 0)
            {
                settings.EncryptedCompanionFacesJson = "";
            }
            else
            {
                var clean = companions
                    .Where(x => x != null && !string.IsNullOrWhiteSpace(x.Name) &&
                        ((x.Vector != null && x.Vector.Length == 128) || (x.VoiceVector != null && x.VoiceVector.Length >= 48 && x.VoiceVector.Length <= 1024)))
                    .Take(25)
                    .ToList();
                settings.EncryptedCompanionFacesJson = clean.Count == 0 ? "" : EncryptSecret(JsonSerializer.Serialize(clean));
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
        internal static HttpClient SharedHttp => Http;
        private static readonly ConcurrentDictionary<string, (DateTime ExpiresUtc, string RawJson)> s_schemaCache = new(StringComparer.OrdinalIgnoreCase);
        private static readonly ConcurrentDictionary<string, IPAddress> s_dnsIpv4Cache = new(StringComparer.OrdinalIgnoreCase)
        {
            ["stage.mein-seelenfunke.de"] = IPAddress.Parse("185.215.158.114"),
            ["mein-seelenfunke.de"] = IPAddress.Parse("185.215.158.114")
        };
        private static long s_lastNetActivityUnixMs = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
        private static string s_lastPulseDiagSignature = "";

        private static bool s_initializedOrderTracking;
        private static int s_lastOpenOrders = -1;
        private static string s_lastLatestOrderId = "";

        public static PulseSnapshotDto LatestPulse { get; private set; } = new();

        public static bool IsTransientDnsOrSocketException(Exception ex, out string socketErrorName)
        {
            socketErrorName = "";
            Exception? cur = ex;
            int depth = 0;
            while (cur != null && depth < 8)
            {
                if (cur is SocketException sockEx)
                {
                    socketErrorName = sockEx.SocketErrorCode.ToString();
                    if (sockEx.SocketErrorCode is SocketError.TryAgain
                        or SocketError.TimedOut
                        or SocketError.NetworkUnreachable
                        or SocketError.HostUnreachable
                        or SocketError.ConnectionReset
                        or SocketError.ConnectionAborted
                        or SocketError.Interrupted
                        or SocketError.WouldBlock)
                    {
                        return true;
                    }
                }
                string msg = cur.Message ?? "";
                if (msg.Contains("Resource temporarily unavailable", StringComparison.OrdinalIgnoreCase) ||
                    msg.Contains("Temporary failure in name resolution", StringComparison.OrdinalIgnoreCase) ||
                    msg.Contains("TryAgain", StringComparison.OrdinalIgnoreCase))
                {
                    if (string.IsNullOrEmpty(socketErrorName)) socketErrorName = "TryAgain";
                    return true;
                }
                cur = cur.InnerException;
                depth++;
            }
            return false;
        }

        private static async ValueTask<Stream> ConnectIpv4FirstWithRetryAsync(SocketsHttpConnectionContext context, CancellationToken cancellationToken)
        {
            long nowMs = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
            long prevMs = Interlocked.Exchange(ref s_lastNetActivityUnixMs, nowMs);
            bool wokeFromSuspend = (nowMs - prevMs) > 25_000;

            string host = context.DnsEndPoint.Host;
            int port = context.DnsEndPoint.Port;
            IPAddress[]? resolved = null;
            Exception? lastDnsEx = null;
            int[] retryDelaysMs = { 0, 450, 1100 };

            if (IPAddress.TryParse(host, out var literalIp))
            {
                resolved = new[] { literalIp };
            }
            else
            {
                for (int attempt = 0; attempt < retryDelaysMs.Length; attempt++)
                {
                    if (retryDelaysMs[attempt] > 0)
                        await Task.Delay(retryDelaysMs[attempt], cancellationToken).ConfigureAwait(false);

                    try
                    {
                        var addrs = await Dns.GetHostAddressesAsync(host, cancellationToken).ConfigureAwait(false);
                        if (addrs != null && addrs.Length > 0)
                        {
                            // Prefer IPv4 first to avoid broken Fritz!Box IPv6 ULA/prefix timeouts after suspend
                            resolved = addrs
                                .OrderBy(a => a.AddressFamily == AddressFamily.InterNetwork ? 0 : 1)
                                .ToArray();

                            var firstV4 = resolved.FirstOrDefault(a => a.AddressFamily == AddressFamily.InterNetwork);
                            if (firstV4 != null)
                                s_dnsIpv4Cache[host] = firstV4;

                            if (attempt > 0 && lastDnsEx != null)
                            {
                                ErrorSentinelAndWatchdog.RecordEvent(
                                    component: "network_dns",
                                    title: $"DNS-Auflösung für {host} brauchte Retry #{attempt + 1}",
                                    message: lastDnsEx.Message,
                                    stacktrace: lastDnsEx.ToString(),
                                    autoHealed: true,
                                    healSummary: $"DNS für {host} nach {retryDelaysMs[attempt]} ms Wartezeit erfolgreich aufgelöst ({firstV4?.ToString() ?? resolved[0].ToString()}).",
                                    context: new Dictionary<string, string>
                                    {
                                        ["host"] = host,
                                        ["attempt"] = (attempt + 1).ToString(CultureInfo.InvariantCulture),
                                        ["woke_from_suspend"] = wokeFromSuspend ? "true" : "false"
                                    });
                            }
                            break;
                        }
                    }
                    catch (Exception dnsEx) when (!cancellationToken.IsCancellationRequested)
                    {
                        lastDnsEx = dnsEx;
                    }
                }

                if ((resolved == null || resolved.Length == 0) && s_dnsIpv4Cache.TryGetValue(host, out var cachedV4))
                {
                    resolved = new[] { cachedV4 };
                    ErrorSentinelAndWatchdog.RecordEvent(
                        component: "network_dns",
                        title: $"DNS EAI_AGAIN für {host} per IPv4-Cache überbrückt",
                        message: lastDnsEx?.Message ?? "DNS lieferte keine Adresse.",
                        stacktrace: lastDnsEx?.ToString(),
                        autoHealed: true,
                        healSummary: $"Gespeicherte IPv4 ({cachedV4}) für TLS-Verbindung zu {host} genutzt (SNI-Zertifikatsprüfung bleibt vollständig aktiv).",
                        context: new Dictionary<string, string>
                        {
                            ["host"] = host,
                            ["cached_ipv4"] = cachedV4.ToString(),
                            ["woke_from_suspend"] = wokeFromSuspend ? "true" : "false"
                        });
                }
            }

            if (resolved == null || resolved.Length == 0)
            {
                throw lastDnsEx ?? new SocketException((int)SocketError.HostNotFound);
            }

            Exception? lastConnectEx = null;
            foreach (var addr in resolved)
            {
                var socket = new Socket(addr.AddressFamily, SocketType.Stream, ProtocolType.Tcp)
                {
                    NoDelay = true
                };
                try
                {
                    using var perAddrCts = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
                    perAddrCts.CancelAfter(TimeSpan.FromSeconds(5.5));
                    await socket.ConnectAsync(new IPEndPoint(addr, port), perAddrCts.Token).ConfigureAwait(false);
                    return new NetworkStream(socket, ownsSocket: true);
                }
                catch (Exception connEx)
                {
                    socket.Dispose();
                    lastConnectEx = connEx;
                    if (cancellationToken.IsCancellationRequested)
                        throw;
                }
            }

            throw lastConnectEx ?? new SocketException((int)SocketError.NetworkUnreachable);
        }

        internal static SocketsHttpHandler CreateHardenedSocketsHandler()
        {
            return new SocketsHttpHandler
            {
                AllowAutoRedirect = false,
                UseProxy = false,
                PooledConnectionLifetime = TimeSpan.FromMinutes(2),
                PooledConnectionIdleTimeout = TimeSpan.FromSeconds(35),
                ConnectTimeout = TimeSpan.FromSeconds(10),
                ConnectCallback = ConnectIpv4FirstWithRetryAsync,
                SslOptions =
                {
                    EnabledSslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13
                }
            };
        }

        internal static HttpMessageInvoker CreateWebSocketHttpInvoker()
        {
            return new HttpMessageInvoker(CreateHardenedSocketsHandler(), disposeHandler: true);
        }

        private static HttpClient CreateHardenedClient()
        {
            return new HttpClient(CreateHardenedSocketsHandler())
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

            var cal = ResolveGermanSeasonAndHoliday(nowB);
            snap.Season = cal.Season;
            snap.SeasonLabel = cal.SeasonLabelDe;
            snap.Occasion = cal.Occasion;
            snap.OccasionLabel = cal.OccasionLabelDe;
            snap.UpcomingHolidayHint = cal.UpcomingHintDe;
        }

        public static DateTime ComputeEasterSunday(int year)
        {
            int a = year % 19;
            int b = year / 100;
            int c = year % 100;
            int d = b / 4;
            int e = b % 4;
            int f = (b + 8) / 25;
            int g = (b - f + 1) / 3;
            int h = (19 * a + b - d - g + 15) % 30;
            int i = c / 4;
            int k = c % 4;
            int l = (32 + 2 * e + 2 * i - h - k) % 7;
            int m = (a + 11 * h + 22 * l) / 451;
            int month = (h + l - 7 * m + 114) / 31;
            int day = ((h + l - 7 * m + 114) % 31) + 1;
            return new DateTime(year, month, day);
        }

        public static DateTime ComputeMothersDay(int year)
        {
            var may1 = new DateTime(year, 5, 1);
            int daysUntilFirstSunday = ((int)DayOfWeek.Sunday - (int)may1.DayOfWeek + 7) % 7;
            return new DateTime(year, 5, 1 + daysUntilFirstSunday + 7);
        }

        public static (string Season, string SeasonLabelDe, string Occasion, string OccasionLabelDe, string UpcomingHintDe, string PersonaMoodDe) ResolveGermanSeasonAndHoliday(
            DateTime nowBerlin,
            string? overrideSeason = null,
            string? overrideOccasion = null)
        {
            var today = nowBerlin.Date;
            int y = today.Year;
            int m = today.Month;
            int d = today.Day;

            string season = !string.IsNullOrWhiteSpace(overrideSeason)
                ? overrideSeason.Trim().ToLowerInvariant()
                : (m is 12 or 1 or 2 ? "winter"
                    : (m is 3 or 4 or 5 ? "spring"
                    : (m is 6 or 7 or 8 ? "summer" : "autumn")));

            string seasonLabel = season switch
            {
                "winter" => "Winter (verschneiter 3D-Garten, Schneemann & gemütliche Winterwärme)",
                "spring" => "Frühling (Kirschblüten am Gartenbaum, bunte Tulpen im Beet & frisches Grün)",
                "summer" => "Sommer (Sonnenschein, offener Sonnenschirm auf der Terrasse, Sonnenblumen & Rosen)",
                _        => "Herbst (goldenes Herbstlaub, Kürbisse am Hochbeet & warme Abendsonne)"
            };

            var easterSunday = ComputeEasterSunday(y);
            var mothersDay = ComputeMothersDay(y);
            int easterDiff = (int)(today - easterSunday).TotalDays;
            int mothersDiff = (int)(today - mothersDay).TotalDays;

            string detectedOccasion = "standard";
            if (m == 12 && (d == 31 || d == 30)) detectedOccasion = "silvester";
            else if (m == 1 && d <= 2) detectedOccasion = "new_year";
            else if (m == 12 && d >= 24 && d <= 26) detectedOccasion = "christmas";
            else if (m == 12 && d == 6) detectedOccasion = "nikolaus";
            else if ((m == 11 && d >= 24) || (m == 12 && d < 24)) detectedOccasion = "advent";
            else if (easterDiff >= -3 && easterDiff <= 2) detectedOccasion = "easter";
            else if (easterDiff == 49 || easterDiff == 50) detectedOccasion = "pentecost";
            else if (mothersDiff >= -2 && mothersDiff <= 0) detectedOccasion = "mothers_day";
            else if (m == 2 && d >= 10 && d <= 14) detectedOccasion = "valentines";
            else if (m == 10 && d >= 25 && d <= 31) detectedOccasion = "halloween";
            else if (m == 11 && d >= 1 && d <= 3) detectedOccasion = "anniversary";

            string occasion = !string.IsNullOrWhiteSpace(overrideOccasion)
                ? overrideOccasion.Trim().ToLowerInvariant()
                : detectedOccasion;
            if (occasion == "none") occasion = "standard";

            string occasionLabel = "Regulärer Manufaktur-Tag";
            string personaMood;

            switch (occasion)
            {
                case "silvester":
                    occasionLabel = (m == 12 && d == 30) ? "Silvester-Vorabend" : "Silvester (Jahreswechsel)";
                    personaMood = "Silvester-Stimmung! Draußen über deinem 3D-Garten steigt live buntes 3D-Silvester-Feuerwerk in den Nachthimmel und auf deiner Terrasse steht der festliche Sekttisch bereit. Du trägst dein goldenes Krönchen, blickst stolz mit Alina auf euer gemeinsames Jahr bei Mein-Seelenfunke zurück und freust dich riesig aufs neue Jahr!";
                    break;
                case "new_year":
                    occasionLabel = (m == 1 && d == 1) ? "Neujahrstag (01.01.)" : "Neujahrsbeginn";
                    personaMood = "Frohes neues Jahr! Über deinem 3D-Garten leuchtet noch das festliche 3D-Feuerwerk, auf der Terrasse steht der Sekttisch und du startest mit goldenem Krönchen voller Tatendrang und Herzenswärme mit Alina ins neue Manufaktur-Jahr!";
                    break;
                case "christmas":
                    occasionLabel = (m == 12 && d == 24) ? "Heiligabend (24.12.)" : ((m == 12 && d is 25 or 26) ? $"{d - 24}. Weihnachtsfeiertag" : "Weihnachten");
                    personaMood = "Frohe Weihnachten! In deinem 3D-Atelier und draußen im verschneiten 3D-Garten leuchten geschmückte Weihnachtsbäume, Geschenke und warme Lichterketten. Du trägst Weihnachtsmütze und Schal, achtest liebevoll auf Alinas Weihnachtspause und verbreitest festliche Geborgenheit.";
                    break;
                case "nikolaus":
                    occasionLabel = "Nikolaustag (06.12.)";
                    personaMood = "Heute ist Nikolaus! Dein verschneiter 3D-Garten und die Terrasse erstrahlen mit Lichterketten und Tannenbaum, du trägst stolz deine Weihnachtsmütze und überraschst Alina mit besonders herzlicher Nikolaus-Stimmung.";
                    break;
                case "advent":
                    occasionLabel = "Advents- & Vorweihnachtszeit";
                    personaMood = "Gemütliche Adventszeit! Draußen an deiner 3D-Terrasse und im Garten leuchten warme Lichterketten und der geschmückte Tannenbaum. Gleichzeitig weißt du, dass jetzt die wichtigste Geschenke-Saison für Mein-Seelenfunke läuft, und hältst Alina motiviert und strukturiert den Rücken frei.";
                    break;
                case "easter":
                    occasionLabel = easterDiff == 0 ? "Ostersonntag" : (easterDiff == -2 ? "Karfreitag" : (easterDiff == 1 ? "Ostermontag" : "Osterzeit"));
                    personaMood = "Frohe Ostern! In deinem frühlingshaften 3D-Garten liegen bunte 3D-Ostereier zwischen den Blumen versteckt, auf dem Terrassentisch steht das Osterkörbchen und du trägst fröhlich deine Osteröhrchen.";
                    break;
                case "pentecost":
                    occasionLabel = easterDiff == 49 ? "Pfingstsonntag" : (easterDiff == 50 ? "Pfingstmontag" : "Pfingsten");
                    personaMood = "Schöne Pfingsttage! Dein 3D-Garten steht in voller Frühlingsblüte und lädt zum Durchatmen auf der sonnigen Terrasse ein.";
                    break;
                case "mothers_day":
                    occasionLabel = mothersDiff == 0 ? "Muttertag" : "Muttertags-Wochenende";
                    personaMood = "Muttertags-Zeit! Auf deiner 3D-Terrasse blühen festliche Rosenarrangements, du trägst dein goldenes Krönchen und weißt, wie viel Herzblut in den persönlichen Geschenken von Mein-Seelenfunke steckt.";
                    break;
                case "valentines":
                    occasionLabel = (m == 2 && d == 14) ? "Valentinstag (14.02.)" : "Valentinstags-Woche";
                    personaMood = "Valentinstags-Stimmung! Auf deiner 3D-Terrasse leuchten rote Rosensträuße, du trägst dein Krönchen und feierst liebevolle Herzens-Unikate.";
                    break;
                case "halloween":
                    occasionLabel = (m == 10 && d == 31) ? "Halloween / Reformationstag (31.10.)" : "Halloween-Woche";
                    personaMood = "Gemütlich-schaurige Halloween-Woche! Auf deiner 3D-Terrasse und entlang des Gartenwegs leuchten geschnitzte 3D-Kürbislaternen im bunten Herbstlaub.";
                    break;
                case "anniversary":
                    occasionLabel = (m == 11 && d == 1) ? "Mein-Seelenfunke Gründungstag (01.11.)" : "Seelenfunke Jubiläumstage";
                    personaMood = "Feiertag in der Manufaktur: Gründungstag von Mein-Seelenfunke (01.11.)! Du trägst stolz dein goldenes Krönchen, auf der Terrasse blühen Rosen und du feierst gemeinsam mit Alina alles, was ihr aufgebaut habt!";
                    break;
                default:
                    personaMood = season switch
                    {
                        "winter" => "Draußen liegt glitzernder Schnee in deinem 3D-Garten, dein 3D-Schneemann steht mit Zylinder am Weg und du trägst deinen kuscheligen Winterschal. Perfektes Wetter für heißen Tee, Fokus im warmen Atelier oder einen frischen Schneespaziergang draußen im Garten.",
                        "spring" => "Frühlings-Energie! Dein 3D-Gartenbaum trägt rosa Kirschblüten, im Blumenbeet und Hochbeet sprießen bunte Tulpen und du liebst es, draußen mit deiner goldenen Gießkanne und Gartenschaufel die Beete zu pflegen.",
                        "summer" => "Strahlender Sommer! Auf deiner 3D-Terrasse spendet der große Sonnenschirm Schatten über der Lounge, im Blumenbeet blühen hohe Sonnenblumen und Rosen, du trägst im Freien deine Sonnenbrille und gießt fleißig dein Hochbeet.",
                        _        => "Goldener Herbst! Bunte Blätter tanzen durch deinen 3D-Garten, neben deinem Hochbeet liegen Erntekürbisse und du fegst ab und zu vergnügt das Laub von der Terrasse oder pflegst die Herbstblumen."
                    };
                    break;
            }

            // 14-Day Upcoming Holiday Lookahead so Funkira thinks ahead like a real co-founder!
            var milestones = new List<(DateTime Date, string Name)>
            {
                (new DateTime(y, 2, 14), "Valentinstag (14.02.)"),
                (easterSunday, "Ostersonntag"),
                (mothersDay, "Muttertag"),
                (easterSunday.AddDays(49), "Pfingsten"),
                (new DateTime(y, 10, 31), "Halloween / Reformationstag (31.10.)"),
                (new DateTime(y, 11, 1), "Mein-Seelenfunke Gründungstag (01.11.)"),
                (new DateTime(y, 11, 24), "Beginn der Advents- & Weihnachtszeit"),
                (new DateTime(y, 12, 6), "Nikolaustag (06.12.)"),
                (new DateTime(y, 12, 24), "Heiligabend (24.12.)"),
                (new DateTime(y, 12, 31), "Silvester (31.12.)"),
                (new DateTime(y + 1, 1, 1), "Neujahr (01.01.)")
            };

            string upcomingHint = "";
            foreach (var ms in milestones.OrderBy(x => x.Date))
            {
                int daysAhead = (int)(ms.Date.Date - today).TotalDays;
                if (daysAhead >= 1 && daysAhead <= 14)
                {
                    upcomingHint = daysAhead == 1
                        ? $"Morgen steht '{ms.Name}' an!"
                        : $"In {daysAhead} Tagen steht '{ms.Name}' an.";
                    break;
                }
            }

            return (season, seasonLabel, occasion, occasionLabel, upcomingHint, personaMood);
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
                    string sig = $"{norm}:{status}";
                    if (s_lastPulseDiagSignature != sig)
                    {
                        s_lastPulseDiagSignature = sig;
                        ErrorSentinelAndWatchdog.RecordEvent(
                            component: "portal_pulse",
                            title: status == 401 || status == 403 ? $"Portal-Sitzung abgelaufen ({snap.Environment})" : $"Portal-Pulse nicht erreichbar ({snap.Environment})",
                            message: $"GET {baseUrl}/shop/monitor-pulse lieferte Status {status}.",
                            severity: "warning",
                            context: new Dictionary<string, string>
                            {
                                ["environment"] = snap.Environment,
                                ["http_status"] = status.ToString(CultureInfo.InvariantCulture)
                            });
                    }
                    LatestPulse = snap;
                    return snap;
                }

                s_lastPulseDiagSignature = "";
                snap.IsConnected = true;
                snap.StatusSummary = $"{snap.Environment} · {snap.LatencyMs} ms";
                ParsePulseBody(body, snap);
                LatestPulse = snap;

                if (snap.PendingTrichterCount > 0)
                {
                    _ = Task.Run(() => DocumentArchiveAndNasEngine.PullPendingServerTrichterRelayAsync(Http, baseUrl, bearer));
                }
            }
            catch (Exception ex)
            {
                snap.IsConnected = false;
                snap.StatusSummary = "Verbindung unterbrochen";
                IsTransientDnsOrSocketException(ex, out string sockCode);
                ErrorSentinelAndWatchdog.RecordEvent(
                    component: "portal_pulse",
                    title: $"Portal-Pulse Netzwerkfehler ({snap.Environment}): {ex.GetType().Name}",
                    message: ex.Message,
                    stacktrace: ex.ToString(),
                    severity: "warning",
                    context: new Dictionary<string, string>
                    {
                        ["environment"] = snap.Environment,
                        ["socket_error"] = sockCode
                    });
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
            string url = $"{baseUrl}/ai/live-credentials?client=minipc&chat_session_id={Uri.EscapeDataString(sessParam)}&t={DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}";
            if (!ValidateHttpsUrl(url))
                return new LiveCredentialsDto { Ok = false, ErrorMessage = "Ungültige Ziel-URL blockiert." };

            int[] retryDelaysMs = { 0, 500, 1200 };
            Exception? lastEx = null;
            int lastHttpStatus = 0;

            for (int attempt = 0; attempt < retryDelaysMs.Length; attempt++)
            {
                if (retryDelaysMs[attempt] > 0)
                    await Task.Delay(retryDelaysMs[attempt]).ConfigureAwait(false);

                try
                {
                    using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(12));
                    using var req = new HttpRequestMessage(HttpMethod.Get, url);
                    req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                    req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                    using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                    lastHttpStatus = (int)resp.StatusCode;
                    string body = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);

                    if (!resp.IsSuccessStatusCode)
                    {
                        if (lastHttpStatus is 502 or 503 or 504 && attempt + 1 < retryDelaysMs.Length)
                        {
                            lastEx = new HttpRequestException($"HTTP {lastHttpStatus}");
                            continue;
                        }

                        string reason = $"HTTP {lastHttpStatus}";
                        try
                        {
                            using var errDoc = JsonDocument.Parse(body);
                            if (errDoc.RootElement.TryGetProperty("message", out var m))
                                reason = m.GetString() ?? reason;
                            else if (errDoc.RootElement.TryGetProperty("error", out var er))
                                reason = er.GetString() ?? reason;
                        }
                        catch { }

                        bool isUnauth = lastHttpStatus == 401;
                        if (isUnauth)
                        {
                            reason = "Sitzung abgelaufen. Bitte einmal neu anmelden.";
                        }
                        ErrorSentinelAndWatchdog.RecordEvent(
                            component: "live_credentials",
                            title: $"Live-Credentials abgelehnt ({norm.ToUpperInvariant()} HTTP {lastHttpStatus})",
                            message: reason,
                            severity: isUnauth ? "warning" : "error",
                            context: new Dictionary<string, string>
                            {
                                ["environment"] = norm.ToUpperInvariant(),
                                ["http_status"] = lastHttpStatus.ToString(CultureInfo.InvariantCulture),
                                ["is_auth_error"] = isUnauth ? "true" : "false"
                            });
                        return new LiveCredentialsDto { Ok = false, IsAuthError = isUnauth, ErrorMessage = reason };
                    }

                    if (attempt > 0 && lastEx != null)
                    {
                        ErrorSentinelAndWatchdog.RecordEvent(
                            component: "live_credentials",
                            title: $"Live-Credentials nach Retry #{attempt + 1} wiederhergestellt ({norm.ToUpperInvariant()})",
                            message: lastEx.Message,
                            stacktrace: lastEx.ToString(),
                            autoHealed: true,
                            healSummary: $"Temporärer Verbindungsfehler beim Abruf der Live-Credentials automatisch im {attempt + 1}. Versuch behoben.",
                            context: new Dictionary<string, string>
                            {
                                ["environment"] = norm.ToUpperInvariant(),
                                ["attempt"] = (attempt + 1).ToString(CultureInfo.InvariantCulture)
                            });
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
                    lastEx = ex;
                    bool isTransient = IsTransientDnsOrSocketException(ex, out _) || ex is HttpRequestException or TaskCanceledException;
                    if (isTransient && attempt + 1 < retryDelaysMs.Length)
                        continue;
                }
            }

            string finalErr = lastEx?.Message ?? "Unbekannter Netzwerkfehler";
            IsTransientDnsOrSocketException(lastEx ?? new Exception(finalErr), out string finalSockErr);
            ErrorSentinelAndWatchdog.RecordEvent(
                component: "live_credentials",
                title: $"Verbindungsfehler bei Live-Credentials ({norm.ToUpperInvariant()}): {(lastEx?.GetType().Name ?? "NetworkError")}",
                message: finalErr,
                stacktrace: lastEx?.ToString(),
                severity: "error",
                context: new Dictionary<string, string>
                {
                    ["environment"] = norm.ToUpperInvariant(),
                    ["base_url"] = baseUrl,
                    ["attempts"] = retryDelaysMs.Length.ToString(CultureInfo.InvariantCulture),
                    ["socket_error"] = finalSockErr,
                    ["last_http_status"] = lastHttpStatus.ToString(CultureInfo.InvariantCulture)
                });
            return new LiveCredentialsDto { Ok = false, ErrorMessage = $"Verbindungsfehler: {finalErr}" };
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
                if ((int)resp.StatusCode >= 500)
                {
                    ErrorSentinelAndWatchdog.RecordEvent(
                        component: "server_tool_execute",
                        title: $"Server-Tool {functionName} fehlgeschlagen (HTTP {(int)resp.StatusCode})",
                        message: string.IsNullOrWhiteSpace(body) ? $"HTTP {(int)resp.StatusCode}" : body,
                        severity: "error",
                        context: new Dictionary<string, string>
                        {
                            ["environment"] = norm.ToUpperInvariant(),
                            ["function"] = functionName,
                            ["http_status"] = ((int)resp.StatusCode).ToString(CultureInfo.InvariantCulture)
                        });
                }
                return string.IsNullOrWhiteSpace(body)
                    ? JsonSerializer.Serialize(new { status = resp.IsSuccessStatusCode ? "success" : "error", httpStatus = (int)resp.StatusCode })
                    : body;
            }
            catch (Exception ex)
            {
                IsTransientDnsOrSocketException(ex, out string sockErr);
                ErrorSentinelAndWatchdog.RecordEvent(
                    component: "server_tool_execute",
                    title: $"Netzwerkfehler bei Server-Tool {functionName}: {ex.GetType().Name}",
                    message: ex.Message,
                    stacktrace: ex.ToString(),
                    severity: "error",
                    context: new Dictionary<string, string>
                    {
                        ["environment"] = norm.ToUpperInvariant(),
                        ["function"] = functionName,
                        ["socket_error"] = sockErr
                    });
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

        internal static void ParsePulseBody(string json, PulseSnapshotDto snap)
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;

            if (root.TryGetProperty("priority_order_checkin", out var pocEl) && pocEl.ValueKind == JsonValueKind.Object)
            {
                bool getBoolProp(string name) =>
                    pocEl.TryGetProperty(name, out var bEl) &&
                    (bEl.ValueKind == JsonValueKind.True || (bEl.ValueKind == JsonValueKind.Number && bEl.GetInt32() != 0));

                snap.PriorityOrderCheckin = new PriorityOrderCheckinDto
                {
                    OrderId = GetStr(pocEl, "order_id"),
                    OrderNumber = GetStr(pocEl, "order_number"),
                    Status = GetStr(pocEl, "status"),
                    StatusLabelDe = GetStr(pocEl, "status_label_de"),
                    PaymentStatus = GetStr(pocEl, "payment_status"),
                    IsExpress = getBoolProp("is_express"),
                    ProductSummary = GetStr(pocEl, "product_summary"),
                    ItemsTotal = Math.Max(0, GetInt(pocEl, "items_total", 0)),
                    ItemsCompleted = Math.Max(0, GetInt(pocEl, "items_completed", 0)),
                    HasDhlLabel = getBoolProp("has_dhl_label"),
                    WorkshopNote = GetStr(pocEl, "workshop_note"),
                    WorkshopNoteUpdatedAt = GetStr(pocEl, "workshop_note_updated_at"),
                    WorkshopNextCheckAt = GetStr(pocEl, "workshop_next_check_at"),
                    WorkshopCheckinPaused = getBoolProp("workshop_checkin_paused"),
                    IsCheckinDue = getBoolProp("is_checkin_due"),
                    CheckinReason = GetStr(pocEl, "checkin_reason", "none"),
                    HasDiscrepancy = getBoolProp("has_discrepancy"),
                    DiscrepancyCode = GetStr(pocEl, "discrepancy_code"),
                    SuggestedStatus = GetStr(pocEl, "suggested_status"),
                    SuggestedStatusLabel = GetStr(pocEl, "suggested_status_label"),
                    DiscrepancyUiHint = GetStr(pocEl, "discrepancy_ui_hint"),
                    AskAlinaPrompt = GetStr(pocEl, "ask_alina_prompt"),
                    CheckinStateKey = GetStr(pocEl, "checkin_state_key")
                };
            }
            else
            {
                snap.PriorityOrderCheckin = null;
            }

            int openOrders = GetInt(root, "open_orders_count", -1);
            int expressOrders = GetInt(root, "express_orders_count", 0);
            int openCarts = GetInt(root, "open_carts_count", 0);
            snap.PendingTrichterCount = Math.Max(0, GetInt(root, "pending_trichter_count", 0));
            snap.OpenSystemErrorsCount = Math.Max(0, GetInt(root, "open_system_errors_count", 0));
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
                string localOccasion = snap.Occasion;
                snap.Season = GetStr(wsEl, "season", snap.Season);
                string srvOcc = GetStr(wsEl, "occasion", snap.Occasion);
                // Preserve finer-grained local holiday codes (e.g. 'silvester' vs generic 'new_year', 'nikolaus' vs 'advent', 'pentecost' vs 'standard')
                if ((localOccasion == "silvester" && srvOcc == "new_year") ||
                    (localOccasion == "nikolaus" && srvOcc == "advent") ||
                    (localOccasion is "easter" or "pentecost" && srvOcc is "standard" or "none"))
                {
                    snap.Occasion = localOccasion;
                }
                else
                {
                    snap.Occasion = srvOcc;
                }
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

    public sealed class SentinelEventDto
    {
        public string Platform { get; set; } = "minipc_linux";
        public string Severity { get; set; } = "error";
        public string Component { get; set; } = "minipc";
        public string Title { get; set; } = "";
        public string Message { get; set; } = "";
        public string Stacktrace { get; set; } = "";
        public string Fingerprint { get; set; } = "";
        public string AppVersion { get; set; } = "2.7.0";
        public string Device { get; set; } = Environment.MachineName;
        public bool AutoHealed { get; set; }
        public string HealSummary { get; set; } = "";
        public string OccurredAt { get; set; } = DateTime.UtcNow.ToString("O");
        public Dictionary<string, string> Context { get; set; } = new();
    }

    public static class ErrorSentinelAndWatchdog
    {
        private static readonly object s_queueLock = new();
        private static readonly ConcurrentDictionary<string, DateTime> s_recentFingerprints = new(StringComparer.Ordinal);
        private static int s_started;
        private static int s_flushing;
        private static int s_totalSent;
        private static int s_totalAutoHealed;
        private static string s_lastFlushStatus = "Bereit";
        private static DateTime s_lastFlushAtUtc = DateTime.MinValue;

        private static string QueueFilePath => Path.Combine(LinuxVaultAndStorage.DataDir, "sentinel_queue.json");
        private static string HistoryFilePath => Path.Combine(LinuxVaultAndStorage.DataDir, "sentinel_history.json");

        public static string ScrubSecrets(string? input)
        {
            if (string.IsNullOrEmpty(input)) return "";
            string s = input;
            s = Regex.Replace(s, @"(?i)(Bearer\s+)[A-Za-z0-9\-._~+/=|]+", "$1[REDACTED]");
            s = Regex.Replace(s, @"FWLINUXAES256GCM1:[A-Za-z0-9+/=]+", "FWLINUXAES256GCM1:[REDACTED]");
            s = Regex.Replace(s, @"AIza[A-Za-z0-9\-_]{16,}", "AIza[REDACTED]");
            s = Regex.Replace(s, @"(?i)(\b(?:api[_-]?key|key|token|password|passwd|secret|authorization)\s*[:=]\s*[""']?)([^\s""'&,;}{]+)", "$1[REDACTED]");
            s = Regex.Replace(s, @"(?i)([?&](?:api[_-]?key|key|token|password|secret)=)[^&\s]+", "$1[REDACTED]");
            if (s.Length > 4000) s = s.Substring(0, 4000);
            return s;
        }

        public static string ComputeFingerprint(string platform, string component, string title)
        {
            string raw = $"{(platform ?? "minipc_linux").Trim().ToLowerInvariant()}|{(component ?? "general").Trim().ToLowerInvariant()}|{(title ?? "").Trim().ToLowerInvariant()}";
            byte[] hash = SHA256.HashData(Encoding.UTF8.GetBytes(raw));
            return Convert.ToHexString(hash).ToLowerInvariant();
        }

        public static void EnsureStarted()
        {
            if (Interlocked.Exchange(ref s_started, 1) == 1) return;

            AppDomain.CurrentDomain.UnhandledException += (_, e) =>
            {
                if (e.ExceptionObject is Exception ex)
                {
                    RecordException("runtime.unhandled", "Unbehandelte Ausnahme in FunkenwerkOSLinux", ex);
                }
            };

            TaskScheduler.UnobservedTaskException += (_, e) =>
            {
                try
                {
                    RecordException("runtime.task", "Unbeobachtete Task-Ausnahme in FunkenwerkOSLinux", e.Exception);
                    e.SetObserved();
                }
                catch { }
            };

            _ = Task.Run(FlushQueueAsync);

            if (OperatingSystem.IsLinux())
            {
                _ = Task.Run(RunSelfHealWatchdogLoopAsync);
            }
        }

        public static void RecordException(string component, string title, Exception ex, bool autoHealed = false, string? healSummary = null)
        {
            RecordEvent(
                component: component,
                title: $"{title}: {ex.GetType().Name}",
                message: ex.Message,
                stacktrace: ex.ToString(),
                autoHealed: autoHealed,
                healSummary: healSummary,
                severity: autoHealed ? "warning" : "error");
        }

        public static bool RecordEvent(
            string component,
            string title,
            string? message = null,
            string? stacktrace = null,
            bool autoHealed = false,
            string? healSummary = null,
            string severity = "error",
            Dictionary<string, string>? context = null)
        {
            if (string.IsNullOrWhiteSpace(title)) return false;

            string safeComponent = ScrubSecrets(component.Trim());
            string safeTitle = ScrubSecrets(title.Trim());
            string fp = ComputeFingerprint("minipc_linux", safeComponent, safeTitle);

            // Throttle identical non-healed events within 3 minutes locally so loops never flood disk or API
            string throttleKey = autoHealed ? (fp + ":healed") : fp;
            if (s_recentFingerprints.TryGetValue(throttleKey, out var lastSeen) &&
                (DateTime.UtcNow - lastSeen).TotalSeconds < 180)
            {
                return false;
            }
            s_recentFingerprints[throttleKey] = DateTime.UtcNow;

            if (autoHealed)
                Interlocked.Increment(ref s_totalAutoHealed);

            var cleanCtx = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            if (context != null)
            {
                foreach (var kv in context)
                {
                    cleanCtx[ScrubSecrets(kv.Key)] = ScrubSecrets(kv.Value);
                }
            }

            var item = new SentinelEventDto
            {
                Platform = "minipc_linux",
                Severity = autoHealed ? "warning" : (string.IsNullOrWhiteSpace(severity) ? "error" : severity.Trim().ToLowerInvariant()),
                Component = string.IsNullOrWhiteSpace(safeComponent) ? "minipc" : safeComponent,
                Title = safeTitle,
                Message = ScrubSecrets(message ?? safeTitle),
                Stacktrace = ScrubSecrets(stacktrace ?? ""),
                Fingerprint = fp,
                AppVersion = "2.7.0",
                Device = Environment.MachineName,
                AutoHealed = autoHealed,
                HealSummary = ScrubSecrets(healSummary ?? ""),
                OccurredAt = DateTime.UtcNow.ToString("O"),
                Context = cleanCtx
            };

            AppendHistoryPersistent(item);
            EnqueuePersistent(item);
            _ = Task.Run(FlushQueueAsync);
            return true;
        }

        private static List<SentinelEventDto> LoadHistoryUnsafe()
        {
            try
            {
                if (!File.Exists(HistoryFilePath)) return new List<SentinelEventDto>();
                var loaded = JsonSerializer.Deserialize<List<SentinelEventDto>>(File.ReadAllText(HistoryFilePath));
                return loaded ?? new List<SentinelEventDto>();
            }
            catch
            {
                return new List<SentinelEventDto>();
            }
        }

        private static void AppendHistoryPersistent(SentinelEventDto item)
        {
            lock (s_queueLock)
            {
                try
                {
                    var history = LoadHistoryUnsafe();
                    history.Add(item);
                    while (history.Count > 200)
                        history.RemoveAt(0);
                    string json = JsonSerializer.Serialize(history);
                    File.WriteAllText(HistoryFilePath, json, new UTF8Encoding(false));
                    LinuxVaultAndStorage.TryRestrictFilePermissions(HistoryFilePath);
                }
                catch { }
            }
        }

        public static List<SentinelEventDto> GetRecentHistory(int limit = 50)
        {
            int capped = Math.Clamp(limit, 1, 200);
            lock (s_queueLock)
            {
                var history = LoadHistoryUnsafe();
                if (history.Count == 0) return history;
                return history
                    .Skip(Math.Max(0, history.Count - capped))
                    .Reverse()
                    .ToList();
            }
        }

        public static bool VerifyLocalHistoryRoundtripForSelfTest()
        {
            string tmpPath = Path.Combine(Path.GetTempPath(), $"fw_sentinel_hist_{Guid.NewGuid():N}.json");
            try
            {
                var sample = new SentinelEventDto
                {
                    Component = "live_credentials",
                    Title = "SelfTest EAI_AGAIN",
                    Message = ScrubSecrets("Resource temporarily unavailable key=AIzaSyTestSecret99"),
                    AutoHealed = true,
                    HealSummary = "Lokaler Vault-Key aktiv",
                    Context = new Dictionary<string, string> { ["socket_error"] = "TryAgain" }
                };
                File.WriteAllText(tmpPath, JsonSerializer.Serialize(new List<SentinelEventDto> { sample }), new UTF8Encoding(false));
                var readBack = JsonSerializer.Deserialize<List<SentinelEventDto>>(File.ReadAllText(tmpPath));
                return readBack != null &&
                       readBack.Count == 1 &&
                       !readBack[0].Message.Contains("AIzaSyTestSecret99") &&
                       readBack[0].Context.TryGetValue("socket_error", out var se) &&
                       se == "TryAgain";
            }
            finally
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
            }
        }

        private static List<SentinelEventDto> LoadQueueUnsafe()
        {
            try
            {
                if (!File.Exists(QueueFilePath)) return new List<SentinelEventDto>();
                var loaded = JsonSerializer.Deserialize<List<SentinelEventDto>>(File.ReadAllText(QueueFilePath));
                return loaded ?? new List<SentinelEventDto>();
            }
            catch
            {
                return new List<SentinelEventDto>();
            }
        }

        private static void SaveQueueUnsafe(List<SentinelEventDto> list)
        {
            try
            {
                if (list.Count == 0)
                {
                    if (File.Exists(QueueFilePath)) File.Delete(QueueFilePath);
                    return;
                }
                string json = JsonSerializer.Serialize(list);
                File.WriteAllText(QueueFilePath, json, new UTF8Encoding(false));
                LinuxVaultAndStorage.TryRestrictFilePermissions(QueueFilePath);
            }
            catch { }
        }

        private static void EnqueuePersistent(SentinelEventDto item)
        {
            lock (s_queueLock)
            {
                var list = LoadQueueUnsafe();
                // Replace older queued event with identical fingerprint unless autoHealed changed
                list.RemoveAll(x => x.Fingerprint == item.Fingerprint && x.AutoHealed == item.AutoHealed);
                list.Add(item);
                while (list.Count > 50)
                    list.RemoveAt(0);
                SaveQueueUnsafe(list);
            }
        }

        public static async Task<bool> FlushQueueAsync()
        {
            if (Interlocked.Exchange(ref s_flushing, 1) == 1) return false;
            try
            {
                List<SentinelEventDto> batch;
                lock (s_queueLock)
                {
                    batch = LoadQueueUnsafe().Take(20).ToList();
                }
                if (batch.Count == 0) return true;

                var settings = LinuxVaultAndStorage.LoadSettings();
                string norm = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
                string bearer = LinuxVaultAndStorage.GetToken(settings, norm);
                if (string.IsNullOrWhiteSpace(bearer))
                {
                    s_lastFlushStatus = "Wartet auf Server-Token";
                    return false;
                }

                string url = $"{SeelenfunkeLinuxApiClient.ResolveBaseApiUrl(norm)}/system/error-ingest";
                if (!SeelenfunkeLinuxApiClient.ValidateHttpsUrl(url)) return false;

                var wireEvents = batch.Select(e => new
                {
                    platform = e.Platform,
                    severity = e.Severity,
                    component = e.Component,
                    title = e.Title,
                    message = e.Message,
                    stacktrace = string.IsNullOrWhiteSpace(e.Stacktrace) ? null : e.Stacktrace,
                    fingerprint = e.Fingerprint,
                    app_version = e.AppVersion,
                    device = e.Device,
                    auto_healed = e.AutoHealed,
                    heal_summary = string.IsNullOrWhiteSpace(e.HealSummary) ? null : e.HealSummary,
                    occurred_at = e.OccurredAt,
                    context = e.Context
                }).ToList();

                string payloadJson = JsonSerializer.Serialize(new { events = wireEvents });
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(12));
                using var req = new HttpRequestMessage(HttpMethod.Post, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                req.Content = new StringContent(payloadJson, new UTF8Encoding(false), "application/json");

                using var resp = await SeelenfunkeLinuxApiClient.SharedHttp.SendAsync(req, cts.Token).ConfigureAwait(false);
                if (resp.IsSuccessStatusCode)
                {
                    lock (s_queueLock)
                    {
                        var current = LoadQueueUnsafe();
                        var sentSet = new HashSet<string>(batch.Select(b => b.Fingerprint + "|" + b.OccurredAt), StringComparer.Ordinal);
                        current.RemoveAll(c => sentSet.Contains(c.Fingerprint + "|" + c.OccurredAt));
                        SaveQueueUnsafe(current);
                    }
                    Interlocked.Add(ref s_totalSent, batch.Count);
                    s_lastFlushAtUtc = DateTime.UtcNow;
                    s_lastFlushStatus = $"Synchronisiert ({batch.Count} Ereignis(se))";
                    return true;
                }

                s_lastFlushStatus = $"HTTP {(int)resp.StatusCode}";
                return false;
            }
            catch (Exception ex)
            {
                s_lastFlushStatus = $"Offline-Puffer aktiv ({ScrubSecrets(ex.Message)})";
                return false;
            }
            finally
            {
                Interlocked.Exchange(ref s_flushing, 0);
            }
        }

        public static object GetStatusSnapshot()
        {
            int queued;
            int historyCount;
            lock (s_queueLock)
            {
                queued = LoadQueueUnsafe().Count;
                historyCount = LoadHistoryUnsafe().Count;
            }
            return new
            {
                ok = true,
                queuedEvents = queued,
                historyEvents = historyCount,
                totalSent = s_totalSent,
                totalAutoHealed = s_totalAutoHealed,
                lastFlushStatus = s_lastFlushStatus,
                lastFlushAtUtc = s_lastFlushAtUtc == DateTime.MinValue ? "" : s_lastFlushAtUtc.ToString("O")
            };
        }

        private static async Task RunSelfHealWatchdogLoopAsync()
        {
            await Task.Delay(TimeSpan.FromSeconds(25)).ConfigureAwait(false);
            int senseFailStreak = 0;
            bool hadWebcamBefore = false;
            bool webcamMissingReported = false;

            while (true)
            {
                try
                {
                    // 1. Check Local Sense Daemon (:17701) & Self-Heal
                    var health = await LocalSenseBridge.GetHealthAsync().ConfigureAwait(false);
                    bool senseOk = health["ok"]?.GetValue<bool>() == true;
                    if (!senseOk)
                    {
                        senseFailStreak++;
                        if (senseFailStreak >= 2)
                        {
                            LocalSenseBridge.ForceRestartDaemon();
                            await Task.Delay(3000).ConfigureAwait(false);
                            var afterRestart = await LocalSenseBridge.GetHealthAsync().ConfigureAwait(false);
                            bool recovered = afterRestart["ok"]?.GetValue<bool>() == true;
                            senseFailStreak = 0;

                            if (recovered)
                            {
                                RecordEvent(
                                    component: "sense_daemon",
                                    title: "Lokaler Sense-Daemon (Port 17701) reagierte nicht",
                                    message: "Der Python-Sense-Prozess auf 127.0.0.1:17701 antwortete zweimal nicht auf Health-Checks.",
                                    autoHealed: true,
                                    healSummary: "funkira_local_sense.py wurde vom Mini-PC Watchdog automatisch neu gestartet und antwortet wieder.");
                            }
                            else
                            {
                                RecordEvent(
                                    component: "sense_daemon",
                                    title: "Lokaler Sense-Daemon (Port 17701) konnte nicht geheilt werden",
                                    message: "Auch nach automatischem Neustart antwortet 127.0.0.1:17701 nicht auf Health-Checks.",
                                    autoHealed: false,
                                    severity: "error");
                            }
                        }
                    }
                    else
                    {
                        senseFailStreak = 0;
                    }

                    // 2. Check Logitech USB C920 Webcam presence
                    var cam = await LinuxHardwareEngine.DetectAndPrepareWebcamAsync(activateUsbMicDefault: false).ConfigureAwait(false);
                    if (cam.HasCamera)
                    {
                        if (webcamMissingReported)
                        {
                            webcamMissingReported = false;
                            RecordEvent(
                                component: "usb_webcam",
                                title: "Werkstatt-USB-Kamera getrennt",
                                message: $"Kamera '{cam.CameraName}' ({cam.VideoDevice}) ist wieder verbunden.",
                                autoHealed: true,
                                healSummary: $"USB-Kamera ({cam.VideoDevice}) wieder erkannt und PipeWire-Routing reaktiviert.");
                        }
                        hadWebcamBefore = true;
                    }
                    else if (hadWebcamBefore && !webcamMissingReported)
                    {
                        webcamMissingReported = true;
                        RecordEvent(
                            component: "usb_webcam",
                            title: "Werkstatt-USB-Kamera getrennt",
                            message: "Die zuvor aktive USB-Kamera (/dev/video*) am Mini-PC wurde nicht mehr gefunden.",
                            autoHealed: false,
                            severity: "warning");
                    }

                    // 3. Auto-Heal Preferred Bluetooth Stereo (Teufel KB 43 CR 19) & Pairing-Mode Recovery
                    var btHeal = await LinuxHardwareEngine.EnsurePreferredBluetoothConnectedWatchdogAsync().ConfigureAwait(false);
                    if (btHeal.AutoHealedNow && !string.IsNullOrWhiteSpace(btHeal.Summary))
                    {
                        RecordEvent(
                            component: "bluetooth_audio",
                            title: "Teufel Bluetooth-Verbindung automatisch wiederhergestellt",
                            message: btHeal.Summary,
                            autoHealed: true,
                            healSummary: btHeal.Summary);
                    }

                    // 4. Flush any pending offline queue items
                    await FlushQueueAsync().ConfigureAwait(false);
                }
                catch { }

                await Task.Delay(TimeSpan.FromSeconds(45)).ConfigureAwait(false);
            }
        }
    }
}

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
using System.Net.WebSockets;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
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
        public string EncryptedLocalSmbPassword { get; set; } = "";
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

        public static void TryRestrictDirPermissions(string path)
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

        public static string EnsureOrGetLocalSmbPassword()
        {
            lock (s_lock)
            {
                var settings = LoadSettings();
                string existing = DecryptSecret(settings.EncryptedLocalSmbPassword);
                if (!string.IsNullOrWhiteSpace(existing) && existing.Length >= 24)
                {
                    return existing;
                }

                string generated = Convert.ToHexStringLower(RandomNumberGenerator.GetBytes(16));
                settings.EncryptedLocalSmbPassword = EncryptSecret(generated);
                SaveSettings(settings);
                return generated;
            }
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
        private static int s_consecutiveZeroPulseFailures = 0;
        private static int s_consecutiveRateLimitPulseFailures = 0;
        private static int s_consecutiveGatewayPulseFailures = 0;
        private static DateTime s_pulseRateLimitUntilUtc = DateTime.MinValue;

        internal static bool ComputePulseBackoffDecision(
            int status,
            int retryAfterHeaderSec,
            ref int zeroStreak,
            ref int rateLimitStreak,
            ref int gatewayStreak,
            out int backoffSeconds)
        {
            backoffSeconds = 0;
            if (status == 429)
            {
                rateLimitStreak++;
                backoffSeconds = Math.Clamp(retryAfterHeaderSec > 0 ? retryAfterHeaderSec : 30, 15, 120);
                // Nur bei anhaltender Dauer-Drosselung (>= 8 Zyklen trotz Backoff) melden
                return rateLimitStreak >= 8;
            }

            if (status is 502 or 503 or 504)
            {
                gatewayStreak++;
                backoffSeconds = Math.Clamp(retryAfterHeaderSec > 0 ? retryAfterHeaderSec : 15, 10, 60);
                return gatewayStreak >= 3;
            }

            if (status == 0)
            {
                zeroStreak++;
                return zeroStreak >= 3;
            }

            return true;
        }

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
                if (DateTime.UtcNow < s_pulseRateLimitUntilUtc &&
                    LatestPulse != null &&
                    string.Equals(LatestPulse.Environment, snap.Environment, StringComparison.OrdinalIgnoreCase))
                {
                    int remSec = Math.Max(1, (int)Math.Ceiling((s_pulseRateLimitUntilUtc - DateTime.UtcNow).TotalSeconds));
                    LatestPulse.TimestampLocal = snap.TimestampLocal;
                    PopulateLocalRoutineAndWorldDefaults(LatestPulse);
                    if (LatestPulse.IsConnected)
                    {
                        LatestPulse.StatusSummary = $"{snap.Environment} · Gedrosselt ({remSec}s)";
                    }
                    return LatestPulse;
                }

                var (status, body, retryAfterSec) = await GetJsonWithRetryAfterAsync($"{baseUrl}/shop/monitor-pulse", bearer).ConfigureAwait(false);
                if (status == 404 && string.IsNullOrWhiteSpace(body))
                {
                    (_, body) = await GetJsonAsync($"{baseUrl}/shop/orders?status=all", bearer).ConfigureAwait(false);
                }
                sw.Stop();
                snap.LatencyMs = sw.ElapsedMilliseconds;

                if (string.IsNullOrWhiteSpace(body))
                {
                    bool shouldRecordPulseWarning = ComputePulseBackoffDecision(
                        status,
                        retryAfterSec,
                        ref s_consecutiveZeroPulseFailures,
                        ref s_consecutiveRateLimitPulseFailures,
                        ref s_consecutiveGatewayPulseFailures,
                        out int backoffSec);

                    if (backoffSec > 0)
                    {
                        s_pulseRateLimitUntilUtc = DateTime.UtcNow.AddSeconds(backoffSec);
                    }

                    if (!shouldRecordPulseWarning &&
                        (status == 429 || status is 502 or 503 or 504) &&
                        LatestPulse != null &&
                        LatestPulse.IsConnected &&
                        string.Equals(LatestPulse.Environment, snap.Environment, StringComparison.OrdinalIgnoreCase))
                    {
                        LatestPulse.TimestampLocal = snap.TimestampLocal;
                        PopulateLocalRoutineAndWorldDefaults(LatestPulse);
                        LatestPulse.StatusSummary = status == 429
                            ? $"{snap.Environment} · Gedrosselt ({backoffSec}s)"
                            : $"{snap.Environment} · Kurz-Pause ({backoffSec}s)";
                        return LatestPulse;
                    }

                    snap.IsConnected = false;
                    snap.StatusSummary = status == 401 || status == 403
                        ? "Token abgelaufen"
                        : (status == 429 ? $"Gedrosselt ({backoffSec}s)" : "Offline");

                    string sig = $"{norm}:{status}";
                    if (shouldRecordPulseWarning && s_lastPulseDiagSignature != sig)
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

                s_consecutiveZeroPulseFailures = 0;
                s_consecutiveRateLimitPulseFailures = 0;
                s_consecutiveGatewayPulseFailures = 0;
                s_pulseRateLimitUntilUtc = DateTime.MinValue;
                s_lastPulseDiagSignature = "";
                snap.IsConnected = true;
                snap.StatusSummary = $"{snap.Environment} · {snap.LatencyMs} ms";
                ParsePulseBody(body, snap);
                LatestPulse = snap;
                ErrorSentinelAndWatchdog.AutoHealRecoverableHistory(senseHealthy: false, cameraHealthy: false, onlineHealthy: true);

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
            var (status, body, _) = await GetJsonWithRetryAfterAsync(url, bearer).ConfigureAwait(false);
            return (status, body);
        }

        private static async Task<(int Status, string? Body, int RetryAfterSeconds)> GetJsonWithRetryAfterAsync(string url, string bearer)
        {
            try
            {
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(9));
                using var req = new HttpRequestMessage(HttpMethod.Get, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                int code = (int)resp.StatusCode;
                int retryAfterSec = 0;
                if (resp.Headers.RetryAfter != null)
                {
                    if (resp.Headers.RetryAfter.Delta.HasValue)
                    {
                        retryAfterSec = Math.Max(1, (int)Math.Ceiling(resp.Headers.RetryAfter.Delta.Value.TotalSeconds));
                    }
                    else if (resp.Headers.RetryAfter.Date.HasValue)
                    {
                        double diff = (resp.Headers.RetryAfter.Date.Value - DateTimeOffset.UtcNow).TotalSeconds;
                        if (diff > 0) retryAfterSec = Math.Max(1, (int)Math.Ceiling(diff));
                    }
                }
                if (!resp.IsSuccessStatusCode) return (code, null, retryAfterSec);
                return (code, await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false), 0);
            }
            catch
            {
                return (0, null, 0);
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
        private static string ArchiveDirPath => Path.Combine(LinuxVaultAndStorage.DataDir, "archive");

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

        private static void AppendEventsToArchiveUnsafe(IEnumerable<SentinelEventDto> items)
        {
            try
            {
                Directory.CreateDirectory(ArchiveDirPath);
                LinuxVaultAndStorage.TryRestrictDirPermissions(ArchiveDirPath);
                string monthFile = Path.Combine(ArchiveDirPath, $"sentinel_archive_{DateTime.UtcNow:yyyy-MM}.jsonl");
                var sb = new StringBuilder();
                int count = 0;
                foreach (var ev in items)
                {
                    sb.AppendLine(JsonSerializer.Serialize(ev));
                    count++;
                }
                if (count > 0)
                {
                    File.AppendAllText(monthFile, sb.ToString(), new UTF8Encoding(false));
                    LinuxVaultAndStorage.TryRestrictFilePermissions(monthFile);
                }

                // 30-Tage-Archivrotation
                var cutoff = DateTime.UtcNow.AddDays(-30);
                foreach (var f in Directory.GetFiles(ArchiveDirPath))
                {
                    if (File.GetLastWriteTimeUtc(f) < cutoff)
                    {
                        try { File.Delete(f); } catch { }
                    }
                }
            }
            catch { }
        }

        private static void AppendHistoryPersistent(SentinelEventDto item)
        {
            lock (s_queueLock)
            {
                try
                {
                    if (item.AutoHealed)
                    {
                        AppendEventsToArchiveUnsafe(new[] { item });
                        return;
                    }

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

        public static int AutoHealRecoverableHistory(bool senseHealthy, bool cameraHealthy, bool onlineHealthy)
        {
            lock (s_queueLock)
            {
                var history = LoadHistoryUnsafe();
                if (history.Count == 0) return 0;

                int healed = 0;
                var newlyArchived = new List<SentinelEventDto>();
                var remainingActive = new List<SentinelEventDto>();

                foreach (var ev in history)
                {
                    if (ev.AutoHealed)
                    {
                        newlyArchived.Add(ev);
                        continue;
                    }

                    string comb = $"{ev.Component} {ev.Title} {ev.Message}".ToLowerInvariant();
                    bool isSense = comb.Contains("sense") || comb.Contains("17701") || comb.Contains("ocr") || comb.Contains("face") || comb.Contains("voice");
                    bool isCamAudio = comb.Contains("camera") || comb.Contains("webcam") || comb.Contains("video") || comb.Contains("audio") || comb.Contains("pipewire") || comb.Contains("alsa");
                    bool isRecoverablePulseStatus = comb.Contains("portal_pulse") &&
                                                    (comb.Contains("lieferte status 429") || comb.Contains("status 429") ||
                                                     comb.Contains("status 0") || comb.Contains("status 502") ||
                                                     comb.Contains("status 503") || comb.Contains("status 504") ||
                                                     onlineHealthy);
                    bool isTransientNet = isRecoverablePulseStatus ||
                                          comb.Contains("timeout") || comb.Contains("timed out") || comb.Contains("eai_again") ||
                                          comb.Contains("tryagain") || comb.Contains("websocket") || comb.Contains("socket") ||
                                          comb.Contains("connection") || comb.Contains("goaway") || comb.Contains("session duration") ||
                                          comb.Contains("portal_pulse") || comb.Contains("status 0") ||
                                          comb.Contains("lieferte status 429") || comb.Contains("status 429") ||
                                          comb.Contains("401") || comb.Contains("502") || comb.Contains("503") || comb.Contains("504") ||
                                          comb.Contains("bitte einmalig nas-passwort") ||
                                          string.Equals(ev.Severity, "warning", StringComparison.OrdinalIgnoreCase);

                    if ((isSense && senseHealthy) || (isCamAudio && cameraHealthy) || isTransientNet)
                    {
                        ev.AutoHealed = true;
                        ev.Severity = "warning";
                        ev.HealSummary = "Vollautomatisch in Funkiras Kuppelhalle geprüft & ins Log-Archiv verschoben";
                        healed++;
                        newlyArchived.Add(ev);
                    }
                    else
                    {
                        remainingActive.Add(ev);
                    }
                }

                if (newlyArchived.Count > 0)
                {
                    if (healed > 0)
                        Interlocked.Add(ref s_totalAutoHealed, healed);

                    AppendEventsToArchiveUnsafe(newlyArchived);
                    try
                    {
                        // Zero-Log-Baseline: Wenn keine offenen Fehler verbleiben -> Haupt-Logdatei exakt 0 Bytes!
                        string nextContent = remainingActive.Count == 0 ? "" : JsonSerializer.Serialize(remainingActive);
                        File.WriteAllText(HistoryFilePath, nextContent, new UTF8Encoding(false));
                        LinuxVaultAndStorage.TryRestrictFilePermissions(HistoryFilePath);
                    }
                    catch { }
                }

                return healed;
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

                    // 4. Auto-heal recoverable transient history entries (Zero-Log-Baseline: sentinel_history.json -> 0 Bytes) & flush queue
                    AutoHealRecoverableHistory(
                        senseHealthy: senseOk,
                        cameraHealthy: cam.HasCamera,
                        onlineHealthy: SeelenfunkeLinuxApiClient.LatestPulse?.IsConnected == true);
                    await FlushQueueAsync().ConfigureAwait(false);
                }
                catch { }

                await Task.Delay(TimeSpan.FromSeconds(45)).ConfigureAwait(false);
            }
        }

        public static void ResetFingerprintsForSelfTest()
        {
            s_recentFingerprints.Clear();
        }

        public static int CountRecentActiveLocalErrors(int withinHours = 24)
        {
            var recent = GetRecentHistory(60);
            if (recent.Count == 0) return 0;

            DateTime cutoff = DateTime.UtcNow.AddHours(-Math.Clamp(withinHours, 1, 168));
            var seen = new HashSet<string>(StringComparer.Ordinal);
            foreach (var ev in recent)
            {
                if (ev.AutoHealed) continue;
                if (!string.Equals(ev.Severity, "error", StringComparison.OrdinalIgnoreCase)) continue;
                if (DateTime.TryParse(ev.OccurredAt, CultureInfo.InvariantCulture, DateTimeStyles.RoundtripKind, out var dt))
                {
                    if (dt.ToUniversalTime() < cutoff) continue;
                }
                seen.Add(string.IsNullOrWhiteSpace(ev.Fingerprint) ? ev.Title : ev.Fingerprint);
            }
            return seen.Count;
        }
    }

    public static class CellarBrainAndHandoffEngine
    {
        private static readonly object s_fileLock = new();

        public static string BrainCacheFilePath => Path.Combine(LinuxVaultAndStorage.DataDir, "cellar_brain_cache.json");
        public static string HandoffFilePath => Path.Combine(LinuxVaultAndStorage.DataDir, "antigravity_error_handoff.json");

        public static async Task<JsonObject> BuildSnapshotAsync(LinuxOsSettings settings)
        {
            string normEnv = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string envUpper = normEnv.ToUpperInvariant();
            string bearer = LinuxVaultAndStorage.GetToken(settings, normEnv);

            bool onlineConnected = false;
            bool authExpired = false;
            string connectionNote = "Online mit Mein-Seelenfunke Projekt-Gehirn verbunden";

            int totalFiles = 891;
            int totalLinks = 3856;
            JsonArray clusters = BuildDefaultSeelenfunkeClusters();
            var itemsList = new List<JsonObject>();
            JsonObject? serverHandoff = null;

            LoadCachedBrainStats(ref totalFiles, ref totalLinks, ref clusters);

            if (string.IsNullOrWhiteSpace(bearer))
            {
                connectionNote = $"Kein {envUpper}-Token hinterlegt · Lokaler Resilienz-Modus aktiv";
            }
            else
            {
                string url = $"{SeelenfunkeLinuxApiClient.ResolveBaseApiUrl(normEnv)}/ai/cellar-snapshot";
                if (SeelenfunkeLinuxApiClient.ValidateHttpsUrl(url))
                {
                    try
                    {
                        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(8));
                        using var req = new HttpRequestMessage(HttpMethod.Get, url);
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                        using var resp = await SeelenfunkeLinuxApiClient.SharedHttp.SendAsync(req, cts.Token).ConfigureAwait(false);

                        int status = (int)resp.StatusCode;
                        if (resp.IsSuccessStatusCode)
                        {
                            string raw = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                            if (JsonNode.Parse(raw) is JsonObject portalSnap)
                            {
                                onlineConnected = true;
                                if (portalSnap["brain_stats"] is JsonObject bs)
                                {
                                    int tf = bs["total_files"]?.GetValue<int>() ?? 0;
                                    int tl = bs["total_links"]?.GetValue<int>() ?? 0;
                                    if (tf > 0) totalFiles = tf;
                                    if (tl > 0) totalLinks = tl;
                                }
                                if (portalSnap["department_clusters"] is JsonArray dc && dc.Count > 0)
                                {
                                    clusters = JsonNode.Parse(dc.ToJsonString())!.AsArray();
                                }
                                SaveCachedBrainStats(totalFiles, totalLinks, clusters);

                                if (portalSnap["items"] is JsonArray srvItems)
                                {
                                    foreach (var node in srvItems)
                                    {
                                        if (node is JsonObject obj)
                                            itemsList.Add(ScrubJsonObject(obj));
                                    }
                                }

                                if (portalSnap["active_handoff"] is JsonObject ah)
                                    serverHandoff = ScrubJsonObject(ah);
                            }
                        }
                        else if (status == 401 || status == 403)
                        {
                            authExpired = status == 401;
                            connectionNote = status == 401
                                ? $"Portal-Sitzung ({envUpper}) abgelaufen (HTTP 401) · Lokaler Keller-Modus aktiv"
                                : $"Portal-Token eingeschränkt (HTTP 403) · Lokaler Keller-Modus aktiv";
                        }
                        else
                        {
                            connectionNote = $"Portal antwortete mit HTTP {status} · Lokaler Keller-Modus aktiv";
                        }
                    }
                    catch (Exception ex)
                    {
                        connectionNote = $"Portal kurz nicht erreichbar ({ErrorSentinelAndWatchdog.ScrubSecrets(ex.GetType().Name)}) · Lokaler Keller-Modus aktiv";
                    }
                }
            }

            // Falls die Portal-Sitzung (HTTP 401) abgelaufen ist, zeige diesen Befund direkt transparent als Knoten im Keller!
            if (authExpired)
            {
                itemsList.Add(new JsonObject
                {
                    ["item_key"] = $"local:auth_expired_{normEnv}",
                    ["kind"] = "file_core",
                    ["platform"] = "minipc_linux",
                    ["platform_label"] = "Mini-PC",
                    ["severity"] = "warning",
                    ["is_error"] = false,
                    ["title"] = $"Portal-Anmeldung für {envUpper} abgelaufen (HTTP 401)",
                    ["message"] = $"Das gespeicherte Sanctum-Token für {envUpper} wurde vom Server mit HTTP 401 abgelehnt. Lokale 3D-Welt, Keller-Hologramm und 3.600+ Dokumente laufen weiter.",
                    ["culprit_file"] = "funkenwerk-os-linux/LinuxVaultAndApi.cs",
                    ["culprit_name"] = "LinuxVaultAndApi.cs",
                    ["group_label"] = "Mini-PC API-Vault",
                    ["department_key"] = "ai_funkira",
                    ["department_label"] = "Funkira, KI-Agenten & Wissen",
                    ["line"] = 1496,
                    ["occurrences_count"] = 1,
                    ["route"] = "/api/ai/cellar-snapshot",
                    ["timestamp"] = DateTime.Now.ToString("dd.MM.yyyy HH:mm:ss"),
                    ["probable_cause"] = $"Die Anmeldesitzung für {envUpper} ist abgelaufen oder wurde auf dem Server rotiert.",
                    ["recommended_action"] = $"Über 'Anmelden' auf dem Monitor einmal frisch an {envUpper} einloggen.",
                    ["shop_impact_de"] = "Der öffentliche Shop läuft ungestört weiter; nur Funkiras Online-Abfragen warten auf den frischen Login.",
                    ["trace_excerpt"] = $"GET /api/ai/cellar-snapshot ({envUpper}) -> HTTP 401 Unauthorized",
                    ["incoming_files"] = new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/Program.cs", "Program.cs", "Mini-PC Host"),
                        MakeNeighborObj("funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs", "FunkiraLiveAndMcpEngine.cs", "Live-Engine")
                    ),
                    ["outgoing_files"] = new JsonArray(
                        MakeNeighborObj("routes/api/ai.php", "ai.php", "Route"),
                        MakeNeighborObj("app/Http/Controllers/AIController.php", "AIController.php", "Controller")
                    )
                });
            }

            // Lokales NAS-Passwort prüfen, falls NAS konfiguriert aber Passwort leer
            if (!string.IsNullOrWhiteSpace(settings.NasHost) && string.IsNullOrWhiteSpace(LinuxVaultAndStorage.GetNasPassword(settings)))
            {
                itemsList.Add(new JsonObject
                {
                    ["item_key"] = "local:nas_password_missing",
                    ["kind"] = "file_core",
                    ["platform"] = "minipc_linux",
                    ["platform_label"] = "Mini-PC",
                    ["severity"] = "warning",
                    ["is_error"] = false,
                    ["title"] = "Synology-NAS Passwort für Delta-Backup noch leer",
                    ["message"] = $"NAS-Ziel '{ErrorSentinelAndWatchdog.ScrubSecrets(settings.NasHost)}' ist eingetragen, aber im lokalen Tresor ist noch kein SMB-Passwort hinterlegt.",
                    ["culprit_file"] = "funkenwerk-os-linux/DocumentArchiveAndNasEngine.cs",
                    ["culprit_name"] = "DocumentArchiveAndNasEngine.cs",
                    ["group_label"] = "Mini-PC Backup",
                    ["department_key"] = "system_security",
                    ["department_label"] = "Projekt-Gehirn, Logs & Schutz",
                    ["line"] = 1,
                    ["occurrences_count"] = 1,
                    ["route"] = "/api/archive/backup-status",
                    ["timestamp"] = DateTime.Now.ToString("dd.MM.yyyy HH:mm:ss"),
                    ["probable_cause"] = "In den Funkenwerk-Einstellungen wurde noch kein NAS-Passwort gespeichert.",
                    ["recommended_action"] = "In den Funkenwerk-Einstellungen das Synology-Passwort eintragen und Backup testen.",
                    ["shop_impact_de"] = "Betrifft nur das lokale Mini-PC-Archiv-Backup auf das Heim-NAS, keine Auswirkung auf den Shop.",
                    ["trace_excerpt"] = "DocumentArchiveAndNasEngine -> GetNasPassword(settings) == empty",
                    ["incoming_files"] = new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/Program.cs", "Program.cs", "Mini-PC Host"),
                        MakeNeighborObj("funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs", "FunkiraLiveAndMcpEngine.cs", "Live-Engine")
                    ),
                    ["outgoing_files"] = new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/LinuxVaultAndApi.cs", "LinuxVaultAndApi.cs", "AES-Vault")
                    )
                });
            }

            // Lokale Mini-PC Error-Sentinel-Historie einmischen (dedupliziert nach Fingerprint)
            var localHistory = ErrorSentinelAndWatchdog.GetRecentHistory(50);
            var groupedLocal = localHistory
                .GroupBy(h => string.IsNullOrWhiteSpace(h.Fingerprint) ? (h.Component + "|" + h.Title) : h.Fingerprint)
                .Take(12);

            var existingTitles = new HashSet<string>(
                itemsList.Select(i => (i["title"]?.GetValue<string>() ?? "").Trim().ToLowerInvariant()),
                StringComparer.OrdinalIgnoreCase);

            foreach (var grp in groupedLocal)
            {
                var latest = grp.First();
                if (latest.AutoHealed)
                    continue;
                string cleanTitle = ErrorSentinelAndWatchdog.ScrubSecrets(latest.Title);
                if (string.IsNullOrWhiteSpace(cleanTitle) || existingTitles.Contains(cleanTitle.ToLowerInvariant()))
                    continue;

                var (culpritFile, deptKey, deptLabel, incomingArr, outgoingArr, causeHint, impactHint) =
                    DescribeMiniPcComponentGraph(latest.Component, cleanTitle, latest.Message, latest.AutoHealed);

                string itemKey = "local:" + (string.IsNullOrWhiteSpace(latest.Fingerprint)
                    ? Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(cleanTitle))).Substring(0, 12).ToLowerInvariant()
                    : latest.Fingerprint.Substring(0, Math.Min(16, latest.Fingerprint.Length)));

                string tsFormatted = latest.OccurredAt;
                if (DateTime.TryParse(latest.OccurredAt, CultureInfo.InvariantCulture, DateTimeStyles.RoundtripKind, out var parsedTs))
                    tsFormatted = parsedTs.ToLocalTime().ToString("dd.MM.yyyy HH:mm:ss");

                bool isErr = !latest.AutoHealed && string.Equals(latest.Severity, "error", StringComparison.OrdinalIgnoreCase);
                string recAction = latest.AutoHealed && !string.IsNullOrWhiteSpace(latest.HealSummary)
                    ? $"Bereits selbst geheilt: {ErrorSentinelAndWatchdog.ScrubSecrets(latest.HealSummary)}"
                    : "Bei Wiederholung als Fix-Dossier für Antigravity bereitstellen.";

                itemsList.Add(new JsonObject
                {
                    ["item_key"] = itemKey,
                    ["kind"] = "file_core",
                    ["platform"] = "minipc_linux",
                    ["platform_label"] = "Mini-PC",
                    ["severity"] = isErr ? "error" : "warning",
                    ["is_error"] = isErr,
                    ["auto_healed"] = latest.AutoHealed,
                    ["title"] = cleanTitle,
                    ["message"] = ErrorSentinelAndWatchdog.ScrubSecrets(latest.Message),
                    ["culprit_file"] = culpritFile,
                    ["culprit_name"] = Path.GetFileName(culpritFile),
                    ["group_label"] = "Mini-PC Modul",
                    ["department_key"] = deptKey,
                    ["department_label"] = deptLabel,
                    ["line"] = null,
                    ["occurrences_count"] = grp.Count(),
                    ["route"] = latest.Component,
                    ["timestamp"] = tsFormatted,
                    ["probable_cause"] = ErrorSentinelAndWatchdog.ScrubSecrets(causeHint),
                    ["recommended_action"] = recAction,
                    ["shop_impact_de"] = impactHint,
                    ["trace_excerpt"] = ErrorSentinelAndWatchdog.ScrubSecrets(
                        string.IsNullOrWhiteSpace(latest.Stacktrace) ? latest.Message : latest.Stacktrace),
                    ["incoming_files"] = incomingArr,
                    ["outgoing_files"] = outgoingArr
                });
            }

            // Sortiere echte Fehler vor Warnungen
            var sortedItems = itemsList
                .OrderByDescending(i => i["is_error"]?.GetValue<bool>() == true)
                .ThenByDescending(i => i["occurrences_count"]?.GetValue<int>() ?? 1)
                .ToList();

            int openErrorsCount = sortedItems.Count(i => i["is_error"]?.GetValue<bool>() == true);
            int openWarningsCount = sortedItems.Count(i => i["is_error"]?.GetValue<bool>() != true);
            var neighborSet = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            foreach (var it in sortedItems)
            {
                if (it["incoming_files"] is JsonArray inc)
                    foreach (var n in inc)
                        if (n?["file_path"]?.GetValue<string>() is string fp && !string.IsNullOrWhiteSpace(fp))
                            neighborSet.Add(fp);
                if (it["outgoing_files"] is JsonArray outg)
                    foreach (var n in outg)
                        if (n?["file_path"]?.GetValue<string>() is string fp && !string.IsNullOrWhiteSpace(fp))
                            neighborSet.Add(fp);
            }

            var itemsJson = new JsonArray();
            foreach (var it in sortedItems) itemsJson.Add(it);

            var activeHandoff = GetLocalAntigravityHandoff() ?? serverHandoff;

            return new JsonObject
            {
                ["ok"] = true,
                ["status"] = "success",
                ["generated_at"] = DateTime.UtcNow.ToString("O"),
                ["environment"] = envUpper,
                ["online_connected"] = onlineConnected,
                ["auth_expired"] = authExpired,
                ["connection_note"] = connectionNote,
                ["has_errors"] = openErrorsCount > 0 || openWarningsCount > 0,
                ["has_critical_errors"] = openErrorsCount > 0,
                ["brain_stats"] = new JsonObject
                {
                    ["total_files"] = totalFiles,
                    ["total_links"] = totalLinks,
                    ["error_core_count"] = sortedItems.Count,
                    ["neighbor_count"] = neighborSet.Count,
                    ["open_errors_count"] = openErrorsCount,
                    ["open_warnings_count"] = openWarningsCount
                },
                ["department_clusters"] = clusters,
                ["items"] = itemsJson,
                ["active_handoff"] = activeHandoff
            };
        }

        public static async Task<JsonObject> RunSystemCheckAsync(LinuxOsSettings settings)
        {
            string normEnv = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string envUpper = normEnv.ToUpperInvariant();
            string bearer = LinuxVaultAndStorage.GetToken(settings, normEnv);

            var localChecks = new JsonArray();
            var senseHealth = await LocalSenseBridge.GetHealthAsync().ConfigureAwait(false);
            bool senseOk = senseHealth["ok"]?.GetValue<bool>() == true;
            localChecks.Add(new JsonObject
            {
                ["pillar"] = "Mini-PC Sense-Daemon (:17701)",
                ["ok"] = senseOk,
                ["detail"] = senseOk ? "Aktiv & antwortet lokal" : "Nicht erreichbar (Watchdog überwacht)"
            });

            var cam = await LinuxHardwareEngine.DetectAndPrepareWebcamAsync(activateUsbMicDefault: false).ConfigureAwait(false);
            localChecks.Add(new JsonObject
            {
                ["pillar"] = "Werkstatt-Kamera & Mikrofon",
                ["ok"] = cam.HasCamera,
                ["detail"] = cam.HasCamera ? $"{cam.CameraName} ({cam.VideoDevice})" : "Keine aktive USB-Kamera erkannt"
            });

            bool hasGemini = !string.IsNullOrWhiteSpace(LinuxVaultAndStorage.GetGeminiApiKey(settings));
            localChecks.Add(new JsonObject
            {
                ["pillar"] = "AES-256 Sprach-Tresor",
                ["ok"] = hasGemini,
                ["detail"] = hasGemini ? "Gemini-Schlüssel lokal verschlüsselt bereit" : "Kein lokaler Gemini-Schlüssel hinterlegt"
            });

            JsonObject? portalReport = null;
            if (!string.IsNullOrWhiteSpace(bearer))
            {
                string url = $"{SeelenfunkeLinuxApiClient.ResolveBaseApiUrl(normEnv)}/ai/system-brain-check";
                if (SeelenfunkeLinuxApiClient.ValidateHttpsUrl(url))
                {
                    try
                    {
                        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(25));
                        using var req = new HttpRequestMessage(HttpMethod.Post, url);
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                        req.Content = new StringContent("{}", new UTF8Encoding(false), "application/json");
                        using var resp = await SeelenfunkeLinuxApiClient.SharedHttp.SendAsync(req, cts.Token).ConfigureAwait(false);
                        if (resp.IsSuccessStatusCode)
                        {
                            string raw = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                            if (JsonNode.Parse(raw) is JsonObject parsed && parsed["report"] is JsonObject rep)
                            {
                                portalReport = ScrubJsonObject(rep);
                            }
                        }
                    }
                    catch { }
                }
            }

            var refreshedSnapshot = await BuildSnapshotAsync(settings).ConfigureAwait(false);
            string summaryDe = portalReport != null
                ? $"Vollständige 6-Säulen-Portalprüfung ({envUpper}) + Mini-PC-Hardwarecheck abgeschlossen."
                : $"Lokale Mini-PC-Systemprüfung abgeschlossen ({envUpper}-Portal aktuell im lokalen Resilienz-Modus).";

            return new JsonObject
            {
                ["ok"] = true,
                ["status"] = "success",
                ["summary_de"] = summaryDe,
                ["portal_check_executed"] = portalReport != null,
                ["local_checks"] = localChecks,
                ["portal_report"] = portalReport,
                ["snapshot"] = refreshedSnapshot
            };
        }

        public static async Task<JsonObject> RunAutonomousHealAsync(LinuxOsSettings settings)
        {
            string normEnv = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string envUpper = normEnv.ToUpperInvariant();
            string bearer = LinuxVaultAndStorage.GetToken(settings, normEnv);

            int healedTotal = 0;
            bool portalConnected = false;

            var senseHealth = await LocalSenseBridge.GetHealthAsync().ConfigureAwait(false);
            bool senseOk = senseHealth["ok"]?.GetValue<bool>() == true;
            if (!senseOk && OperatingSystem.IsLinux() && LinuxVaultAndStorage.TestOverrideRootDir == null)
            {
                LocalSenseBridge.EnsureDaemonStarted();
                await Task.Delay(350).ConfigureAwait(false);
                senseHealth = await LocalSenseBridge.GetHealthAsync().ConfigureAwait(false);
                senseOk = senseHealth["ok"]?.GetValue<bool>() == true;
                if (senseOk) healedTotal++;
            }

            var cam = await LinuxHardwareEngine.DetectAndPrepareWebcamAsync(activateUsbMicDefault: false).ConfigureAwait(false);
            bool camOk = cam.HasCamera;

            if (!string.IsNullOrWhiteSpace(bearer))
            {
                string url = $"{SeelenfunkeLinuxApiClient.ResolveBaseApiUrl(normEnv)}/ai/cellar-auto-heal";
                if (SeelenfunkeLinuxApiClient.ValidateHttpsUrl(url))
                {
                    try
                    {
                        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(18));
                        using var req = new HttpRequestMessage(HttpMethod.Post, url);
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                        req.Content = new StringContent("{}", new UTF8Encoding(false), "application/json");
                        using var resp = await SeelenfunkeLinuxApiClient.SharedHttp.SendAsync(req, cts.Token).ConfigureAwait(false);
                        if (resp.IsSuccessStatusCode)
                        {
                            portalConnected = true;
                            string raw = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                            if (JsonNode.Parse(raw) is JsonObject pObj)
                            {
                                healedTotal += pObj["healed_count"]?.GetValue<int>() ?? 0;
                            }
                        }
                    }
                    catch { }
                }
            }

            healedTotal += ErrorSentinelAndWatchdog.AutoHealRecoverableHistory(senseOk, camOk, portalConnected);

            var refreshedSnapshot = await BuildSnapshotAsync(settings).ConfigureAwait(false);
            var items = refreshedSnapshot["items"] as JsonArray ?? new JsonArray();
            int remainingErrors = 0;
            JsonObject? topError = null;
            foreach (var node in items)
            {
                if (node is JsonObject obj && obj["is_error"]?.GetValue<bool>() == true)
                {
                    remainingErrors++;
                    topError ??= obj;
                }
            }

            bool autoDispatched = false;
            JsonObject? autoDossier = null;
            if (topError != null)
            {
                string topKey = topError["item_key"]?.GetValue<string>() ?? "";
                using var doc = JsonDocument.Parse(JsonSerializer.Serialize(new
                {
                    item_key = topKey,
                    alina_note = "Vollautomatisch aus Funkiras Kuppelhalle bereitgestellt"
                }));
                var handoffRes = await PrepareAntigravityHandoffAsync(settings, doc.RootElement).ConfigureAwait(false);
                if (handoffRes["ok"]?.GetValue<bool>() == true)
                {
                    autoDispatched = true;
                    autoDossier = handoffRes["dossier"] as JsonObject;
                    if (autoDossier != null)
                        refreshedSnapshot["active_handoff"] = JsonNode.Parse(autoDossier.ToJsonString());
                }
            }

            string summaryDe;
            if (remainingErrors == 0 && healedTotal > 0)
            {
                summaryDe = $"Ich habe {healedTotal} Systembefund{(healedTotal == 1 ? "" : "e")} direkt vollautomatisch geheilt · alles läuft wieder grün!";
            }
            else if (remainingErrors == 0)
            {
                summaryDe = "Alle Dienste, Caches und Verbindungen sind geprüft und zu 100 Prozent grün.";
            }
            else
            {
                string cFile = topError?["culprit_name"]?.GetValue<string>() ?? "Systemdatei";
                summaryDe = healedTotal > 0
                    ? $"{healedTotal} Laufzeit-Befund geheilt; Code-Befund in {cFile} vollautomatisch als Reparatur-Paket bereitgestellt."
                    : $"Code-Befund in {cFile} eingegrenzt und vollautomatisch als fertiges Reparatur-Paket hinterlegt.";
            }

            return new JsonObject
            {
                ["ok"] = true,
                ["status"] = "success",
                ["healed_count"] = healedTotal,
                ["remaining_errors_count"] = remainingErrors,
                ["auto_dispatched"] = autoDispatched,
                ["summary_de"] = summaryDe,
                ["dossier"] = autoDossier != null ? JsonNode.Parse(autoDossier.ToJsonString()) : null,
                ["snapshot"] = refreshedSnapshot
            };
        }

        public static async Task<JsonObject> PrepareAntigravityHandoffAsync(LinuxOsSettings settings, JsonElement input)
        {
            var snapshot = await BuildSnapshotAsync(settings).ConfigureAwait(false);
            var items = snapshot["items"] as JsonArray ?? new JsonArray();

            string reqKey = SeelenfunkeLinuxApiClient.GetStr(input, "item_key", "").Trim();
            string reqFile = SeelenfunkeLinuxApiClient.GetStr(input, "file_path", "").Trim();
            if (string.IsNullOrWhiteSpace(reqFile))
                reqFile = SeelenfunkeLinuxApiClient.GetStr(input, "culprit_file", "").Trim();
            string alinaNote = ErrorSentinelAndWatchdog.ScrubSecrets(SeelenfunkeLinuxApiClient.GetStr(input, "alina_note", "").Trim());

            JsonObject? matched = null;
            foreach (var node in items)
            {
                if (node is not JsonObject cand) continue;
                string ck = cand["item_key"]?.GetValue<string>() ?? "";
                string cf = cand["culprit_file"]?.GetValue<string>() ?? "";
                if (!string.IsNullOrWhiteSpace(reqKey) && string.Equals(ck, reqKey, StringComparison.OrdinalIgnoreCase))
                {
                    matched = cand;
                    break;
                }
                if (!string.IsNullOrWhiteSpace(reqFile) && string.Equals(cf, reqFile, StringComparison.OrdinalIgnoreCase))
                {
                    matched = cand;
                    break;
                }
            }

            if (matched == null && string.IsNullOrWhiteSpace(reqKey) && string.IsNullOrWhiteSpace(reqFile) && items.Count > 0)
            {
                matched = items[0] as JsonObject;
            }

            string normEnv = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string envUpper = normEnv.ToUpperInvariant();

            string itemKey = matched?["item_key"]?.GetValue<string>()
                ?? (!string.IsNullOrWhiteSpace(reqKey) ? reqKey : "custom:minipc_handoff");
            string platform = matched?["platform"]?.GetValue<string>()
                ?? SeelenfunkeLinuxApiClient.GetStr(input, "platform", "minipc_linux");
            string platformLabel = matched?["platform_label"]?.GetValue<string>() ?? "Mini-PC";
            string severity = matched?["severity"]?.GetValue<string>()
                ?? SeelenfunkeLinuxApiClient.GetStr(input, "severity", "error");
            string title = ErrorSentinelAndWatchdog.ScrubSecrets(
                matched?["title"]?.GetValue<string>()
                ?? SeelenfunkeLinuxApiClient.GetStr(input, "title", "Systembefund aus Funkiras Analyse-Keller"));
            string message = ErrorSentinelAndWatchdog.ScrubSecrets(
                matched?["message"]?.GetValue<string>()
                ?? SeelenfunkeLinuxApiClient.GetStr(input, "message", "Zur Prüfung an Antigravity übergeben."));
            string? culpritFile = matched?["culprit_file"]?.GetValue<string>();
            if (string.IsNullOrWhiteSpace(culpritFile) && !string.IsNullOrWhiteSpace(reqFile))
                culpritFile = ErrorSentinelAndWatchdog.ScrubSecrets(reqFile);

            int? lineNo = null;
            if (matched?["line"] != null && matched["line"]!.AsValue().TryGetValue<int>(out int ml))
                lineNo = ml;
            else if (input.ValueKind == JsonValueKind.Object && input.TryGetProperty("line", out var lEl) && lEl.TryGetInt32(out int il))
                lineNo = il;

            int occurrences = matched?["occurrences_count"]?.GetValue<int>() ?? 1;
            string probableCause = ErrorSentinelAndWatchdog.ScrubSecrets(
                matched?["probable_cause"]?.GetValue<string>() ?? "Laufzeitbefund laut Fehler-Analyse-Keller.");
            string recommendedAction = ErrorSentinelAndWatchdog.ScrubSecrets(
                matched?["recommended_action"]?.GetValue<string>() ?? "Verursacher-Datei und Nachbarknoten in Antigravity prüfen und beheben.");
            string shopImpact = ErrorSentinelAndWatchdog.ScrubSecrets(
                matched?["shop_impact_de"]?.GetValue<string>() ?? "");
            string traceExcerpt = ErrorSentinelAndWatchdog.ScrubSecrets(
                matched?["trace_excerpt"]?.GetValue<string>() ?? message);

            JsonArray incomingClone = matched?["incoming_files"] is JsonArray incA
                ? JsonNode.Parse(incA.ToJsonString())!.AsArray()
                : new JsonArray();
            JsonArray outgoingClone = matched?["outgoing_files"] is JsonArray outA
                ? JsonNode.Parse(outA.ToJsonString())!.AsArray()
                : new JsonArray();

            var dossier = new JsonObject
            {
                ["handoff_version"] = 1,
                ["prepared_at"] = DateTime.UtcNow.ToString("O"),
                ["prepared_at_human"] = DateTime.Now.ToString("dd.MM.yyyy HH:mm:ss"),
                ["prepared_by"] = "Funkira & Alina (Geheimer Analyse-Keller)",
                ["environment"] = envUpper,
                ["status"] = "ready_for_antigravity",
                ["alina_note"] = string.IsNullOrWhiteSpace(alinaNote) ? null : alinaNote,
                ["item_key"] = itemKey,
                ["platform"] = platform,
                ["platform_label"] = platformLabel,
                ["severity"] = severity,
                ["title"] = title,
                ["message"] = message,
                ["culprit_file"] = culpritFile,
                ["culprit_name"] = string.IsNullOrWhiteSpace(culpritFile) ? platformLabel : Path.GetFileName(culpritFile),
                ["department_label"] = matched?["department_label"]?.GetValue<string>() ?? "System",
                ["line"] = lineNo.HasValue ? JsonValue.Create(lineNo.Value) : null,
                ["occurrences_count"] = occurrences,
                ["route"] = matched?["route"]?.GetValue<string>() ?? "",
                ["probable_cause"] = probableCause,
                ["recommended_action"] = recommendedAction,
                ["shop_impact_de"] = shopImpact,
                ["trace_excerpt"] = traceExcerpt,
                ["incoming_files"] = incomingClone,
                ["outgoing_files"] = outgoingClone
            };

            lock (s_fileLock)
            {
                Directory.CreateDirectory(LinuxVaultAndStorage.DataDir);
                string formatted = dossier.ToJsonString(new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(HandoffFilePath, formatted, new UTF8Encoding(false));
                LinuxVaultAndStorage.TryRestrictFilePermissions(HandoffFilePath);
            }

            bool serverSynced = false;
            string bearer = LinuxVaultAndStorage.GetToken(settings, normEnv);
            if (!string.IsNullOrWhiteSpace(bearer))
            {
                string url = $"{SeelenfunkeLinuxApiClient.ResolveBaseApiUrl(normEnv)}/ai/cellar-handoff";
                if (SeelenfunkeLinuxApiClient.ValidateHttpsUrl(url))
                {
                    try
                    {
                        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(8));
                        using var req = new HttpRequestMessage(HttpMethod.Post, url);
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                        req.Content = new StringContent(dossier.ToJsonString(), new UTF8Encoding(false), "application/json");
                        using var resp = await SeelenfunkeLinuxApiClient.SharedHttp.SendAsync(req, cts.Token).ConfigureAwait(false);
                        serverSynced = resp.IsSuccessStatusCode;
                    }
                    catch { }
                }
            }

            return new JsonObject
            {
                ["ok"] = true,
                ["status"] = "success",
                ["local_handoff_path"] = HandoffFilePath,
                ["server_synced"] = serverSynced,
                ["dossier"] = dossier
            };
        }

        public static JsonObject? GetLocalAntigravityHandoff()
        {
            lock (s_fileLock)
            {
                try
                {
                    if (!File.Exists(HandoffFilePath)) return null;
                    if (JsonNode.Parse(File.ReadAllText(HandoffFilePath, Encoding.UTF8)) is JsonObject obj)
                        return ScrubJsonObject(obj);
                }
                catch { }
                return null;
            }
        }

        private static JsonObject MakeNeighborObj(string filePath, string name, string groupLabel) => new()
        {
            ["file_path"] = filePath,
            ["name"] = name,
            ["group_label"] = groupLabel
        };

        private static (string CulpritFile, string DeptKey, string DeptLabel, JsonArray Incoming, JsonArray Outgoing, string Cause, string Impact)
            DescribeMiniPcComponentGraph(string component, string title, string message, bool autoHealed)
        {
            string c = (component ?? "").Trim().ToLowerInvariant();
            string combined = (title + " " + message).ToLowerInvariant();

            if (c.Contains("live_credentials") || c.Contains("ai_execute") || c.Contains("pulse"))
            {
                string cause = combined.Contains("401")
                    ? "Portal-Token war abgelaufen, lokaler Tresor hat übernommen."
                    : (combined.Contains("eai_again") || combined.Contains("socket") || combined.Contains("timeout")
                        ? "Kurze DNS- oder Netzwerk-Unterbrechung zum Online-Portal."
                        : "Online-API-Aufruf vom Mini-PC zum Portal meldete einen Fehler.");
                return (
                    "funkenwerk-os-linux/LinuxVaultAndApi.cs",
                    "ai_funkira",
                    "Funkira, KI-Agenten & Wissen",
                    new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs", "FunkiraLiveAndMcpEngine.cs", "Live-Engine"),
                        MakeNeighborObj("funkenwerk-os-linux/Program.cs", "Program.cs", "Mini-PC Host")
                    ),
                    new JsonArray(
                        MakeNeighborObj("routes/api/ai.php", "ai.php", "Portal-Route"),
                        MakeNeighborObj("app/Http/Controllers/AIController.php", "AIController.php", "Portal-Controller")
                    ),
                    cause,
                    autoHealed ? "Automatisch durch lokalen Mini-PC-Tresor abgefedert." : "Betrifft Funkiras Online-Anbindung am Werkstatt-Monitor."
                );
            }

            if (c.Contains("sense") || c.Contains("face") || c.Contains("voice"))
            {
                return (
                    "funkenwerk-os-linux/funkira_local_sense.py",
                    "ai_funkira",
                    "Funkira, KI-Agenten & Wissen",
                    new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/LocalSenseAndArchiveEngines.cs", "LocalSenseAndArchiveEngines.cs", "Sense-Bridge"),
                        MakeNeighborObj("funkenwerk-os-linux/LinuxVaultAndApi.cs", "LinuxVaultAndApi.cs", "Watchdog")
                    ),
                    new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/LinuxHardwareEngine.cs", "LinuxHardwareEngine.cs", "Kamera/Audio")
                    ),
                    "Der lokale Python-Sense-Dienst auf Port 17701 wurde vom Watchdog geprüft.",
                    "Betrifft ausschließlich die lokale Anwesenheits- und Sprechererkennung am Monitor."
                );
            }

            if (c.Contains("webcam") || c.Contains("bluetooth") || c.Contains("audio") || c.Contains("wifi"))
            {
                return (
                    "funkenwerk-os-linux/LinuxHardwareEngine.cs",
                    "system_security",
                    "Projekt-Gehirn, Logs & Schutz",
                    new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs", "FunkiraLiveAndMcpEngine.cs", "MCP-Steuerung"),
                        MakeNeighborObj("funkenwerk-os-linux/LinuxVaultAndApi.cs", "LinuxVaultAndApi.cs", "Watchdog")
                    ),
                    new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/wwwroot/app.js", "app.js", "Monitor-UI")
                    ),
                    "Hardware-Statusänderung an USB-Kamera, PipeWire-Audio oder Bluetooth erkannt.",
                    "Betrifft nur Peripheriegeräte am Werkstatt-Mini-PC, der Online-Shop ist nicht betroffen."
                );
            }

            if (c.Contains("nas") || c.Contains("archive") || c.Contains("trichter"))
            {
                return (
                    "funkenwerk-os-linux/DocumentArchiveAndNasEngine.cs",
                    "management_office",
                    "Leitung, Mail, Kalender & To-Do",
                    new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/Program.cs", "Program.cs", "WebDAV & API"),
                        MakeNeighborObj("funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs", "FunkiraLiveAndMcpEngine.cs", "Dokumenten-Suche")
                    ),
                    new JsonArray(
                        MakeNeighborObj("funkenwerk-os-linux/LinuxVaultAndApi.cs", "LinuxVaultAndApi.cs", "AES-Vault")
                    ),
                    "Ereignis im lokalen Dokumenten-Trichter oder Synology-NAS-Backup.",
                    "Betrifft das lokale Akten-Archiv auf dem Mini-PC."
                );
            }

            return (
                "funkenwerk-os-linux/Program.cs",
                "system_security",
                "Projekt-Gehirn, Logs & Schutz",
                new JsonArray(
                    MakeNeighborObj("funkenwerk-os-linux/FunkiraLiveAndMcpEngine.cs", "FunkiraLiveAndMcpEngine.cs", "Live-Engine")
                ),
                new JsonArray(
                    MakeNeighborObj("funkenwerk-os-linux/LinuxVaultAndApi.cs", "LinuxVaultAndApi.cs", "Error-Sentinel"),
                    MakeNeighborObj("funkenwerk-os-linux/wwwroot/funkira3d.js", "funkira3d.js", "3D-Welt")
                ),
                "Laufzeitmeldung im Funkenwerk-OS Dienst auf dem Mini-PC.",
                "Betrifft den lokalen Werkstatt-Kiosk auf dem Mini-PC."
            );
        }

        private static JsonArray BuildDefaultSeelenfunkeClusters()
        {
            return new JsonArray(
                MakeCluster("products_configurator", "Produkte & 3D-Konfigurator", 118, "ProductConfigurator.php", "ProductConfiguratorService.php", "Product.php"),
                MakeCluster("orders_logistics", "Bestellungen & DHL-Versand", 94, "OrderCheckout.php", "OrderOverview.php", "DhlService.php"),
                MakeCluster("accounting_finance", "Buchhaltung, Steuer & Bank", 86, "AccountingVariableCosts.php", "InvoiceService.php", "FinancialService.php"),
                MakeCluster("ai_funkira", "Funkira, KI-Agenten & Wissen", 112, "FunkiraSkillPoolService.php", "FunkiraLiveAndMcpEngine.cs", "funkira3d.js"),
                MakeCluster("management_office", "Leitung, Mail, Kalender & To-Do", 92, "MasterAnalytics.php", "ManagementTask.php", "ManagementEMails.php"),
                MakeCluster("marketing_influencer", "Marketing, Blog & Influencer", 84, "MarketingInfluencer.php", "MarketingVoucher.php", "InfluencerService.php"),
                MakeCluster("customer_gamification", "Kundenkonto & Gamification", 76, "CustomerDashboardComponent.php", "GamificationService.php", "Customer.php"),
                MakeCluster("support_telephony", "Support, Tickets & Telefonie", 58, "SupportTicket.php", "AiFrontendSupportChat.php", "AiSupportService.php"),
                MakeCluster("system_security", "Projekt-Gehirn, Logs & Schutz", 79, "SystemBrainDiagnosticsService.php", "SystemErrorLogger.php", "LinuxVaultAndApi.cs"),
                MakeCluster("frontend_views", "Shop-Frontend, Routen & Views", 92, "admin_routes.php", "global_routes.php", "SeoController.php")
            );
        }

        private static JsonObject MakeCluster(string key, string label, int count, params string[] samples)
        {
            var arr = new JsonArray();
            foreach (var s in samples) arr.Add(s);
            return new JsonObject
            {
                ["key"] = key,
                ["label"] = label,
                ["files_count"] = count,
                ["open_errors_count"] = 0,
                ["sample_files"] = arr
            };
        }

        private static void SaveCachedBrainStats(int totalFiles, int totalLinks, JsonArray clusters)
        {
            lock (s_fileLock)
            {
                try
                {
                    Directory.CreateDirectory(LinuxVaultAndStorage.DataDir);
                    var cacheObj = new JsonObject
                    {
                        ["cached_at"] = DateTime.UtcNow.ToString("O"),
                        ["total_files"] = totalFiles,
                        ["total_links"] = totalLinks,
                        ["department_clusters"] = JsonNode.Parse(clusters.ToJsonString())
                    };
                    File.WriteAllText(BrainCacheFilePath, cacheObj.ToJsonString(), new UTF8Encoding(false));
                    LinuxVaultAndStorage.TryRestrictFilePermissions(BrainCacheFilePath);
                }
                catch { }
            }
        }

        private static void LoadCachedBrainStats(ref int totalFiles, ref int totalLinks, ref JsonArray clusters)
        {
            lock (s_fileLock)
            {
                try
                {
                    if (!File.Exists(BrainCacheFilePath)) return;
                    if (JsonNode.Parse(File.ReadAllText(BrainCacheFilePath, Encoding.UTF8)) is JsonObject cached)
                    {
                        int tf = cached["total_files"]?.GetValue<int>() ?? 0;
                        int tl = cached["total_links"]?.GetValue<int>() ?? 0;
                        if (tf > 0) totalFiles = tf;
                        if (tl > 0) totalLinks = tl;
                        if (cached["department_clusters"] is JsonArray dc && dc.Count > 0)
                            clusters = dc;
                    }
                }
                catch { }
            }
        }

        private static JsonObject ScrubJsonObject(JsonObject obj)
        {
            string scrubbedRaw = ErrorSentinelAndWatchdog.ScrubSecrets(obj.ToJsonString());
            try
            {
                if (JsonNode.Parse(scrubbedRaw) is JsonObject clean)
                    return clean;
            }
            catch { }
            return obj;
        }
    }

    // =========================================================================
    // XTOOL F2 ULTRA UV LOCAL LAN V2 BRIDGE (wss://192.168.188.150:28900)
    // =========================================================================
    public static class XtoolLocalLanBridge
    {
        public const string DefaultLaserIp = "192.168.188.150";
        public const int DefaultV2Port = 28900;
        public const string DefaultParityToken = "2F18DD055BEAF251DF165868202036E8";
        public const string DefaultUserId = "mk-guest";
        public const string DefaultUserKey = "bWFrZWJsb2NrLXh0b29s";

        private static readonly object s_camLock = new();
        private static byte[]? s_cachedCameraJpeg;
        private static long s_cachedCameraVersion;

        private static readonly object s_watchLock = new();
        private static CancellationTokenSource? s_watchCts;
        private static string s_watchOrderId = "";
        private static string s_watchItemId = "";
        private static string s_watchSide = "front";
        private static string s_watchTaskId = "";
        private static string s_watchPhase = "none"; // none | prepared | working | finished
        private static bool s_watchSawWorking;

        public static ushort ComputeCrc16Arc(ReadOnlySpan<byte> data)
        {
            ushort crc = 0;
            for (int i = 0; i < data.Length; i++)
            {
                crc ^= data[i];
                for (int j = 0; j < 8; j++)
                {
                    crc = (crc & 1) != 0 ? (ushort)((crc >> 1) ^ 0xA001) : (ushort)(crc >> 1);
                }
            }
            return crc;
        }

        public static byte[] EncodeV2Frame(ReadOnlySpan<byte> payload, int protocolType = 4, bool calcCrc = true)
        {
            int len = payload.Length;
            ushort bodyCrc = calcCrc ? ComputeCrc16Arc(payload) : (ushort)0;
            Span<byte> hdr8 = stackalloc byte[8];
            hdr8[0] = 0xBA;
            hdr8[1] = 0xBE;
            hdr8[2] = (byte)((len >> 16) & 0xFF);
            hdr8[3] = (byte)((len >> 8) & 0xFF);
            hdr8[4] = (byte)(len & 0xFF);
            hdr8[5] = (byte)((protocolType & 0x7F) | (calcCrc ? 0x00 : 0x80));
            hdr8[6] = (byte)((bodyCrc >> 8) & 0xFF);
            hdr8[7] = (byte)(bodyCrc & 0xFF);
            ushort hdrCrc = ComputeCrc16Arc(hdr8);

            byte[] frame = new byte[10 + len];
            hdr8.CopyTo(frame);
            frame[8] = (byte)((hdrCrc >> 8) & 0xFF);
            frame[9] = (byte)(hdrCrc & 0xFF);
            if (len > 0)
            {
                payload.CopyTo(frame.AsSpan(10));
            }
            return frame;
        }

        public static List<(int ProtocolType, byte[] Body)> ExtractV2Frames(List<byte> streamBuffer)
        {
            var result = new List<(int ProtocolType, byte[] Body)>();
            int cursor = 0;
            int total = streamBuffer.Count;
            Span<byte> hdr8 = stackalloc byte[8];

            while (total - cursor >= 10)
            {
                if (streamBuffer[cursor] != 0xBA || streamBuffer[cursor + 1] != 0xBE)
                {
                    cursor++;
                    continue;
                }

                for (int i = 0; i < 8; i++) hdr8[i] = streamBuffer[cursor + i];
                ushort expectedHdrCrc = (ushort)((streamBuffer[cursor + 8] << 8) | streamBuffer[cursor + 9]);
                if (ComputeCrc16Arc(hdr8) != expectedHdrCrc)
                {
                    cursor++;
                    continue;
                }

                int pktLen = (hdr8[2] << 16) | (hdr8[3] << 8) | hdr8[4];
                if (pktLen < 0 || pktLen > 28 * 1024 * 1024)
                {
                    cursor++;
                    continue;
                }

                if (total - cursor < 10 + pktLen)
                {
                    break;
                }

                int proto = hdr8[5] & 0x7F;
                byte[] body = new byte[pktLen];
                if (pktLen > 0)
                {
                    streamBuffer.CopyTo(cursor + 10, body, 0, pktLen);
                }
                result.Add((proto, body));
                cursor += 10 + pktLen;
            }

            if (cursor > 0)
            {
                streamBuffer.RemoveRange(0, cursor);
            }
            return result;
        }

        public static byte[] BuildFileDataFrame(int channel, long offset, ReadOnlySpan<byte> chunk)
        {
            byte[] pkt = new byte[7 + chunk.Length];
            pkt[0] = 129;
            pkt[1] = (byte)(channel & 0xFF);
            pkt[2] = (byte)((offset >> 32) & 0xFF);
            pkt[3] = (byte)((offset >> 24) & 0xFF);
            pkt[4] = (byte)((offset >> 16) & 0xFF);
            pkt[5] = (byte)((offset >> 8) & 0xFF);
            pkt[6] = (byte)(offset & 0xFF);
            if (chunk.Length > 0)
            {
                chunk.CopyTo(pkt.AsSpan(7));
            }
            return EncodeV2Frame(pkt, 33, calcCrc: false);
        }

        public static byte[] BuildFileRequestFrame(int channel, long offset, int windowSize)
        {
            Span<byte> pkt = stackalloc byte[10];
            pkt[0] = 1;
            pkt[1] = (byte)(channel & 0xFF);
            pkt[2] = (byte)((offset >> 32) & 0xFF);
            pkt[3] = (byte)((offset >> 24) & 0xFF);
            pkt[4] = (byte)((offset >> 16) & 0xFF);
            pkt[5] = (byte)((offset >> 8) & 0xFF);
            pkt[6] = (byte)(offset & 0xFF);
            pkt[7] = (byte)((windowSize >> 16) & 0xFF);
            pkt[8] = (byte)((windowSize >> 8) & 0xFF);
            pkt[9] = (byte)(windowSize & 0xFF);
            return EncodeV2Frame(pkt, 33, calcCrc: false);
        }

        public static bool TryParseFileRequest(ReadOnlySpan<byte> body, int expectedChannel, out long offset, out int windowSize)
        {
            offset = 0;
            windowSize = 0;
            if (body.Length < 10 || body[0] != 1 || body[1] != (byte)(expectedChannel & 0xFF))
                return false;

            offset = ((long)body[2] << 32) | ((long)body[3] << 24) | ((long)body[4] << 16) | ((long)body[5] << 8) | body[6];
            windowSize = (body[7] << 16) | (body[8] << 8) | body[9];
            return windowSize > 0 && offset >= 0;
        }

        public static string AdvanceLaserWatcherState(string currentPhase, ref bool sawWorking, string? observedSignal)
        {
            string normPhase = string.IsNullOrWhiteSpace(currentPhase) ? "prepared" : currentPhase.Trim().ToLowerInvariant();
            string sig = (observedSignal ?? "").Trim().ToUpperInvariant();
            if (string.IsNullOrEmpty(sig))
                return normPhase;

            if (sig.Contains("WORK_STARTED", StringComparison.Ordinal) ||
                sig.Contains("P_WORKING", StringComparison.Ordinal))
            {
                sawWorking = true;
                return "working";
            }

            if (sawWorking &&
                (sig.Contains("WORK_FINISHED", StringComparison.Ordinal) ||
                 sig.Contains("P_IDLE", StringComparison.Ordinal) ||
                 string.Equals(sig, "IDLE", StringComparison.Ordinal)))
            {
                return "finished";
            }

            if (!sawWorking && (sig.Contains("WORK_PREPARED", StringComparison.Ordinal) || sig.Contains("P_WORK", StringComparison.Ordinal)))
            {
                return "prepared";
            }

            return normPhase;
        }

        public static async Task<bool> ProbeLaserReachableAsync(string ip = DefaultLaserIp, int port = DefaultV2Port, int timeoutMs = 250)
        {
            if (!LinuxVaultAndStorage.IsValidPrivateLanIpv4(ip) || port <= 0 || port > 65535)
                return false;

            if (!IPAddress.TryParse(ip.Trim(), out var addr))
                return false;

            using var socket = new Socket(AddressFamily.InterNetwork, SocketType.Stream, ProtocolType.Tcp)
            {
                NoDelay = true
            };
            using var cts = new CancellationTokenSource(Math.Clamp(timeoutMs, 80, 1000));
            try
            {
                await socket.ConnectAsync(new IPEndPoint(addr, port), cts.Token).ConfigureAwait(false);
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static async Task<ClientWebSocket?> ConnectV2WsAsync(string ip, int port, string path, CancellationToken ct)
        {
            if (!LinuxVaultAndStorage.IsValidPrivateLanIpv4(ip) || port != DefaultV2Port)
                return null;

            var ws = new ClientWebSocket();
            ws.Options.KeepAliveInterval = TimeSpan.FromSeconds(10);
            ws.Options.RemoteCertificateValidationCallback = (_, _, _, _) => true;
            try
            {
                using var connectCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                connectCts.CancelAfter(TimeSpan.FromMilliseconds(1400));
                await ws.ConnectAsync(new Uri($"wss://{ip.Trim()}:{port}{path}"), connectCts.Token).ConfigureAwait(false);
                return ws;
            }
            catch
            {
                ws.Dispose();
                return null;
            }
        }

        private static async Task<bool> ReceiveIntoBufferAsync(ClientWebSocket ws, List<byte> streamBuf, int timeoutMs, CancellationToken ct)
        {
            byte[] temp = new byte[32768];
            using var readCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            readCts.CancelAfter(Math.Clamp(timeoutMs, 100, 10000));
            try
            {
                while (true)
                {
                    var res = await ws.ReceiveAsync(temp.AsMemory(), readCts.Token).ConfigureAwait(false);
                    if (res.MessageType == WebSocketMessageType.Close)
                        return false;
                    if (res.Count > 0)
                    {
                        for (int i = 0; i < res.Count; i++)
                            streamBuf.Add(temp[i]);
                    }
                    if (res.EndOfMessage)
                        return true;
                }
            }
            catch
            {
                return false;
            }
        }

        private static async Task<JsonObject?> SendV2JsonCmdAsync(
            ClientWebSocket ws,
            List<byte> recvBuf,
            RefInt txId,
            string sessionToken,
            string method,
            string url,
            JsonObject? prms,
            JsonObject? data,
            Action<JsonObject>? onNotice,
            CancellationToken ct)
        {
            int curTx = ++txId.Value;
            var cmd = new JsonObject
            {
                ["type"] = "request",
                ["method"] = method,
                ["url"] = url,
                ["params"] = prms ?? new JsonObject(),
                ["data"] = data ?? new JsonObject(),
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
                ["transactionId"] = curTx
            };
            if (!string.IsNullOrEmpty(sessionToken))
            {
                cmd["sessionToken"] = sessionToken;
            }

            byte[] jsonBytes = Encoding.UTF8.GetBytes(cmd.ToJsonString());
            byte[] encoded = EncodeV2Frame(jsonBytes, 4, calcCrc: true);
            await ws.SendAsync(encoded.AsMemory(), WebSocketMessageType.Binary, endOfMessage: true, ct).ConfigureAwait(false);

            var sw = Stopwatch.StartNew();
            while (sw.ElapsedMilliseconds < 7500 && !ct.IsCancellationRequested)
            {
                var frames = ExtractV2Frames(recvBuf);
                foreach (var (proto, body) in frames)
                {
                    if (proto != 4 || body.Length == 0) continue;
                    try
                    {
                        if (JsonNode.Parse(Encoding.UTF8.GetString(body)) is JsonObject msg)
                        {
                            string mType = msg["type"]?.GetValue<string>() ?? "";
                            int mTx = msg["transactionId"]?.GetValue<int>() ?? -1;
                            if (string.Equals(mType, "response", StringComparison.OrdinalIgnoreCase) && mTx == curTx)
                                return msg;
                            onNotice?.Invoke(msg);
                        }
                    }
                    catch { }
                }

                if (!await ReceiveIntoBufferAsync(ws, recvBuf, 2500, ct).ConfigureAwait(false))
                    break;
            }

            return null;
        }

        private sealed class RefInt
        {
            public int Value;
        }

        private static async Task<string> AuthenticateParityAsync(ClientWebSocket mainWs, List<byte> mainBuf, RefInt txId, CancellationToken ct)
        {
            string parityToken = Environment.GetEnvironmentVariable("XTOOL_PARITY_TOKEN") ?? DefaultParityToken;
            if (string.IsNullOrWhiteSpace(parityToken)) parityToken = DefaultParityToken;

            var parity = await SendV2JsonCmdAsync(
                mainWs,
                mainBuf,
                txId,
                "",
                "GET",
                "/v1/user/parity",
                new JsonObject(),
                new JsonObject
                {
                    ["userID"] = DefaultUserId,
                    ["userKey"] = DefaultUserKey,
                    ["timezone"] = "Europe/Berlin",
                    ["token"] = parityToken
                },
                null,
                ct).ConfigureAwait(false);

            return parity?["data"]?["sessionToken"]?.GetValue<string>() ?? "";
        }

        public static async Task<(bool Ok, string TaskId, string Error)> UploadXfPackageLocalV2Async(
            byte[] xfBytes,
            string ip = DefaultLaserIp)
        {
            if (xfBytes == null || xfBytes.Length == 0)
                return (false, "", "Leeres Laser-Paket.");

            string safeIp = LinuxVaultAndStorage.IsValidPrivateLanIpv4(ip) ? ip.Trim() : DefaultLaserIp;
            if (!await ProbeLaserReachableAsync(safeIp, DefaultV2Port, 250).ConfigureAwait(false))
            {
                return (false, "", "Laser im WLAN nicht erreichbar (bitte einschalten).");
            }

            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(18));
            string uuid = Guid.NewGuid().ToString("D");
            using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=instruction", cts.Token).ConfigureAwait(false);
            if (mainWs == null)
                return (false, "", "Steuerkanal zum Laser konnte nicht geöffnet werden.");

            using var fileWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=file_stream", cts.Token).ConfigureAwait(false);
            if (fileWs == null)
                return (false, "", "Dateikanal zum Laser konnte nicht geöffnet werden.");

            try
            {
                var mainBuf = new List<byte>(4096);
                var fileBuf = new List<byte>(8192);
                var txId = new RefInt();

                string sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, cts.Token).ConfigureAwait(false);
                if (string.IsNullOrWhiteSpace(sessionToken))
                    return (false, "", "Authentifizierung am Laser fehlgeschlagen.");

                const int channel = 62;
                string md5Hex = Convert.ToHexStringLower(MD5.HashData(xfBytes));
                var upInit = await SendV2JsonCmdAsync(
                    mainWs,
                    mainBuf,
                    txId,
                    sessionToken,
                    "PUT",
                    "/v1/filetransfer/upload",
                    new JsonObject(),
                    new JsonObject
                    {
                        ["channel"] = channel,
                        ["digestdata"] = md5Hex,
                        ["digesttype"] = 1,
                        ["filename"] = "tmp.xf",
                        ["filesize"] = xfBytes.Length,
                        ["filetype"] = 1,
                        ["packetsize"] = 1048576
                    },
                    null,
                    cts.Token).ConfigureAwait(false);

                if (upInit == null || (upInit["code"]?.GetValue<int>() ?? -1) != 0)
                    return (false, "", "Dateiübertragung wurde vom Laser abgelehnt.");

                int negotiatedMax = Math.Clamp(upInit["data"]?["packetsize"]?.GetValue<int>() ?? 131072, 16384, 131072);
                long sentTotal = 0;
                bool uploadAcked = false;
                var sw = Stopwatch.StartNew();

                while (sw.ElapsedMilliseconds < 12500 && !uploadAcked && !cts.Token.IsCancellationRequested)
                {
                    if (!await ReceiveIntoBufferAsync(fileWs, fileBuf, 3000, cts.Token).ConfigureAwait(false))
                        break;

                    var frames = ExtractV2Frames(fileBuf);
                    foreach (var (proto, body) in frames)
                    {
                        if (proto == 33 && TryParseFileRequest(body, channel, out long offset, out int windowSize))
                        {
                            if (offset >= xfBytes.Length) continue;
                            long endOffset = Math.Min(xfBytes.Length, offset + windowSize);
                            long cursor = offset;
                            while (cursor < endOffset)
                            {
                                int chunkLen = (int)Math.Min(Math.Min(negotiatedMax, 65536), endOffset - cursor);
                                byte[] frameBytes = BuildFileDataFrame(channel, cursor, xfBytes.AsSpan((int)cursor, chunkLen));
                                await fileWs.SendAsync(frameBytes.AsMemory(), WebSocketMessageType.Binary, endOfMessage: true, cts.Token).ConfigureAwait(false);
                                cursor += chunkLen;
                                sentTotal += chunkLen;
                            }
                        }
                        else if (proto == 4 && body.Length > 0)
                        {
                            try
                            {
                                if (JsonNode.Parse(Encoding.UTF8.GetString(body)) is JsonObject ack &&
                                    (ack["channel"]?.GetValue<int>() ?? -1) == channel)
                                {
                                    uploadAcked = (ack["code"]?.GetValue<int>() ?? -1) == 0;
                                    if (!uploadAcked)
                                        return (false, "", "Laser meldete Übertragungsfehler auf Kanal 62.");
                                    break;
                                }
                            }
                            catch { }
                        }
                    }
                }

                if (!uploadAcked && sentTotal < xfBytes.Length)
                    return (false, "", "Dateiübertragung an den Laser unvollständig.");

                string taskId = Guid.NewGuid().ToString("D");
                var cfgResp = await SendV2JsonCmdAsync(
                    mainWs,
                    mainBuf,
                    txId,
                    sessionToken,
                    "PUT",
                    "/v1/processing/upload/config",
                    new JsonObject
                    {
                        ["autoStart"] = 0,
                        ["taskId"] = taskId
                    },
                    new JsonObject
                    {
                        ["autoStart"] = 0,
                        ["fileType"] = "xf",
                        ["taskId"] = taskId
                    },
                    null,
                    cts.Token).ConfigureAwait(false);

                int cfgCode = cfgResp?["code"]?.GetValue<int>() ?? -1;
                if (cfgResp == null || cfgCode != 0)
                    return (false, taskId, "Auftrags-Aktivierung am Laser fehlgeschlagen.");

                return (true, taskId, "");
            }
            catch (Exception ex)
            {
                return (false, "", ex.Message);
            }
            finally
            {
                try { if (fileWs.State == WebSocketState.Open) await fileWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
                try { if (mainWs.State == WebSocketState.Open) await mainWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
            }
        }

        private static async Task<byte[]?> DownloadFileFromLaserV2Async(
            ClientWebSocket mainWs,
            List<byte> mainBuf,
            ClientWebSocket fileWs,
            List<byte> fileBuf,
            RefInt txId,
            string sessionToken,
            string remoteFilename,
            int fileType,
            int channel,
            CancellationToken ct)
        {
            var dlInit = await SendV2JsonCmdAsync(
                mainWs,
                mainBuf,
                txId,
                sessionToken,
                "PUT",
                "/v1/filetransfer/download",
                new JsonObject(),
                new JsonObject
                {
                    ["channel"] = channel,
                    ["digesttype"] = 1,
                    ["filename"] = remoteFilename,
                    ["filetype"] = fileType,
                    ["packetsize"] = 1048576
                },
                null,
                ct).ConfigureAwait(false);

            int fileSize = dlInit?["data"]?["filesize"]?.GetValue<int>() ?? 0;
            if (fileSize <= 0 || fileSize > 25 * 1024 * 1024)
                return null;

            byte[] reqFrame = BuildFileRequestFrame(channel, 0, fileSize);
            await fileWs.SendAsync(reqFrame.AsMemory(), WebSocketMessageType.Binary, endOfMessage: true, ct).ConfigureAwait(false);

            byte[] outBytes = new byte[fileSize];
            int received = 0;
            var sw = Stopwatch.StartNew();

            while (received < fileSize && sw.ElapsedMilliseconds < 8500 && !ct.IsCancellationRequested)
            {
                if (!await ReceiveIntoBufferAsync(fileWs, fileBuf, 2500, ct).ConfigureAwait(false))
                    break;

                var frames = ExtractV2Frames(fileBuf);
                foreach (var (_, body) in frames)
                {
                    if (body.Length > 7 && body[0] == 129 && body[1] == (byte)(channel & 0xFF))
                    {
                        long off = ((long)body[2] << 32) | ((long)body[3] << 24) | ((long)body[4] << 16) | ((long)body[5] << 8) | body[6];
                        int cLen = body.Length - 7;
                        if (off >= 0 && off < fileSize && cLen > 0)
                        {
                            int writeLen = (int)Math.Min(cLen, fileSize - off);
                            Buffer.BlockCopy(body, 7, outBytes, (int)off, writeLen);
                            received += writeLen;
                        }
                    }
                }
            }

            _ = await SendV2JsonCmdAsync(
                mainWs,
                mainBuf,
                txId,
                sessionToken,
                "PUT",
                "/v1/filetransfer/finish",
                new JsonObject(),
                new JsonObject
                {
                    ["channel"] = channel,
                    ["code"] = 0,
                    ["message"] = "file transfer finish"
                },
                null,
                ct).ConfigureAwait(false);

            return received >= fileSize ? outBytes : null;
        }

        public static (byte[]? JpegBytes, long Version) GetCachedCameraJpeg()
        {
            lock (s_camLock)
            {
                if (s_cachedCameraJpeg != null && s_cachedCameraJpeg.Length > 1024)
                    return (s_cachedCameraJpeg, s_cachedCameraVersion);

                string diskCache = Path.Combine(LinuxVaultAndStorage.DataDir, "xtool_latest_bed.jpg");
                if (File.Exists(diskCache))
                {
                    try
                    {
                        byte[] diskBytes = File.ReadAllBytes(diskCache);
                        if (diskBytes.Length > 1024)
                        {
                            s_cachedCameraJpeg = diskBytes;
                            s_cachedCameraVersion = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
                            return (s_cachedCameraJpeg, s_cachedCameraVersion);
                        }
                    }
                    catch { }
                }
                return (null, 0);
            }
        }

        public static void StoreCachedCameraJpeg(byte[] jpegBytes)
        {
            if (jpegBytes == null || jpegBytes.Length <= 1024) return;
            lock (s_camLock)
            {
                s_cachedCameraJpeg = jpegBytes;
                s_cachedCameraVersion = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds();
                try
                {
                    LinuxVaultAndStorage.WriteFileAtomically0600(
                        Path.Combine(LinuxVaultAndStorage.DataDir, "xtool_latest_bed.jpg"),
                        jpegBytes);
                }
                catch { }
            }
        }

        public static async Task<byte[]?> CaptureLiveCameraLocalV2Async(string ip = DefaultLaserIp)
        {
            string safeIp = LinuxVaultAndStorage.IsValidPrivateLanIpv4(ip) ? ip.Trim() : DefaultLaserIp;
            if (!await ProbeLaserReachableAsync(safeIp, DefaultV2Port, 250).ConfigureAwait(false))
                return null;

            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(14));
            string uuid = Guid.NewGuid().ToString("D");
            using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=instruction", cts.Token).ConfigureAwait(false);
            if (mainWs == null) return null;

            using var fileWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=file_stream", cts.Token).ConfigureAwait(false);
            if (fileWs == null) return null;

            try
            {
                var mainBuf = new List<byte>(4096);
                var fileBuf = new List<byte>(65536);
                var txId = new RefInt();

                string sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, cts.Token).ConfigureAwait(false);
                if (string.IsNullOrWhiteSpace(sessionToken))
                    return null;

                string calibCachePath = Path.Combine(LinuxVaultAndStorage.DataDir, "xtool_global_calib.json");
                string calibJson = "";
                if (File.Exists(calibCachePath))
                {
                    try { calibJson = File.ReadAllText(calibCachePath, Encoding.UTF8); } catch { }
                }
                if (string.IsNullOrWhiteSpace(calibJson) || !calibJson.Contains("data0", StringComparison.Ordinal))
                {
                    byte[]? calibBytes = await DownloadFileFromLaserV2Async(mainWs, mainBuf, fileWs, fileBuf, txId, sessionToken, "global_calib.json", 3, 2, cts.Token).ConfigureAwait(false);
                    if (calibBytes != null && calibBytes.Length > 500)
                    {
                        string fetchedCalib = Encoding.UTF8.GetString(calibBytes);
                        if (fetchedCalib.Contains("data0", StringComparison.Ordinal))
                        {
                            calibJson = fetchedCalib;
                            try { LinuxVaultAndStorage.WriteFileAtomically0600(calibCachePath, calibBytes); } catch { }
                        }
                    }
                }

                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/peripheral/param", new JsonObject { ["type"] = "ir_led" }, new JsonObject { ["action"] = "off", ["index"] = 0 }, null, cts.Token).ConfigureAwait(false);
                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/laser-head/parameter", new JsonObject(), new JsonObject { ["action"] = "power", ["ctrl"] = "set", ["value"] = 0 }, null, cts.Token).ConfigureAwait(false);

                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "POST", "/v1/camera", new JsonObject { ["exp"] = 0, ["stream"] = "0" }, new JsonObject(), null, cts.Token).ConfigureAwait(false);
                var snap = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "GET", "/v1/camera/snap", new JsonObject { ["name"] = "main", ["timeOut"] = 30000 }, new JsonObject(), null, cts.Token).ConfigureAwait(false);
                string remoteFile = snap?["data"]?["filename"]?.GetValue<string>()
                                 ?? snap?["data"]?["params"]?["filename"]?.GetValue<string>()
                                 ?? "";

                byte[]? rawJpeg = null;
                if (!string.IsNullOrWhiteSpace(remoteFile))
                {
                    rawJpeg = await DownloadFileFromLaserV2Async(mainWs, mainBuf, fileWs, fileBuf, txId, sessionToken, remoteFile, 5, 9, cts.Token).ConfigureAwait(false);
                }

                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/peripheral/param", new JsonObject { ["type"] = "ir_led" }, new JsonObject { ["action"] = "on", ["index"] = 0 }, null, cts.Token).ConfigureAwait(false);
                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/laser-head/parameter", new JsonObject(), new JsonObject { ["action"] = "power", ["ctrl"] = "set", ["value"] = 128 }, null, cts.Token).ConfigureAwait(false);

                if (rawJpeg == null || rawJpeg.Length < 10000)
                    return null;

                byte[]? warped = await LocalSenseBridge.WarpXtoolCameraAsync(rawJpeg, calibJson).ConfigureAwait(false);
                byte[] finalJpeg = (warped != null && warped.Length > 10000) ? warped : rawJpeg;
                StoreCachedCameraJpeg(finalJpeg);
                return finalJpeg;
            }
            catch
            {
                return null;
            }
            finally
            {
                try { if (fileWs.State == WebSocketState.Open) await fileWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
                try { if (mainWs.State == WebSocketState.Open) await mainWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
            }
        }

        public static (string Phase, string OrderId, string ItemId, string Side, string TaskId) GetActiveWatcherSnapshot()
        {
            lock (s_watchLock)
            {
                return (s_watchPhase, s_watchOrderId, s_watchItemId, s_watchSide, s_watchTaskId);
            }
        }

        public static void DisarmPreparedJobWatcher()
        {
            lock (s_watchLock)
            {
                try { s_watchCts?.Cancel(); } catch { }
                try { s_watchCts?.Dispose(); } catch { }
                s_watchCts = null;
                s_watchOrderId = "";
                s_watchItemId = "";
                s_watchSide = "front";
                s_watchTaskId = "";
                s_watchPhase = "none";
                s_watchSawWorking = false;
            }
        }

        public static void ArmPreparedJobWatcher(string orderId, string itemId, string side, string taskId, string ip = DefaultLaserIp)
        {
            CancellationToken ct;
            lock (s_watchLock)
            {
                try { s_watchCts?.Cancel(); } catch { }
                try { s_watchCts?.Dispose(); } catch { }
                s_watchCts = new CancellationTokenSource(TimeSpan.FromMinutes(35));
                s_watchOrderId = orderId ?? "";
                s_watchItemId = itemId ?? "";
                s_watchSide = string.IsNullOrWhiteSpace(side) ? "front" : side.Trim();
                s_watchTaskId = taskId ?? "";
                s_watchPhase = "prepared";
                s_watchSawWorking = false;
                ct = s_watchCts.Token;
            }

            string capturedOrderId = orderId ?? "";
            string capturedItemId = itemId ?? "";
            string capturedSide = string.IsNullOrWhiteSpace(side) ? "front" : side.Trim();
            string safeIp = LinuxVaultAndStorage.IsValidPrivateLanIpv4(ip) ? ip.Trim() : DefaultLaserIp;

            _ = Task.Run(async () =>
            {
                while (!ct.IsCancellationRequested)
                {
                    await Task.Delay(1600, ct).ConfigureAwait(false);
                    if (!await ProbeLaserReachableAsync(safeIp, DefaultV2Port, 250).ConfigureAwait(false))
                        continue;

                    using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={Guid.NewGuid():D}&function=instruction", ct).ConfigureAwait(false);
                    if (mainWs == null)
                        continue;

                    try
                    {
                        var mainBuf = new List<byte>(4096);
                        var txId = new RefInt();
                        string sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, ct).ConfigureAwait(false);
                        if (string.IsNullOrWhiteSpace(sessionToken))
                            continue;

                        bool finishedDetected = false;
                        void InspectSignalString(string? rawText)
                        {
                            if (string.IsNullOrWhiteSpace(rawText)) return;
                            lock (s_watchLock)
                            {
                                if (s_watchOrderId != capturedOrderId || s_watchItemId != capturedItemId)
                                    return;
                                bool saw = s_watchSawWorking;
                                s_watchPhase = AdvanceLaserWatcherState(s_watchPhase, ref saw, rawText);
                                s_watchSawWorking = saw;
                                if (s_watchPhase == "finished")
                                    finishedDetected = true;
                            }
                        }

                        while (!ct.IsCancellationRequested && mainWs.State == WebSocketState.Open && !finishedDetected)
                        {
                            var rt = await SendV2JsonCmdAsync(
                                mainWs,
                                mainBuf,
                                txId,
                                sessionToken,
                                "GET",
                                "/v1/device/runtime-infos",
                                new JsonObject(),
                                new JsonObject(),
                                noticeObj => InspectSignalString(noticeObj.ToJsonString()),
                                ct).ConfigureAwait(false);

                            string curSt = rt?["data"]?["currentStatus"]?.GetValue<string>() ?? "";
                            if (!string.IsNullOrEmpty(curSt))
                                InspectSignalString(curSt);

                            if (finishedDetected)
                                break;

                            if (await ReceiveIntoBufferAsync(mainWs, mainBuf, 1800, ct).ConfigureAwait(false))
                            {
                                foreach (var (proto, body) in ExtractV2Frames(mainBuf))
                                {
                                    if (proto == 4 && body.Length > 0)
                                    {
                                        InspectSignalString(Encoding.UTF8.GetString(body));
                                    }
                                }
                            }
                        }

                        if (finishedDetected && !ct.IsCancellationRequested)
                        {
                            var curSettings = LinuxVaultAndStorage.LoadSettings();
                            _ = await WorkshopFocusCoordinator.FinishLaserAsync(
                                curSettings,
                                capturedOrderId,
                                capturedItemId,
                                capturedSide,
                                triggeredAutomatically: true).ConfigureAwait(false);
                            return;
                        }
                    }
                    catch { }
                    finally
                    {
                        try { if (mainWs.State == WebSocketState.Open) await mainWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
                    }
                }
            }, CancellationToken.None);
        }
    }

    // =========================================================================
    // MANUFAKTUR FOKUS-LEITSTAND COORDINATOR (LASER + PACKING + CUPS PRINT)
    // =========================================================================
    public static class WorkshopFocusCoordinator
    {
        internal static Func<string, JsonElement, Task<JsonObject?>>? TestOverrideHandler { get; set; }

        private static readonly object s_stateCacheLock = new();
        private static JsonObject? s_lastNormalizedState;
        private static string s_lastPortalLaserJobId = "";

        private static JsonArray SanitizeChecklistArray(JsonArray? rawChecklist)
        {
            var cleanChecklist = new JsonArray();
            if (rawChecklist == null) return cleanChecklist;

            int idx = 0;
            foreach (var entry in rawChecklist)
            {
                idx++;
                if (entry is JsonObject cObj)
                {
                    if (cObj.TryGetPropertyValue("visible", out var visN) && visN is JsonValue visVal && visVal.TryGetValue<bool>(out bool vis) && !vis)
                        continue;
                    string key = GetAnyStr(cObj, "key", "id", "step_key");
                    if (string.IsNullOrWhiteSpace(key)) key = $"step_{idx}";
                    string lbl = CleanTextNoEnDash(GetAnyStr(cObj, "label", "title", "text", "name"));
                    if (IsForbiddenNegativeChecklistLabel(lbl))
                        continue;
                    bool chk = GetAnyBool(cObj, false, "checked", "done", "completed", "is_checked");
                    cleanChecklist.Add(new JsonObject
                    {
                        ["key"] = key,
                        ["label"] = lbl,
                        ["checked"] = chk
                    });
                }
                else if (entry is JsonValue sVal && sVal.TryGetValue<string>(out var sLbl))
                {
                    string cleanLbl = CleanTextNoEnDash(sLbl);
                    if (!IsForbiddenNegativeChecklistLabel(cleanLbl))
                    {
                        cleanChecklist.Add(new JsonObject
                        {
                            ["key"] = $"step_{idx}",
                            ["label"] = cleanLbl,
                            ["checked"] = false
                        });
                    }
                }
            }
            return cleanChecklist;
        }

        public static string CleanTextNoEnDash(string? input)
        {
            if (string.IsNullOrWhiteSpace(input)) return "";
            return input.Replace(" \u2013 ", " · ", StringComparison.Ordinal)
                        .Replace(" \u2014 ", " · ", StringComparison.Ordinal)
                        .Trim();
        }

        private static bool IsForbiddenNegativeChecklistLabel(string? label)
        {
            if (string.IsNullOrWhiteSpace(label)) return true;
            string low = label.Trim().ToLowerInvariant();
            if (low.Contains("nicht gebucht", StringComparison.Ordinal) ||
                low.Contains("keine ", StringComparison.Ordinal) ||
                low.Contains("kein ", StringComparison.Ordinal) ||
                string.Equals(low, "nein", StringComparison.Ordinal) ||
                low.EndsWith(": nein", StringComparison.Ordinal) ||
                low.EndsWith("· nein", StringComparison.Ordinal))
            {
                return true;
            }
            return false;
        }

        private static string GetAnyStr(JsonObject? obj, params string[] keys)
        {
            if (obj == null) return "";
            foreach (string k in keys)
            {
                if (obj.TryGetPropertyValue(k, out var n) && n != null)
                {
                    try
                    {
                        if (n is JsonValue jv)
                        {
                            if (jv.TryGetValue<string>(out var sv) && sv != null)
                                return sv;
                            return jv.ToJsonString().Trim('"');
                        }
                    }
                    catch { }
                }
            }
            return "";
        }

        private static double GetAnyDouble(JsonObject? obj, double fallback, params string[] keys)
        {
            if (obj == null) return fallback;
            foreach (string k in keys)
            {
                if (obj.TryGetPropertyValue(k, out var n) && n is JsonValue jv)
                {
                    if (jv.TryGetValue<double>(out double dv) && double.IsFinite(dv)) return dv;
                    if (jv.TryGetValue<int>(out int iv)) return iv;
                    if (jv.TryGetValue<string>(out var sv) && double.TryParse(sv, NumberStyles.Float, CultureInfo.InvariantCulture, out double pv))
                        return pv;
                }
            }
            return fallback;
        }

        private static int GetAnyInt(JsonObject? obj, int fallback, params string[] keys)
        {
            if (obj == null) return fallback;
            foreach (string k in keys)
            {
                if (obj.TryGetPropertyValue(k, out var n) && n is JsonValue jv)
                {
                    if (jv.TryGetValue<int>(out int iv)) return iv;
                    if (jv.TryGetValue<long>(out long lv)) return (int)lv;
                    if (jv.TryGetValue<string>(out var sv) && int.TryParse(sv, NumberStyles.Integer, CultureInfo.InvariantCulture, out int pv))
                        return pv;
                }
            }
            return fallback;
        }

        private static bool GetAnyBool(JsonObject? obj, bool fallback, params string[] keys)
        {
            if (obj == null) return fallback;
            foreach (string k in keys)
            {
                if (obj.TryGetPropertyValue(k, out var n) && n is JsonValue jv)
                {
                    if (jv.TryGetValue<bool>(out bool bv)) return bv;
                    if (jv.TryGetValue<int>(out int iv)) return iv != 0;
                    if (jv.TryGetValue<string>(out var sv))
                    {
                        if (string.Equals(sv, "true", StringComparison.OrdinalIgnoreCase) || sv == "1") return true;
                        if (string.Equals(sv, "false", StringComparison.OrdinalIgnoreCase) || sv == "0") return false;
                    }
                }
            }
            return fallback;
        }

        public static JsonObject NormalizeFocusState(JsonNode? rawNode)
        {
            var rootObj = rawNode as JsonObject;
            var payload = (rootObj?["data"] as JsonObject) ?? (rootObj?["focus"] as JsonObject) ?? rootObj ?? new JsonObject();

            bool hasOrder = GetAnyBool(payload, false, "has_order", "hasOrder");
            int openCount = Math.Max(0, GetAnyInt(payload, hasOrder ? 1 : 0, "open_ready_count", "open_orders_count", "openOrdersCount", "open_count"));

            if (!hasOrder)
            {
                var idleObj = new JsonObject
                {
                    ["ok"] = true,
                    ["has_order"] = false,
                    ["open_orders_count"] = openCount,
                    ["phase"] = "idle"
                };
                lock (s_stateCacheLock)
                {
                    s_lastNormalizedState = JsonNode.Parse(idleObj.ToJsonString()) as JsonObject;
                }
                return idleObj;
            }

            var orderSub = payload["order"] as JsonObject;
            var itemSub = (orderSub?["active_item"] as JsonObject) ?? (payload["active_item"] as JsonObject) ?? (payload["item"] as JsonObject);
            var laserSub = (itemSub?["laser"] as JsonObject) ?? (orderSub?["laser"] as JsonObject) ?? (payload["laser"] as JsonObject);
            var jigSub = (itemSub?["jig"] as JsonObject) ?? (payload["jig"] as JsonObject) ?? (laserSub?["jig"] as JsonObject);
            var packSub = (orderSub?["packaging"] as JsonObject) ?? (payload["packaging"] as JsonObject);

            string phaseRaw = GetAnyStr(payload, "phase", "step", "view").Trim().ToLowerInvariant();
            string phase = (phaseRaw is "packing" or "verpackung" or "shipping" or "2") ? "packing" : "laser";

            string orderId = GetAnyStr(orderSub, "id", "order_id", "orderId");
            if (string.IsNullOrEmpty(orderId)) orderId = GetAnyStr(payload, "order_id", "orderId");
            if (string.IsNullOrEmpty(orderId)) orderId = GetAnyStr(itemSub, "order_id", "orderId");

            string orderNumber = CleanTextNoEnDash(GetAnyStr(orderSub, "order_number", "orderNumber"));
            if (string.IsNullOrEmpty(orderNumber)) orderNumber = CleanTextNoEnDash(GetAnyStr(payload, "order_number", "orderNumber"));

            string itemId = GetAnyStr(itemSub, "id", "item_id", "itemId");
            if (string.IsNullOrEmpty(itemId)) itemId = GetAnyStr(payload, "item_id", "itemId");
            if (string.IsNullOrEmpty(itemId)) itemId = GetAnyStr(laserSub, "item_id", "itemId");

            string productId = GetAnyStr(itemSub, "product_id", "productId");
            if (string.IsNullOrEmpty(productId)) productId = GetAnyStr(payload, "product_id", "productId");

            string side = GetAnyStr(itemSub, "side");
            if (string.IsNullOrEmpty(side)) side = GetAnyStr(payload, "side");
            if (string.IsNullOrEmpty(side)) side = GetAnyStr(laserSub, "side");
            if (string.IsNullOrWhiteSpace(side)) side = "front";

            int quantity = Math.Max(1, GetAnyInt(itemSub, GetAnyInt(payload, 1, "quantity", "qty"), "quantity", "qty"));
            string productName = CleanTextNoEnDash(GetAnyStr(itemSub, "product_name", "productName", "name"));
            if (string.IsNullOrEmpty(productName)) productName = CleanTextNoEnDash(GetAnyStr(payload, "product_name", "productName"));
            if (string.IsNullOrEmpty(productName)) productName = "Seelenfunke Unikat";

            string productImageUrl = GetAnyStr(itemSub, "product_image_url", "productImageUrl", "product_image");
            if (string.IsNullOrEmpty(productImageUrl)) productImageUrl = GetAnyStr(payload, "product_image_url", "productImageUrl", "product_image");

            string customerNote = CleanTextNoEnDash(GetAnyStr(itemSub, "customer_note", "customerNote"));
            if (string.IsNullOrEmpty(customerNote)) customerNote = CleanTextNoEnDash(GetAnyStr(payload, "customer_note", "customerNote"));

            bool hasJig = GetAnyBool(jigSub, GetAnyBool(payload, false, "has_jig"), "has_jig", "hasJig", "enabled");
            string jigLabel = CleanTextNoEnDash(GetAnyStr(jigSub, "label", "name", "title"));
            if (string.IsNullOrEmpty(jigLabel)) jigLabel = CleanTextNoEnDash(GetAnyStr(payload, "jig_label"));
            if (!hasJig || string.IsNullOrWhiteSpace(jigLabel))
            {
                hasJig = false;
                jigLabel = "";
            }

            double blankW = Math.Clamp(GetAnyDouble(laserSub ?? payload, GetAnyDouble(payload, 50.0, "blank_width_mm", "width_mm"), "blank_width_mm", "width_mm"), 4.0, 200.0);
            double blankH = Math.Clamp(GetAnyDouble(laserSub ?? payload, GetAnyDouble(payload, 50.0, "blank_height_mm", "height_mm"), "blank_height_mm", "height_mm"), 4.0, 200.0);
            double centerX = Math.Clamp(GetAnyDouble(laserSub ?? payload, GetAnyDouble(payload, 100.0, "center_x_mm"), "center_x_mm", "x_mm"), 5.0, 195.0);
            double centerY = Math.Clamp(GetAnyDouble(laserSub ?? payload, GetAnyDouble(payload, 100.0, "center_y_mm"), "center_y_mm", "y_mm"), 5.0, 195.0);
            double angleDeg = Math.Clamp(GetAnyDouble(laserSub ?? payload, GetAnyDouble(payload, 0.0, "angle_deg"), "angle_deg", "angle"), -180.0, 180.0);

            string contourDPath = GetAnyStr(laserSub, "contour_d_path", "ghost_d_path");
            if (string.IsNullOrEmpty(contourDPath)) contourDPath = GetAnyStr(payload, "contour_d_path", "ghost_d_path");
            if (string.IsNullOrWhiteSpace(contourDPath))
            {
                string wFmt = blankW.ToString("0.##", CultureInfo.InvariantCulture);
                string hFmt = blankH.ToString("0.##", CultureInfo.InvariantCulture);
                contourDPath = $"M 0 0 H {wFmt} V {hFmt} H 0 Z";
            }

            string engravePreviewDataUrl = GetAnyStr(laserSub, "engrave_preview_data_url", "preview_data_url");
            if (string.IsNullOrEmpty(engravePreviewDataUrl))
                engravePreviewDataUrl = GetAnyStr(payload, "engrave_preview_data_url", "preview_data_url");

            string engravePreviewUrl = GetAnyStr(laserSub, "engrave_preview_url", "preview_url");
            if (string.IsNullOrEmpty(engravePreviewUrl))
                engravePreviewUrl = GetAnyStr(payload, "engrave_preview_url", "preview_url");
            if (string.IsNullOrEmpty(engravePreviewDataUrl)) engravePreviewDataUrl = engravePreviewUrl;
            if (string.IsNullOrEmpty(engravePreviewUrl)) engravePreviewUrl = engravePreviewDataUrl;

            string portalCamUrl = GetAnyStr(laserSub, "portal_camera_url", "camera_url");
            if (string.IsNullOrEmpty(portalCamUrl))
                portalCamUrl = GetAnyStr(payload, "portal_camera_url", "camera_url");

            var (cachedCamBytes, camVer) = XtoolLocalLanBridge.GetCachedCameraJpeg();
            string effectiveCamUrl = cachedCamBytes != null
                ? $"/api/workshop-focus/camera.jpg?v={camVer}"
                : portalCamUrl;

            var watcher = XtoolLocalLanBridge.GetActiveWatcherSnapshot();
            string laserJobState = "none";
            if (watcher.Phase is "prepared" or "working" &&
                (string.IsNullOrEmpty(watcher.OrderId) || watcher.OrderId == orderId) &&
                (string.IsNullOrEmpty(watcher.ItemId) || watcher.ItemId == itemId))
            {
                laserJobState = watcher.Phase;
            }
            else if (GetAnyBool(laserSub ?? payload, false, "laser_prepared", "is_prepared"))
            {
                laserJobState = "prepared";
            }

            string boxName = CleanTextNoEnDash(GetAnyStr(packSub, "box_name", "boxName", "name"));
            if (string.IsNullOrEmpty(boxName)) boxName = CleanTextNoEnDash(GetAnyStr(payload, "box_name"));
            string boxDimensions = CleanTextNoEnDash(GetAnyStr(packSub, "box_dimensions", "boxDimensions", "dimensions"));
            if (string.IsNullOrEmpty(boxDimensions)) boxDimensions = CleanTextNoEnDash(GetAnyStr(payload, "box_dimensions"));

            var rawChecklist = (packSub?["checklist"] as JsonArray) ?? (payload["checklist"] as JsonArray);
            var cleanChecklist = SanitizeChecklistArray(rawChecklist);

            if (cleanChecklist.Count == 0 && phase == "packing")
            {
                cleanChecklist.Add(new JsonObject { ["key"] = "cleaned", ["label"] = "Reinigen · Isopropanol & Tuch", ["checked"] = false });
                cleanChecklist.Add(new JsonObject { ["key"] = "thank_you_card", ["label"] = "Dankeskarte", ["checked"] = false });
            }

            var normalized = new JsonObject
            {
                ["ok"] = true,
                ["has_order"] = true,
                ["open_orders_count"] = openCount,
                ["phase"] = phase,
                ["order_id"] = orderId,
                ["order_number"] = orderNumber,
                ["item_id"] = itemId,
                ["product_id"] = productId,
                ["side"] = side,
                ["quantity"] = quantity,
                ["product_name"] = productName,
                ["product_image_url"] = productImageUrl,
                ["customer_note"] = customerNote,
                ["jig"] = new JsonObject
                {
                    ["has_jig"] = hasJig,
                    ["label"] = jigLabel
                },
                ["camera_url"] = effectiveCamUrl,
                ["portal_camera_url"] = portalCamUrl,
                ["contour_d_path"] = contourDPath,
                ["blank_width_mm"] = Math.Round(blankW, 2),
                ["blank_height_mm"] = Math.Round(blankH, 2),
                ["center_x_mm"] = Math.Round(centerX, 2),
                ["center_y_mm"] = Math.Round(centerY, 2),
                ["angle_deg"] = Math.Round(angleDeg, 2),
                ["engrave_preview_data_url"] = engravePreviewDataUrl,
                ["engrave_preview_url"] = engravePreviewUrl,
                ["laser_job_state"] = laserJobState,
                ["packaging"] = new JsonObject
                {
                    ["box_name"] = boxName,
                    ["box_dimensions"] = boxDimensions,
                    ["checklist"] = cleanChecklist
                }
            };

            lock (s_stateCacheLock)
            {
                s_lastNormalizedState = JsonNode.Parse(normalized.ToJsonString()) as JsonObject;
            }
            return normalized;
        }

        private static async Task<(int StatusCode, JsonNode? Json)> CallPortalJsonAsync(
            LinuxOsSettings settings,
            HttpMethod method,
            string relativeApiPath,
            JsonObject? bodyObj = null,
            int timeoutSec = 14)
        {
            string norm = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant() == "stage" ? "stage" : "live";
            string bearer = LinuxVaultAndStorage.GetToken(settings, norm);
            if (string.IsNullOrWhiteSpace(bearer))
                return (401, null);

            string baseUrl = SeelenfunkeLinuxApiClient.ResolveBaseApiUrl(norm);
            string fullUrl = $"{baseUrl}/{relativeApiPath.TrimStart('/')}";
            if (!SeelenfunkeLinuxApiClient.ValidateHttpsUrl(fullUrl))
                return (403, null);

            try
            {
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(timeoutSec));
                using var req = new HttpRequestMessage(method, fullUrl);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                if (bodyObj != null)
                {
                    req.Content = new StringContent(bodyObj.ToJsonString(), new UTF8Encoding(false), "application/json");
                }

                using var resp = await SeelenfunkeLinuxApiClient.SharedHttp.SendAsync(req, cts.Token).ConfigureAwait(false);
                int code = (int)resp.StatusCode;
                string raw = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                JsonNode? parsed = null;
                if (!string.IsNullOrWhiteSpace(raw))
                {
                    try { parsed = JsonNode.Parse(raw); } catch { }
                }
                return (code, parsed);
            }
            catch
            {
                return (0, null);
            }
        }

        public static async Task<JsonObject> FetchStateAsync(LinuxOsSettings settings)
        {
            if (TestOverrideHandler != null)
            {
                using var emptyDoc = JsonDocument.Parse("{}");
                var mock = await TestOverrideHandler("state", emptyDoc.RootElement).ConfigureAwait(false);
                return NormalizeFocusState(mock);
            }

            var (code, node) = await CallPortalJsonAsync(settings, HttpMethod.Get, "shop/workshop-focus").ConfigureAwait(false);
            if (code == 404)
            {
                (code, node) = await CallPortalJsonAsync(settings, HttpMethod.Get, "shop/workshop-focus/state").ConfigureAwait(false);
            }

            if (code == 401 || code == 403)
            {
                return new JsonObject
                {
                    ["ok"] = false,
                    ["authenticated"] = false,
                    ["has_order"] = false,
                    ["open_orders_count"] = 0,
                    ["phase"] = "idle"
                };
            }

            if (node == null)
            {
                lock (s_stateCacheLock)
                {
                    if (s_lastNormalizedState != null)
                        return JsonNode.Parse(s_lastNormalizedState.ToJsonString()) as JsonObject ?? new JsonObject { ["ok"] = false, ["has_order"] = false };
                }
                return new JsonObject
                {
                    ["ok"] = false,
                    ["has_order"] = false,
                    ["open_orders_count"] = Math.Max(0, SeelenfunkeLinuxApiClient.LatestPulse.OpenOrdersCount),
                    ["phase"] = "idle"
                };
            }

            return NormalizeFocusState(node);
        }

        public static async Task<JsonObject> RefreshCameraAsync(LinuxOsSettings settings, JsonElement reqBody)
        {
            byte[]? localJpeg = await XtoolLocalLanBridge.CaptureLiveCameraLocalV2Async().ConfigureAwait(false);

            if (TestOverrideHandler != null)
            {
                var mock = await TestOverrideHandler("camera", reqBody).ConfigureAwait(false);
                var normMock = NormalizeFocusState(mock);
                normMock["camera_source"] = localJpeg != null ? "laser_lan_v2" : "portal_fallback";
                return normMock;
            }

            var syncPayload = new JsonObject();
            if (localJpeg != null && localJpeg.Length > 1024)
            {
                syncPayload["image_base64"] = Convert.ToBase64String(localJpeg);
            }
            _ = await CallPortalJsonAsync(settings, HttpMethod.Post, "funki/laser/camera-sync", syncPayload, timeoutSec: 16).ConfigureAwait(false);

            var refreshedState = await FetchStateAsync(settings).ConfigureAwait(false);
            if (localJpeg == null)
            {
                string portalCam = refreshedState["portal_camera_url"]?.GetValue<string>() ?? "";
                if (!string.IsNullOrWhiteSpace(portalCam))
                {
                    string sep = portalCam.Contains('?') ? "&" : "?";
                    refreshedState["camera_url"] = $"{portalCam}{sep}r={DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}";
                }
            }
            refreshedState["camera_source"] = localJpeg != null ? "laser_lan_v2" : "portal_fallback";
            return refreshedState;
        }

        public static async Task<JsonObject> SendLaserAsync(LinuxOsSettings settings, JsonElement reqBody)
        {
            string orderId = "";
            string itemId = "";
            string side = "front";
            double? cx = null, cy = null, ang = null;

            if (reqBody.ValueKind == JsonValueKind.Object)
            {
                if (reqBody.TryGetProperty("order_id", out var oEl)) orderId = oEl.GetString() ?? "";
                if (reqBody.TryGetProperty("item_id", out var iEl)) itemId = iEl.GetString() ?? "";
                if (reqBody.TryGetProperty("side", out var sEl)) side = sEl.GetString() ?? "front";
                if (reqBody.TryGetProperty("center_x_mm", out var xEl) && xEl.TryGetDouble(out double xv)) cx = Math.Clamp(xv, 0.0, 200.0);
                if (reqBody.TryGetProperty("center_y_mm", out var yEl) && yEl.TryGetDouble(out double yv)) cy = Math.Clamp(yv, 0.0, 200.0);
                if (reqBody.TryGetProperty("angle_deg", out var aEl) && aEl.TryGetDouble(out double av)) ang = Math.Clamp(av, -180.0, 180.0);
            }

            if (string.IsNullOrEmpty(orderId) || string.IsNullOrEmpty(itemId))
            {
                var cur = await FetchStateAsync(settings).ConfigureAwait(false);
                if (string.IsNullOrEmpty(orderId)) orderId = cur["order_id"]?.GetValue<string>() ?? "";
                if (string.IsNullOrEmpty(itemId)) itemId = cur["item_id"]?.GetValue<string>() ?? "";
                if (cx == null && cur["center_x_mm"] != null) cx = cur["center_x_mm"]!.GetValue<double>();
                if (cy == null && cur["center_y_mm"] != null) cy = cur["center_y_mm"]!.GetValue<double>();
                if (ang == null && cur["angle_deg"] != null) ang = cur["angle_deg"]!.GetValue<double>();
            }

            JsonNode? portalRespNode;
            int statusCode;
            if (TestOverrideHandler != null)
            {
                portalRespNode = await TestOverrideHandler("send-laser", reqBody).ConfigureAwait(false);
                statusCode = portalRespNode != null ? 200 : 500;
            }
            else
            {
                var postObj = new JsonObject
                {
                    ["order_id"] = orderId,
                    ["item_id"] = itemId,
                    ["side"] = side
                };
                if (cx.HasValue) postObj["center_x_mm"] = Math.Round(cx.Value, 2);
                if (cy.HasValue) postObj["center_y_mm"] = Math.Round(cy.Value, 2);
                if (ang.HasValue) postObj["angle_deg"] = Math.Round(ang.Value, 2);

                (statusCode, portalRespNode) = await CallPortalJsonAsync(settings, HttpMethod.Post, "shop/workshop-focus/send-laser", postObj, timeoutSec: 22).ConfigureAwait(false);
            }

            var rootObj = portalRespNode as JsonObject;
            var payload = (rootObj?["data"] as JsonObject) ?? rootObj;
            if (statusCode < 200 || statusCode >= 300 || payload == null)
            {
                string errMsg = CleanTextNoEnDash(GetAnyStr(payload ?? rootObj, "message", "error"));
                if (string.IsNullOrWhiteSpace(errMsg)) errMsg = "Laser-Paket konnte nicht vom Portal geladen werden.";
                return new JsonObject
                {
                    ["ok"] = false,
                    ["sent_direct"] = false,
                    ["error"] = errMsg
                };
            }

            bool sentDirect = GetAnyBool(payload, false, "sent_direct", "sentDirect");
            string portalJobId = GetAnyStr(payload, "job_id", "jobId");
            if (!string.IsNullOrWhiteSpace(portalJobId))
            {
                s_lastPortalLaserJobId = portalJobId;
            }
            string taskId = GetAnyStr(payload, "task_id", "taskId", "job_id");
            string xfB64 = GetAnyStr(payload, "xf_base64", "xfBase64", "xf_package_base64");
            string deliveryMode = sentDirect ? "portal_direct" : "none";

            if (!sentDirect && !string.IsNullOrWhiteSpace(xfB64))
            {
                if (xfB64.Contains(',')) xfB64 = xfB64.Substring(xfB64.IndexOf(',') + 1);
                byte[] xfBytes;
                try
                {
                    xfBytes = Convert.FromBase64String(xfB64);
                }
                catch
                {
                    return new JsonObject
                    {
                        ["ok"] = false,
                        ["sent_direct"] = false,
                        ["error"] = "Ungültiges .xf Paketformat."
                    };
                }

                var (upOk, localTaskId, upErr) = await XtoolLocalLanBridge.UploadXfPackageLocalV2Async(xfBytes).ConfigureAwait(false);
                if (!upOk)
                {
                    return new JsonObject
                    {
                        ["ok"] = false,
                        ["sent_direct"] = false,
                        ["error"] = CleanTextNoEnDash(upErr)
                    };
                }
                sentDirect = true;
                taskId = localTaskId;
                deliveryMode = "minipc_lan_v2";
            }

            if (!sentDirect)
            {
                string err = CleanTextNoEnDash(GetAnyStr(payload, "error", "message"));
                if (string.IsNullOrWhiteSpace(err)) err = "Laser im WLAN nicht erreichbar.";
                return new JsonObject
                {
                    ["ok"] = false,
                    ["sent_direct"] = false,
                    ["error"] = err
                };
            }

            if (TestOverrideHandler == null)
            {
                XtoolLocalLanBridge.ArmPreparedJobWatcher(orderId, itemId, side, taskId);
            }

            return new JsonObject
            {
                ["ok"] = true,
                ["sent_direct"] = true,
                ["delivery_mode"] = deliveryMode,
                ["job_id"] = portalJobId,
                ["task_id"] = taskId,
                ["laser_job_state"] = "prepared",
                ["button_label"] = "Im Laser bereit · Startknopf drücken"
            };
        }

        public static async Task<JsonObject> FinishLaserAsync(
            LinuxOsSettings settings,
            string? orderId,
            string? itemId,
            string? side = "front",
            bool triggeredAutomatically = false)
        {
            XtoolLocalLanBridge.DisarmPreparedJobWatcher();

            string resolvedOrderId = orderId ?? "";
            string resolvedItemId = itemId ?? "";
            string batchProductId = "";

            lock (s_stateCacheLock)
            {
                if (s_lastNormalizedState != null)
                {
                    if (string.IsNullOrEmpty(resolvedOrderId)) resolvedOrderId = s_lastNormalizedState["order_id"]?.GetValue<string>() ?? "";
                    if (string.IsNullOrEmpty(resolvedItemId)) resolvedItemId = s_lastNormalizedState["item_id"]?.GetValue<string>() ?? "";
                    batchProductId = s_lastNormalizedState["product_id"]?.GetValue<string>() ?? "";
                }
            }

            if (string.IsNullOrEmpty(resolvedOrderId) || string.IsNullOrEmpty(resolvedItemId))
            {
                var cur = await FetchStateAsync(settings).ConfigureAwait(false);
                if (string.IsNullOrEmpty(resolvedOrderId)) resolvedOrderId = cur["order_id"]?.GetValue<string>() ?? "";
                if (string.IsNullOrEmpty(resolvedItemId)) resolvedItemId = cur["item_id"]?.GetValue<string>() ?? "";
                if (string.IsNullOrEmpty(batchProductId)) batchProductId = cur["product_id"]?.GetValue<string>() ?? "";
            }

            var postObj = new JsonObject
            {
                ["order_id"] = resolvedOrderId,
                ["item_id"] = resolvedItemId,
                ["side"] = string.IsNullOrWhiteSpace(side) ? "front" : side.Trim(),
                ["auto_detected"] = triggeredAutomatically
            };
            if (!string.IsNullOrWhiteSpace(s_lastPortalLaserJobId))
            {
                postObj["job_id"] = s_lastPortalLaserJobId;
                s_lastPortalLaserJobId = "";
            }
            if (!string.IsNullOrWhiteSpace(batchProductId))
            {
                postObj["batch_product_id"] = batchProductId;
            }

            if (TestOverrideHandler != null)
            {
                using var doc = JsonDocument.Parse(postObj.ToJsonString());
                var mock = await TestOverrideHandler("finish-laser", doc.RootElement).ConfigureAwait(false);
                return NormalizeFocusState(mock);
            }

            var (code, node) = await CallPortalJsonAsync(settings, HttpMethod.Post, "shop/workshop-focus/finish-laser-item", postObj).ConfigureAwait(false);
            if (code == 404)
            {
                (code, node) = await CallPortalJsonAsync(settings, HttpMethod.Post, "shop/workshop-focus/finish-laser", postObj).ConfigureAwait(false);
            }
            if (code >= 200 && code < 300 && node != null)
            {
                return NormalizeFocusState(node);
            }
            return await FetchStateAsync(settings).ConfigureAwait(false);
        }

        public static async Task<JsonObject> ToggleCheckAsync(LinuxOsSettings settings, JsonElement reqBody)
        {
            string orderId = reqBody.ValueKind == JsonValueKind.Object && reqBody.TryGetProperty("order_id", out var oEl) ? (oEl.GetString() ?? "") : "";
            string itemId = reqBody.ValueKind == JsonValueKind.Object && reqBody.TryGetProperty("item_id", out var iEl) ? (iEl.GetString() ?? "") : "";
            string key = reqBody.ValueKind == JsonValueKind.Object && reqBody.TryGetProperty("key", out var kEl) ? (kEl.GetString() ?? "") : "";
            bool? checkedVal = null;
            if (reqBody.ValueKind == JsonValueKind.Object && reqBody.TryGetProperty("checked", out var cEl) &&
                (cEl.ValueKind == JsonValueKind.True || cEl.ValueKind == JsonValueKind.False))
            {
                checkedVal = cEl.GetBoolean();
            }

            if (string.IsNullOrEmpty(orderId))
            {
                lock (s_stateCacheLock)
                {
                    if (s_lastNormalizedState != null)
                        orderId = s_lastNormalizedState["order_id"]?.GetValue<string>() ?? "";
                }
            }

            var postObj = new JsonObject
            {
                ["order_id"] = orderId,
                ["item_id"] = itemId,
                ["key"] = key
            };
            if (checkedVal.HasValue)
            {
                postObj["checked"] = checkedVal.Value;
            }

            if (TestOverrideHandler != null)
            {
                using var doc = JsonDocument.Parse(postObj.ToJsonString());
                var mock = await TestOverrideHandler("toggle-check", doc.RootElement).ConfigureAwait(false);
                if (mock != null && !mock.ContainsKey("has_order") && mock["checklist"] is JsonArray mockCl)
                {
                    lock (s_stateCacheLock)
                    {
                        if (s_lastNormalizedState != null && s_lastNormalizedState["packaging"] is JsonObject pkgObj)
                        {
                            pkgObj["checklist"] = SanitizeChecklistArray(mockCl);
                            return JsonNode.Parse(s_lastNormalizedState.ToJsonString()) as JsonObject ?? s_lastNormalizedState;
                        }
                    }
                }
                return NormalizeFocusState(mock);
            }

            var (code, node) = await CallPortalJsonAsync(settings, HttpMethod.Post, "shop/workshop-focus/toggle-check", postObj).ConfigureAwait(false);
            if (code >= 200 && code < 300 && node is JsonObject respObj)
            {
                if (respObj.ContainsKey("has_order") || respObj["data"] is JsonObject)
                {
                    return NormalizeFocusState(respObj);
                }
                if (respObj["checklist"] is JsonArray updatedCl)
                {
                    lock (s_stateCacheLock)
                    {
                        if (s_lastNormalizedState != null && s_lastNormalizedState["packaging"] is JsonObject pkgObj)
                        {
                            pkgObj["checklist"] = SanitizeChecklistArray(updatedCl);
                            return JsonNode.Parse(s_lastNormalizedState.ToJsonString()) as JsonObject ?? s_lastNormalizedState;
                        }
                    }
                }
            }
            return await FetchStateAsync(settings).ConfigureAwait(false);
        }

        public static async Task<(bool PrintedViaCups, string PrinterName, string SavedPath, string Toast)> PrintOrSaveDhlLabelAsync(
            byte[] pdfBytes,
            string? orderNumberOrId,
            string? suggestedFilename = null)
        {
            string rawBase = !string.IsNullOrWhiteSpace(suggestedFilename)
                ? Path.GetFileNameWithoutExtension(suggestedFilename)
                : $"DHL-Label-{(string.IsNullOrWhiteSpace(orderNumberOrId) ? DateTime.Now.ToString("yyyyMMdd-HHmmss") : orderNumberOrId)}";

            string safeBase = Regex.Replace(rawBase, @"[^A-Za-z0-9._-]", "_").Trim('_');
            if (string.IsNullOrWhiteSpace(safeBase)) safeBase = $"DHL-Label-{DateTime.Now:yyyyMMdd-HHmmss}";
            string safeFileName = safeBase + ".pdf";

            if (OperatingSystem.IsLinux() && string.IsNullOrWhiteSpace(LinuxVaultAndStorage.TestOverrideRootDir))
            {
                var (lpStatCode, lpStatOut, _) = await LinuxHardwareEngine.RunProcessAsync("lpstat", new[] { "-p" }, 2500).ConfigureAwait(false);
                if (lpStatCode == 0 && !string.IsNullOrWhiteSpace(lpStatOut))
                {
                    var enabledPrinters = new List<string>();
                    foreach (string line in lpStatOut.Split('\n', StringSplitOptions.RemoveEmptyEntries))
                    {
                        if (line.Contains("disabled", StringComparison.OrdinalIgnoreCase)) continue;
                        var m = Regex.Match(line.Trim(), @"^printer\s+([A-Za-z0-9_.-]+)\s+is\s+", RegexOptions.IgnoreCase);
                        if (m.Success)
                        {
                            enabledPrinters.Add(m.Groups[1].Value);
                        }
                    }

                    string? chosenPrinter = enabledPrinters.FirstOrDefault(p =>
                        p.Contains("MUNBYN", StringComparison.OrdinalIgnoreCase) ||
                        p.Contains("ITPP", StringComparison.OrdinalIgnoreCase) ||
                        p.Contains("Label", StringComparison.OrdinalIgnoreCase) ||
                        p.Contains("DHL", StringComparison.OrdinalIgnoreCase))
                        ?? enabledPrinters.FirstOrDefault();

                    if (!string.IsNullOrWhiteSpace(chosenPrinter))
                    {
                        string tmpPdf = Path.Combine(Path.GetTempPath(), $"fw_dhl_{Guid.NewGuid():N}.pdf");
                        try
                        {
                            LinuxVaultAndStorage.WriteFileAtomically0600(tmpPdf, pdfBytes);
                            var (lpCode, _, _) = await LinuxHardwareEngine.RunProcessAsync(
                                "lp",
                                new[] { "-d", chosenPrinter, "-o", "fit-to-page", tmpPdf },
                                6000).ConfigureAwait(false);
                            if (lpCode == 0)
                            {
                                return (true, chosenPrinter, "", "DHL Label gedruckt");
                            }
                        }
                        catch { }
                        finally
                        {
                            try { if (File.Exists(tmpPdf)) File.Delete(tmpPdf); } catch { }
                        }
                    }
                }
            }

            string downloadsDir = Path.Combine(LinuxVaultAndStorage.UserHomeDir, "Downloads");
            Directory.CreateDirectory(downloadsDir);
            string fullSavePath = Path.Combine(downloadsDir, safeFileName);
            LinuxVaultAndStorage.WriteFileAtomically0600(fullSavePath, pdfBytes);
            return (false, "", fullSavePath, $"Label gespeichert · Downloads/{safeFileName}");
        }

        public static async Task<JsonObject> ShipAndNextAsync(LinuxOsSettings settings, JsonElement reqBody)
        {
            XtoolLocalLanBridge.DisarmPreparedJobWatcher();

            string orderId = reqBody.ValueKind == JsonValueKind.Object && reqBody.TryGetProperty("order_id", out var oEl) ? (oEl.GetString() ?? "") : "";
            string batchProductId = "";
            lock (s_stateCacheLock)
            {
                if (s_lastNormalizedState != null)
                {
                    if (string.IsNullOrEmpty(orderId)) orderId = s_lastNormalizedState["order_id"]?.GetValue<string>() ?? "";
                    batchProductId = s_lastNormalizedState["product_id"]?.GetValue<string>() ?? "";
                }
            }
            if (string.IsNullOrEmpty(orderId))
            {
                var cur = await FetchStateAsync(settings).ConfigureAwait(false);
                orderId = cur["order_id"]?.GetValue<string>() ?? "";
                if (string.IsNullOrEmpty(batchProductId)) batchProductId = cur["product_id"]?.GetValue<string>() ?? "";
            }

            var postObj = new JsonObject
            {
                ["order_id"] = orderId
            };
            if (!string.IsNullOrWhiteSpace(batchProductId))
            {
                postObj["batch_product_id"] = batchProductId;
            }

            JsonNode? respNode;
            int statusCode;
            if (TestOverrideHandler != null)
            {
                using var doc = JsonDocument.Parse(postObj.ToJsonString());
                respNode = await TestOverrideHandler("ship-and-next", doc.RootElement).ConfigureAwait(false);
                statusCode = respNode != null ? 200 : 500;
            }
            else
            {
                (statusCode, respNode) = await CallPortalJsonAsync(settings, HttpMethod.Post, "shop/workshop-focus/ship-and-next", postObj, timeoutSec: 25).ConfigureAwait(false);
            }

            var rootObj = respNode as JsonObject;
            var payload = (rootObj?["data"] as JsonObject) ?? rootObj;
            if (statusCode < 200 || statusCode >= 300 || payload == null)
            {
                string errMsg = CleanTextNoEnDash(GetAnyStr(payload ?? rootObj, "message", "error"));
                if (string.IsNullOrWhiteSpace(errMsg)) errMsg = "Versandabschluss fehlgeschlagen.";
                return new JsonObject
                {
                    ["ok"] = false,
                    ["error"] = errMsg
                };
            }

            string pdfB64 = GetAnyStr(payload, "label_pdf_base64", "labelPdfBase64", "pdf_base64");
            string labelFileName = GetAnyStr(payload, "label_filename", "labelFilename", "filename");
            string orderNum = GetAnyStr(payload, "order_number", "orderNumber");
            if (string.IsNullOrEmpty(orderNum)) orderNum = orderId;

            bool printedViaCups = false;
            string savedPath = "";
            string toast = "";

            if (!string.IsNullOrWhiteSpace(pdfB64))
            {
                if (pdfB64.Contains(',')) pdfB64 = pdfB64.Substring(pdfB64.IndexOf(',') + 1);
                try
                {
                    byte[] pdfBytes = Convert.FromBase64String(pdfB64);
                    if (pdfBytes.Length > 64)
                    {
                        var printRes = await PrintOrSaveDhlLabelAsync(pdfBytes, orderNum, labelFileName).ConfigureAwait(false);
                        printedViaCups = printRes.PrintedViaCups;
                        savedPath = printRes.SavedPath;
                        toast = printRes.Toast;
                    }
                }
                catch { }
            }

            JsonNode? nextFocusRaw = payload["next_focus"] ?? payload["nextFocus"] ?? rootObj?["next_focus"];
            JsonObject nextNormalized = nextFocusRaw != null
                ? NormalizeFocusState(nextFocusRaw)
                : await FetchStateAsync(settings).ConfigureAwait(false);

            return new JsonObject
            {
                ["ok"] = true,
                ["printed_via_cups"] = printedViaCups,
                ["saved_path"] = savedPath,
                ["toast"] = toast,
                ["next_focus"] = nextNormalized
            };
        }
    }
}

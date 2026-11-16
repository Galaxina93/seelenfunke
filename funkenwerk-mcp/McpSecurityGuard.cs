using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Renci.SshNet;

namespace FunkenwerkMcp
{
    public sealed class SanitizedServerProfileDto
    {
        public string SourceApp { get; set; } = "";
        public string Name { get; set; } = "";
        public string Protocol { get; set; } = "";
        public string Host { get; set; } = "";
        public int Port { get; set; } = 22;
        public string DefaultRemoteDirectory { get; set; } = "/";
        public bool UsesPrivateKey { get; set; }
        public bool HasSavedPasswordInVault { get; set; }
        public bool IsHostKeyPinnedSha256 { get; set; }
    }

    public sealed class SecurityAuditStatusDto
    {
        public bool DirectoryAclRestrictedToCurrentUser { get; set; }
        public bool AuditFileAclRestrictedToCurrentUser { get; set; }
        public bool AuditHashChainValid { get; set; }
        public int AuditEntriesCount { get; set; }
        public int PinnedSshHostsCount { get; set; }
        public List<string> AllowedFilesystemRoots { get; set; } = new();
        public List<string> AllowedApiHosts { get; set; } = new();
        public List<string> ActiveHardeningLayers { get; set; } = new();
    }

    public sealed class StageShopAssetSyncResultDto
    {
        public bool Success { get; set; }
        public bool DryRun { get; set; }
        public string TargetHost { get; set; } = "";
        public string TargetRemoteStorageDir { get; set; } = "";
        public int TotalManifestFiles { get; set; }
        public int UnchangedOnStageCount { get; set; }
        public int UploadedCount { get; set; }
        public long UploadedBytes { get; set; }
        public List<string> UploadedFiles { get; set; } = new();
    }

    public sealed class StageAndroidApkPublishResultDto
    {
        public bool Success { get; set; }
        public string TargetHost { get; set; } = "";
        public string TargetRemoteVaultDir { get; set; } = "";
        public int VersionCode { get; set; }
        public string VersionName { get; set; } = "";
        public string ApkSha256 { get; set; } = "";
        public string CertSha256 { get; set; } = "";
        public long FileSizeBytes { get; set; }
        public string RemoteManifestJson { get; set; } = "";
    }

    public sealed class AgDirectBridgeConfigDto
    {
        [JsonPropertyName("master_conversation_id")]
        public string MasterConversationId { get; set; } = McpSecurityGuard.DefaultMasterConversationId;

        [JsonPropertyName("poll_interval_seconds")]
        public int PollIntervalSeconds { get; set; } = McpSecurityGuard.DefaultAgPollIntervalSeconds;

        [JsonPropertyName("channel_conversation_ids")]
        public Dictionary<string, string> ChannelConversationIds { get; set; } = new(StringComparer.OrdinalIgnoreCase);
    }

    public static class McpSecurityGuard
    {
        public const long MaxPdfFileBytes = 50L * 1024L * 1024L; // 50 MB hard cap
        public const int MaxJsonRpcMessageBytes = 512 * 1024;    // 512 KB stdin cap
        public const int MaxStringArgumentChars = 4096;          // 4 KB per string arg cap

        private static readonly object AuditLock = new();
        private static readonly object RateLimitLock = new();
        private static readonly Queue<DateTime> GeneralCallWindow = new();
        private static readonly Queue<DateTime> SshCallWindow = new();

        private static readonly string[] AllowedApiHosts =
        {
            "127.0.0.1",
            "localhost",
            "seelenfunke.test",
            "stage.mein-seelenfunke.de",
            "mein-seelenfunke.de"
        };

        private static readonly string[] BlockedPathFragments =
        {
            @"\.ssh\",
            @"/.ssh/",
            @"\.gemini\",
            @"/.gemini/",
            @"\.aws\",
            @"\.gnupg\",
            @"\appdata\",
            @"\.git\",
            @"/.git/",
            @"\funkenwerk collection\",
            @"/funkenwerk collection/",
            @"\funkenwerkmcp\",
            @"\funkenwerkftp\",
            @"\funkenwerkssh\",
            @"\funkenwerktresor\"
        };

        private static readonly string[] BlockedFileNames =
        {
            ".env",
            ".env.local",
            ".env.production",
            ".env.staging",
            ".env.testing",
            "settings.json",
            "mcp_config.json",
            "profiles_vault.json",
            "ssh_profiles_vault.json",
            "ai_broker_vault.bin",
            "devtoolsactiveport",
            "known_hosts",
            "known_hosts_sha256.json",
            "master.dpapi",
            "handoff_ticket.dpapi",
            "security_audit.jsonl",
            "id_ed25519",
            "id_rsa",
            "id_ecdsa",
            "ntuser.dat",
            "auth.json",
            "composer.lock"
        };

        private static readonly HashSet<string> ReservedDosDeviceNames = new(StringComparer.OrdinalIgnoreCase)
        {
            "CON", "PRN", "AUX", "NUL",
            "COM1", "COM2", "COM3", "COM4", "COM5", "COM6", "COM7", "COM8", "COM9",
            "LPT1", "LPT2", "LPT3", "LPT4", "LPT5", "LPT6", "LPT7", "LPT8", "LPT9"
        };

        private static readonly string[] BlockedExtensions =
        {
            ".dpapi", ".pem", ".key", ".pfx", ".p12", ".ppk", ".kdbx", ".cer", ".crt", ".der",
            ".exe", ".dll", ".bat", ".cmd", ".ps1", ".vbs", ".js", ".msi", ".scr", ".com", ".reg", ".lnk", ".hta"
        };

        // Outbound DLP Regexes - prevents ANY accidental credential/token/key leakage to stdout
        private static readonly Regex[] OutboundSecretPatterns =
        {
            new(@"\b\d+\|[A-Za-z0-9]{32,}\b", RegexOptions.Compiled),                                   // Laravel Sanctum tokens
            new(@"-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z0-9 ]*PRIVATE KEY-----", RegexOptions.Compiled),
            new(@"PuTTY-User-Key-File-\d+:[\s\S]{16,}", RegexOptions.Compiled),                         // PuTTY .ppk material
            new(@"FWVAULT2:[A-Za-z0-9+/=]{16,}", RegexOptions.Compiled),                                // Funkenwerk Vault v2
            new(@"FWAES256GCM1:[A-Za-z0-9+/=]{16,}", RegexOptions.Compiled),                            // Funkenwerk AES-256-GCM Vault
            new(@"FWSSHAES256GCM1:[A-Za-z0-9+/=]{16,}", RegexOptions.Compiled),                         // Funkenwerk SSH Vault
            new(@"DPAPI:[A-Za-z0-9+/=]{16,}", RegexOptions.Compiled),                                   // Legacy DPAPI
            new(@"(?i)(APP_KEY|DB_PASSWORD|MAIL_PASSWORD|STRIPE_SECRET|AWS_SECRET_ACCESS_KEY|GEMINI_API_KEY|API_TOKEN)\s*[=:]\s*\S+", RegexOptions.Compiled),
            new(@"(?i)""(?:password|passwd|pwd|tapo_password|secret|api_token)""\s*:\s*""[^""\r\n]+""", RegexOptions.Compiled), // JSON password/secret fields
            new(@"\bAIza[0-9A-Za-z\-_]{30,}\b", RegexOptions.Compiled),                                 // Google API keys
            new(@"\bsk-[A-Za-z0-9\-_]{24,}\b", RegexOptions.Compiled),                                  // OpenAI secret keys
            new(@"\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9_]{12,}\b", RegexOptions.Compiled),               // Stripe Secret & Restricted keys
            new(@"\bwhsec_[A-Za-z0-9_]{12,}\b", RegexOptions.Compiled),                                 // Stripe Webhook signing secrets
            new(@"\b(?:pi|seti)_[A-Za-z0-9]+_secret_[A-Za-z0-9]+\b", RegexOptions.Compiled),            // Stripe PaymentIntent/SetupIntent client_secret
            new(@"\b(?:ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,})\b", RegexOptions.Compiled),   // GitHub PATs
            new(@"\beyJ[A-Za-z0-9_\-]{10,}\.eyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b", RegexOptions.Compiled), // JWTs
            new(@"(?i)https?://[^/\s:@]+:[^/\s@]+@[^\s/]+", RegexOptions.Compiled),                     // Basic-Auth credentials in HTTP URLs
            new(@"(?i)rtsps?://[^/\s:@""']+:[^/\s@""']+@[^\s""']+", RegexOptions.Compiled),             // RTSP camera credentials in URLs
            new(@"(?i)Bearer\s+[A-Za-z0-9\-._~+/|=]{20,}", RegexOptions.Compiled),                      // Raw Bearer headers
            new(@"(?i)otpauth://totp/[^\s""']+", RegexOptions.Compiled),                                // Raw TOTP otpauth:// URIs
            new(@"(?i)""(?:totp_seed|totpSeed|totpSecret|TotpSeedOrConfig|ApiToken|x-access-token)""\s*:\s*""[^""\r\n]+""", RegexOptions.Compiled), // Broker/TOTP/Token JSON fields
            new(@"(?i)(?:x-access-token|MITTWALD_API_TOKEN)\s*[=:]\s*\S+", RegexOptions.Compiled),      // Raw Mittwald token headers/vars
            new(@"\bmw_pat_[A-Za-z0-9\-._~+/=]{12,}\b", RegexOptions.Compiled)                          // Mittwald PAT tokens
        };

        public const string StageAllowedHost = "ssh.altgemeinde.project.host";
        public const string StageWebRemoteDir = "/home/p-g27wim/html/seelenfunke-stage";
        public const string StageWorkerRemoteDir = "/home/p-g27wim/html/worker-stage-3";
        public const string StageTwilioBridgeRemoteDir = "/home/p-g27wim/html/twilio-bridge";
        public const string StageTwilioBridgeSshUser = "kontakt@mein-seelenfunke.de@a-yeq7el";
        public const string MiniPcAllowedHost = "192.168.188.151";
        public const string MiniPcSentinelDataDir = "/home/alina/.local/share/FunkenwerkOS";
        public const string DefaultLocalStorageAppRoot = @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\storage\app";

        public static readonly IReadOnlyList<string> SafeReadonlySshPresets = new[]
        {
            "disk_usage",
            "uptime_and_load",
            "php_version",
            "artisan_about",
            "git_status",
            "git_log",
            "git_branch",
            "list_dir",
            "stage_git_pull",
            "stage_deploy",
            "stage_worker_restart",
            "stage_sentinel_export",
            "stage_sentinel_resolve_all",
            "minipc_sentinel_export",
            "minipc_sentinel_resolve_all"
        };

        private static readonly string[] AllowedShopAssetPrefixes =
        {
            "public/produkte/",
            "produkte/products-secure/",
            "public/marketing/blog/",
            "public/shopverwaltung/"
        };

        private static readonly string[] BlockedShopRootPrefixes =
        {
            "leitung/",
            "public/leitung/",
            "buchhaltung/",
            "public/buchhaltung/",
            "agenten/",
            "public/agenten/",
            "support/",
            "public/support/",
            "bestellungen/",
            "public/bestellungen/",
            "private/",
            "system/",
            "public/system/",
            "temp/",
            "print_queue/",
            "dhl_labels/"
        };

        private static readonly HashSet<string> BlockedShopSegments = new(StringComparer.OrdinalIgnoreCase)
        {
            "music",
            "buchhaltung",
            "wiki",
            "health",
            "gesundheit",
            "kameraaufnahmen",
            "person_profiles",
            "backup",
            "backups",
            "reviews",
            "documents",
            "echtgold-seelen-herz",
            ".git"
        };

        private static readonly HashSet<string> AllowedShopExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".png", ".jpg", ".jpeg", ".webp", ".svg", ".gif",
            ".glb", ".gltf", ".mp4", ".webm", ".pdf", ".zip", ".json"
        };

        private static readonly Regex SafeShopRelPathRegex = new(@"^[A-Za-z0-9_./\-]+$", RegexOptions.Compiled);
        private static readonly Regex SafeSecureZipRegex = new(@"^produkte/products-secure/[A-Za-z0-9_\-]+\.zip$", RegexOptions.Compiled);
        private static readonly Regex SafeHostRegex = new(@"^[A-Za-z0-9.\-]+$", RegexOptions.Compiled);
        private static readonly Regex SafeSshUserRegex = new(@"^[A-Za-z0-9._@\-]+$", RegexOptions.Compiled);
        private static readonly Regex SafeRemoteDirRegex = new(@"^[A-Za-z0-9/_.\-~]+$", RegexOptions.Compiled);
        private static readonly Regex AnsiEscapeRegex = new(@"\x1B(?:\[[0-9;?]*[A-Za-z]|\][^\x07\x1B]*(?:\x07|\x1B\\)|[()][AB012]|[>=<c])", RegexOptions.Compiled);

        // DPAPI / Vault interop matching Funkenwerk Suite
        private static readonly byte[] FunkenwerkMainEntropy = Encoding.UTF8.GetBytes("Funkenwerk_Seelenfunke_HardwareVault_v1");
        private static readonly byte[] FunkenwerkMainAad = Encoding.UTF8.GetBytes("Funkenwerk-Vault-v2-AEAD-AES256GCM");
        private static readonly byte[] FtpVaultEntropy = Encoding.UTF8.GetBytes("Funkenwerk.Ftp.SecurityVault.v1!MeinSeelenfunke");
        private static readonly byte[] SshVaultEntropy = Encoding.UTF8.GetBytes("Funkenwerk.Ssh.SecurityVault.v1!MeinSeelenfunke");
        private static readonly byte[] HandoffEntropy = Encoding.UTF8.GetBytes("Funkenwerk.FtpToSsh.Handoff.v1!MeinSeelenfunke");

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct DATA_BLOB
        {
            public int cbData;
            public IntPtr pbData;
        }

        [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Auto)]
        private static extern bool CryptUnprotectData(
            ref DATA_BLOB pCipherText,
            IntPtr ppszDescription,
            ref DATA_BLOB pEntropy,
            IntPtr pReserved,
            IntPtr pPrompt,
            int dwFlags,
            out DATA_BLOB pPlainText);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr LocalFree(IntPtr hMem);

        private static string? _vaultDirOverride;
        private static string? _testAllowedRootOverride;

        public static string VaultDirectory => McpDataDir;

        public static string McpDataDir
        {
            get
            {
                string dir = !string.IsNullOrWhiteSpace(_vaultDirOverride)
                    ? _vaultDirOverride!
                    : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkMcp");
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        public static IDisposable UseTemporaryVaultDirectory(string tempVaultDir, string? tempAllowedDocsDir = null)
        {
            string? prevVault = _vaultDirOverride;
            string? prevDocs = _testAllowedRootOverride;
            Directory.CreateDirectory(tempVaultDir);
            LockDownDirectoryAcl(tempVaultDir);
            if (!string.IsNullOrWhiteSpace(tempAllowedDocsDir))
            {
                Directory.CreateDirectory(tempAllowedDocsDir);
            }
            _vaultDirOverride = tempVaultDir;
            _testAllowedRootOverride = tempAllowedDocsDir;
            ResetRateLimiterForTests();
            return new TempVaultScope(prevVault, prevDocs);
        }

        private sealed class TempVaultScope : IDisposable
        {
            private readonly string? _prevVault;
            private readonly string? _prevDocs;
            public TempVaultScope(string? prevVault, string? prevDocs)
            {
                _prevVault = prevVault;
                _prevDocs = prevDocs;
            }
            public void Dispose()
            {
                _vaultDirOverride = _prevVault;
                _testAllowedRootOverride = _prevDocs;
                ResetRateLimiterForTests();
            }
        }

        internal static void ResetRateLimiterForTests()
        {
            lock (RateLimitLock)
            {
                GeneralCallWindow.Clear();
                SshCallWindow.Clear();
            }
        }

        private static string AuditLogFile => Path.Combine(McpDataDir, "security_audit.jsonl");

        public static string CollectionRootDir
        {
            get
            {
                string desktopCol = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "Funkenwerk Collection");
                if (Directory.Exists(desktopCol)) return desktopCol;
                if (Directory.Exists(@"C:\Funkenwerk Collection")) return @"C:\Funkenwerk Collection";
                return desktopCol;
            }
        }

        public static List<string> GetAllowedFilesystemRoots()
        {
            string userProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            var roots = new List<string>
            {
                Path.Combine(userProfile, "Downloads"),
                Path.Combine(userProfile, "Documents"),
                Path.Combine(userProfile, "Desktop"),
                Path.Combine(userProfile, "Pictures")
            };
            if (!string.IsNullOrWhiteSpace(_testAllowedRootOverride))
            {
                roots.Add(_testAllowedRootOverride!);
            }
            try
            {
                if (Directory.Exists(@"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke"))
                {
                    roots.Add(@"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\storage\app\buchhaltung");
                    roots.Add(@"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\Abschlussberichte");
                }
            }
            catch { }
            return roots.Select(NormalizeRootPrefix).ToList();
        }

        private static string NormalizeRootPrefix(string p)
        {
            string full = Path.GetFullPath(p).TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
            return full + Path.DirectorySeparatorChar;
        }

        public static bool LockDownDirectoryAcl(string directoryPath)
        {
            try
            {
                var dirInfo = new DirectoryInfo(directoryPath);
                if (!dirInfo.Exists) return false;

                var currentUser = WindowsIdentity.GetCurrent().User;
                if (currentUser == null) return false;

                var sec = new DirectorySecurity();
                sec.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);

                sec.AddAccessRule(new FileSystemAccessRule(
                    currentUser,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));

                var systemSid = new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null);
                sec.AddAccessRule(new FileSystemAccessRule(
                    systemSid,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));

                var adminsSid = new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null);
                sec.AddAccessRule(new FileSystemAccessRule(
                    adminsSid,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));

                dirInfo.SetAccessControl(sec);
                return true;
            }
            catch
            {
                return false;
            }
        }

        public static bool LockDownFileAcl(string filePath)
        {
            try
            {
                var fi = new FileInfo(filePath);
                if (!fi.Exists) return false;

                var currentUser = WindowsIdentity.GetCurrent().User;
                if (currentUser == null) return false;

                var sec = new FileSecurity();
                sec.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                sec.AddAccessRule(new FileSystemAccessRule(currentUser, FileSystemRights.FullControl, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null), FileSystemRights.FullControl, AccessControlType.Allow));

                fi.SetAccessControl(sec);
                return true;
            }
            catch
            {
                return false;
            }
        }

        // =====================================================================
        // LAYER 7: SLIDING-WINDOW RATE LIMITER
        // =====================================================================
        public static void EnforceRateLimit(string toolName, bool isSshCall = false)
        {
            lock (RateLimitLock)
            {
                DateTime now = DateTime.UtcNow;
                while (GeneralCallWindow.Count > 0 && (now - GeneralCallWindow.Peek()).TotalSeconds > 60)
                    GeneralCallWindow.Dequeue();

                if (GeneralCallWindow.Count >= 60)
                {
                    AppendAuditLog(toolName, "rate_limit", "DENY:general_60_per_min");
                    throw new InvalidOperationException("Sicherheits-Rate-Limit erreicht (max. 60 MCP-Aufrufe pro Minute).");
                }
                GeneralCallWindow.Enqueue(now);

                if (isSshCall)
                {
                    while (SshCallWindow.Count > 0 && (now - SshCallWindow.Peek()).TotalSeconds > 60)
                        SshCallWindow.Dequeue();

                    if (SshCallWindow.Count >= 10)
                    {
                        AppendAuditLog(toolName, "ssh_rate_limit", "DENY:ssh_10_per_min");
                        throw new InvalidOperationException("SSH-Sicherheits-Rate-Limit erreicht (max. 10 SSH-Aufrufe pro Minute).");
                    }
                    SshCallWindow.Enqueue(now);
                }
            }
        }

        // =====================================================================
        // LAYER 2: STRICT FILESYSTEM SANDBOX + SYMLINK/ADS/DEVICE/MAGIC GUARD
        // =====================================================================
        public static string ValidateSafeFilePath(
            string? inputPath,
            string[] allowedExtensions,
            bool mustExist,
            long maxBytes = MaxPdfFileBytes)
        {
            if (string.IsNullOrWhiteSpace(inputPath))
                throw new ArgumentException("Dateipfad darf nicht leer sein.");

            if (inputPath.Length > 1024)
                throw new UnauthorizedAccessException("Dateipfad überschreitet die maximale Sicherheitslänge (1024 Zeichen).");

            string trimmed = inputPath.Trim().Trim('"');
            if (trimmed.IndexOf('\0') >= 0 || trimmed.Any(c => char.IsControl(c)))
                throw new UnauthorizedAccessException("Ungültige Steuerzeichen im Dateipfad blockiert.");

            // Block Win32 device & verbatim namespaces (\\.\pipe\..., \\?\...)
            if (trimmed.StartsWith(@"\\.\", StringComparison.Ordinal) ||
                trimmed.StartsWith(@"\\?\", StringComparison.Ordinal) ||
                trimmed.StartsWith(@"//./", StringComparison.Ordinal) ||
                trimmed.StartsWith(@"//?/", StringComparison.Ordinal))
            {
                throw new UnauthorizedAccessException("Win32-Device-/Pipe-Pfade sind aus Sicherheitsgründen blockiert.");
            }

            // Block NTFS Alternate Data Streams (e.g. file.pdf:secret)
            int firstColon = trimmed.IndexOf(':');
            if (firstColon >= 0)
            {
                bool validDrivePrefix = (firstColon == 1 && char.IsLetter(trimmed[0]) && trimmed.IndexOf(':', 2) < 0);
                if (!validDrivePrefix)
                    throw new UnauthorizedAccessException("NTFS Alternate Data Streams (ADS) sind gesperrt.");
            }

            // Reject raw relative traversal tokens before canonicalization as defense-in-depth
            if (trimmed.Contains("..", StringComparison.Ordinal))
                throw new UnauthorizedAccessException("Pfad-Traversal ('..') ist aus Sicherheitsgründen blockiert.");

            string fullPath = Path.GetFullPath(trimmed);
            string lowerFull = fullPath.ToLowerInvariant();

            // Block DOS reserved device names in ANY path segment (e.g. CON.pdf, NUL, COM1.pdf)
            foreach (string segment in fullPath.Split(new[] { '\\', '/' }, StringSplitOptions.RemoveEmptyEntries))
            {
                string stem = segment;
                int dotIdx = stem.IndexOf('.');
                if (dotIdx >= 0) stem = stem.Substring(0, dotIdx);
                if (ReservedDosDeviceNames.Contains(stem))
                    throw new UnauthorizedAccessException($"Reservierter Windows-Gerätename ('{segment}') ist gesperrt.");
            }

            // Check sensitive path segments & sensitive filenames (including Funkenwerk Collection binaries!)
            string? activeTestDocsRoot = _testAllowedRootOverride;
            bool insideIsolatedTestDocs = !string.IsNullOrWhiteSpace(activeTestDocsRoot) &&
                fullPath.StartsWith(Path.GetFullPath(activeTestDocsRoot).TrimEnd('\\', '/') + "\\", StringComparison.OrdinalIgnoreCase);

            foreach (string frag in BlockedPathFragments)
            {
                if (insideIsolatedTestDocs && string.Equals(frag, @"\appdata\", StringComparison.Ordinal))
                    continue;
                if (lowerFull.Contains(frag, StringComparison.Ordinal))
                    throw new UnauthorizedAccessException("Zugriff auf geschütztes Programm-, System- oder Schlüsselverzeichnis verweigert.");
            }

            string fileNameLower = Path.GetFileName(fullPath).ToLowerInvariant();
            if (string.IsNullOrEmpty(fileNameLower) || BlockedFileNames.Contains(fileNameLower) || fileNameLower.StartsWith(".env."))
                throw new UnauthorizedAccessException($"Zugriff auf geschützte Konfigurations-/Tresordatei ('{fileNameLower}') verweigert.");

            string extLower = Path.GetExtension(fullPath).ToLowerInvariant();
            if (BlockedExtensions.Contains(extLower))
                throw new UnauthorizedAccessException($"Dateierweiterung '{extLower}' ist im MCP-Sicherheitskäfig gesperrt.");

            if (allowedExtensions != null && allowedExtensions.Length > 0)
            {
                bool extOk = allowedExtensions.Any(e => string.Equals(e, extLower, StringComparison.OrdinalIgnoreCase));
                if (!extOk)
                    throw new UnauthorizedAccessException($"Nur folgende Dateitypen sind für dieses Tool freigegeben: {string.Join(", ", allowedExtensions)}");
            }

            // Enforce whitelist roots
            var allowedRoots = GetAllowedFilesystemRoots();
            bool insideAllowedRoot = allowedRoots.Any(root =>
                fullPath.StartsWith(root, StringComparison.OrdinalIgnoreCase));

            if (!insideAllowedRoot)
                throw new UnauthorizedAccessException("Dateipfad liegt außerhalb der freigegebenen Arbeitsordner (Downloads, Documents, Desktop, Pictures, Buchhaltung, Abschlussberichte).");

            // Defeat Symlink / Directory Junction sandbox-escape attacks
            RejectReparsePointsInHierarchy(fullPath);

            if (mustExist)
            {
                if (!File.Exists(fullPath))
                    throw new FileNotFoundException($"Datei nicht gefunden: {fullPath}");

                var fi = new FileInfo(fullPath);
                if ((fi.Attributes & FileAttributes.ReparsePoint) != 0)
                    throw new UnauthorizedAccessException("Symbolische Links / Reparse-Points sind aus Sicherheitsgründen blockiert.");
                if (fi.Length > maxBytes)
                    throw new InvalidOperationException($"Datei überschreitet das Sicherheitslimit von {maxBytes / (1024 * 1024)} MB.");

                if (string.Equals(extLower, ".pdf", StringComparison.OrdinalIgnoreCase))
                {
                    VerifyPdfMagicHeader(fullPath);
                }
            }

            return fullPath;
        }

        /// <summary>
        /// Verifies that an existing file genuinely begins with the ISO-32000 '%PDF-' magic header
        /// at byte offset 0 (or offset 3 immediately following a UTF-8 BOM), rejecting polyglot files.
        /// </summary>
        public static void VerifyPdfMagicHeader(string fullPath)
        {
            byte[] header = new byte[8];
            int read;
            using (var fs = new FileStream(fullPath, FileMode.Open, FileAccess.Read, FileShare.Read))
            {
                read = fs.Read(header, 0, header.Length);
            }

            if (read < 5)
                throw new UnauthorizedAccessException("Ungültige oder leere PDF-Datei (Magic-Header fehlt).");

            bool atZero = header[0] == (byte)'%' &&
                          header[1] == (byte)'P' &&
                          header[2] == (byte)'D' &&
                          header[3] == (byte)'F' &&
                          header[4] == (byte)'-';

            bool afterUtf8Bom = read >= 8 &&
                                header[0] == 0xEF &&
                                header[1] == 0xBB &&
                                header[2] == 0xBF &&
                                header[3] == (byte)'%' &&
                                header[4] == (byte)'P' &&
                                header[5] == (byte)'D' &&
                                header[6] == (byte)'F' &&
                                header[7] == (byte)'-';

            if (!atZero && !afterUtf8Bom)
                throw new UnauthorizedAccessException("Datei besitzt keinen gültigen '%PDF-' Magic-Header an Byte-Offset 0 (Tarnungs-/Polyglot-Schutz hat ausgelöst).");
        }

        /// <summary>
        /// TOCTOU-resistant atomic file write: writes to a cryptographic random sibling temporary file
        /// with FileMode.CreateNew, re-verifies no symlink appeared at destination, then moves atomically.
        /// </summary>
        public static async Task WriteSafeFileAtomicAsync(string rawTargetPath, string[] allowedExtensions, byte[] content, bool allowOverwrite)
        {
            string safeOut = ValidateSafeFilePath(rawTargetPath, allowedExtensions, mustExist: false);
            if (File.Exists(safeOut))
            {
                var existingFi = new FileInfo(safeOut);
                if ((existingFi.Attributes & FileAttributes.ReparsePoint) != 0)
                    throw new UnauthorizedAccessException("Überschreiben von Symlinks/Reparse-Points ist gesperrt.");

                if (!allowOverwrite)
                    throw new InvalidOperationException($"Zieldatei existiert bereits ('{safeOut}'). Nutze 'overwrite: true' oder einen anderen 'outputPath'.");
            }

            string dir = Path.GetDirectoryName(safeOut) ?? throw new InvalidOperationException("Ungültiges Zielverzeichnis.");
            if (!Directory.Exists(dir))
                throw new DirectoryNotFoundException($"Zielverzeichnis existiert nicht: {dir}");

            RejectReparsePointsInHierarchy(safeOut);

            string tempFile = Path.Combine(dir, $".fwmcp_{Convert.ToHexString(RandomNumberGenerator.GetBytes(8)).ToLowerInvariant()}.tmp");
            try
            {
                await using (var fs = new FileStream(tempFile, FileMode.CreateNew, FileAccess.Write, FileShare.None, 32768, FileOptions.WriteThrough | FileOptions.Asynchronous))
                {
                    await fs.WriteAsync(content, 0, content.Length);
                    await fs.FlushAsync();
                }

                // Second anti-TOCTOU check immediately prior to atomic rename
                RejectReparsePointsInHierarchy(safeOut);
                File.Move(tempFile, safeOut, overwrite: allowOverwrite);
            }
            finally
            {
                try
                {
                    if (File.Exists(tempFile))
                        File.Delete(tempFile);
                }
                catch { }
            }
        }

        public static bool HasReparsePointInAncestry(string fullPath)
        {
            if (string.IsNullOrWhiteSpace(fullPath)) return false;

            bool isWslUnc = fullPath.StartsWith(@"\\wsl.localhost\Ubuntu\", StringComparison.OrdinalIgnoreCase)
                         || fullPath.StartsWith(@"\\wsl$\Ubuntu\", StringComparison.OrdinalIgnoreCase);

            string? cur = fullPath;
            while (!string.IsNullOrEmpty(cur))
            {
                if (isWslUnc && (string.Equals(cur.TrimEnd('\\'), @"\\wsl.localhost\Ubuntu", StringComparison.OrdinalIgnoreCase)
                              || string.Equals(cur.TrimEnd('\\'), @"\\wsl$\Ubuntu", StringComparison.OrdinalIgnoreCase)))
                {
                    break;
                }

                try
                {
                    if (File.Exists(cur))
                    {
                        var attr = File.GetAttributes(cur);
                        if ((attr & FileAttributes.ReparsePoint) != 0)
                            return true;
                    }
                    else if (Directory.Exists(cur))
                    {
                        var di = new DirectoryInfo(cur);
                        if (di.Parent != null && (di.Attributes & FileAttributes.ReparsePoint) != 0)
                            return true;
                    }
                }
                catch
                {
                    // Ignore root share metadata query limitations
                }

                cur = Path.GetDirectoryName(cur);
            }

            return false;
        }

        public static void RejectReparsePointsInHierarchy(string fullPath)
        {
            if (HasReparsePointInAncestry(fullPath))
                throw new UnauthorizedAccessException("Symlink/Junction/Reparse-Point in Pfadhierarchie wurde blockiert.");
        }

        // =====================================================================
        // LAYER 3 & 4: OUTBOUND DLP REDACTOR & PROMPT-INJECTION SANITIZER
        // =====================================================================
        public static string ScrubSecrets(string? text)
        {
            if (string.IsNullOrEmpty(text)) return "";
            string clean = text;
            foreach (var rx in OutboundSecretPatterns)
            {
                clean = rx.Replace(clean, "[REDACTED-BY-FUNKENWERK-DLP]");
            }
            return clean;
        }

        public static string SanitizeUntrustedPdfString(string? raw, int maxLen = 600)
        {
            if (string.IsNullOrEmpty(raw)) return "";
            var sb = new StringBuilder(Math.Min(raw.Length, maxLen));
            foreach (char c in raw)
            {
                if (sb.Length >= maxLen) break;
                if (c == '\n' || c == '\r' || c == '\t' || !char.IsControl(c))
                    sb.Append(c);
            }
            return ScrubSecrets(sb.ToString());
        }

        // =====================================================================
        // LAYER 1 & 6: SSRF-PROOF HTTP CLIENT + ZERO-SECRET FUNKENWERK AUTH
        // =====================================================================
        public static HttpClient CreateHardenedApiClient(TimeSpan timeout)
        {
            var handler = new SocketsHttpHandler
            {
                AllowAutoRedirect = false, // Prevents 302 redirect from leaking Authorization header!
                UseCookies = false,
                UseProxy = false,
                ConnectTimeout = TimeSpan.FromSeconds(5),
                PooledConnectionLifetime = TimeSpan.FromMinutes(2),
                SslOptions =
                {
                    EnabledSslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13
                }
            };

            return new HttpClient(handler)
            {
                Timeout = timeout,
                MaxResponseContentBufferSize = 4L * 1024L * 1024L // 4 MB response memory cap
            };
        }

        public static string GetValidatedLocalOrConfiguredApiBaseUrl()
        {
            string candidate = "http://127.0.0.1/api";
            try
            {
                string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
                string[] settingsFiles =
                {
                    Path.Combine(CollectionRootDir, "Funkenwerk", "settings.json"),
                    Path.Combine(localAppData, "Funkenwerk", "settings.json")
                };

                foreach (string fwSettings in settingsFiles)
                {
                    if (!File.Exists(fwSettings)) continue;
                    using var doc = JsonDocument.Parse(File.ReadAllText(fwSettings), new JsonDocumentOptions { MaxDepth = 16 });
                    if (doc.RootElement.TryGetProperty("ApiBaseUrl", out var prop))
                    {
                        string? raw = prop.GetString();
                        if (!string.IsNullOrWhiteSpace(raw) && IsAllowedApiUrl(raw, out string normalized))
                        {
                            candidate = normalized;
                            if (!candidate.Contains("127.0.0.1", StringComparison.OrdinalIgnoreCase))
                                return candidate;
                        }
                    }
                }

                string osSettings = Path.Combine(localAppData, "FunkenwerkOS", "os_settings.json");
                if (File.Exists(osSettings))
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(osSettings), new JsonDocumentOptions { MaxDepth = 16 });
                    if (doc.RootElement.TryGetProperty("ApiEnvironment", out var envProp))
                    {
                        string? env = envProp.GetString()?.Trim().ToLowerInvariant();
                        if (env == "stage") return "https://stage.mein-seelenfunke.de/api";
                        if (env == "live") return "https://mein-seelenfunke.de/api";
                        if (env == "local") return "http://127.0.0.1/api";
                    }
                }
            }
            catch { }

            return candidate;
        }

        public static bool IsAllowedApiUrl(string url, out string normalized)
        {
            normalized = "";
            if (!Uri.TryCreate(url.Trim(), UriKind.Absolute, out Uri? uri)) return false;
            if (!string.IsNullOrEmpty(uri.UserInfo)) return false;

            bool hostOk = AllowedApiHosts.Any(h => string.Equals(h, uri.Host, StringComparison.OrdinalIgnoreCase));
            if (!hostOk) return false;

            bool isLocal = uri.Host.Equals("127.0.0.1", StringComparison.OrdinalIgnoreCase)
                        || uri.Host.Equals("localhost", StringComparison.OrdinalIgnoreCase)
                        || uri.Host.Equals("seelenfunke.test", StringComparison.OrdinalIgnoreCase);

            if (!isLocal && !uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                return false;

            if (isLocal && !uri.Scheme.Equals("http", StringComparison.OrdinalIgnoreCase) && !uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                return false;

            normalized = uri.GetLeftPart(UriPartial.Authority) + uri.AbsolutePath.TrimEnd('/');
            return true;
        }

        public static string ResolveApiBaseUrlForEnv(string? requestedEnv)
        {
            string env = (requestedEnv ?? "active").Trim().ToLowerInvariant();
            if (env == "local") return "http://127.0.0.1/api";
            if (env == "stage") return "https://stage.mein-seelenfunke.de/api";
            if (env == "live") return "https://mein-seelenfunke.de/api";
            return GetValidatedLocalOrConfiguredApiBaseUrl();
        }

        /// <summary>
        /// Attaches the Funkenwerk Admin Bearer Token directly to an HttpRequestMessage in memory
        /// without ever exposing the token string to any tool output, log, or configuration file.
        /// </summary>
        public static void AttachZeroSecretAdminAuth(HttpRequestMessage req, string baseUrl)
        {
            string token = ResolveFunkenwerkBearerTokenInternal(baseUrl);
            if (!string.IsNullOrWhiteSpace(token))
            {
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
            }
            req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        }

        private static string ResolveFunkenwerkBearerTokenInternal(string baseUrl)
        {
            bool isLocal = baseUrl.Contains("127.0.0.1", StringComparison.OrdinalIgnoreCase)
                        || baseUrl.Contains("localhost", StringComparison.OrdinalIgnoreCase)
                        || baseUrl.Contains("seelenfunke.test", StringComparison.OrdinalIgnoreCase);
            bool isStage = baseUrl.Contains("stage.mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase);
            bool isLive = !isStage && baseUrl.Contains("mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase);

            try
            {
                string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
                string[] settingsFiles =
                {
                    Path.Combine(localAppData, "Funkenwerk", "settings.json"),
                    Path.Combine(CollectionRootDir, "Funkenwerk", "settings.json"),
                    Path.Combine(localAppData, "FunkenwerkOS", "os_settings.json")
                };

                foreach (string fwSettings in settingsFiles)
                {
                    if (!File.Exists(fwSettings)) continue;
                    using var doc = JsonDocument.Parse(File.ReadAllText(fwSettings), new JsonDocumentOptions { MaxDepth = 16 });
                    var root = doc.RootElement;

                    var candidates = new List<string?>();
                    if (isLocal && root.TryGetProperty("ApiTokenLocal", out var tl)) candidates.Add(tl.GetString());
                    if (isLocal && root.TryGetProperty("EncryptedTokenLocal", out var etl)) candidates.Add(etl.GetString());
                    if (isStage && root.TryGetProperty("ApiTokenStage", out var ts)) candidates.Add(ts.GetString());
                    if (isStage && root.TryGetProperty("EncryptedTokenStage", out var ets)) candidates.Add(ets.GetString());
                    if (isLive && root.TryGetProperty("ApiTokenLive", out var tlv)) candidates.Add(tlv.GetString());
                    if (isLive && root.TryGetProperty("EncryptedTokenLive", out var etlv)) candidates.Add(etlv.GetString());

                    foreach (string? stored in candidates)
                    {
                        if (string.IsNullOrWhiteSpace(stored)) continue;
                        string decrypted = UnprotectFunkenwerkMainVaultSecret(stored);
                        if (!string.IsNullOrWhiteSpace(decrypted))
                            return decrypted.Trim();
                    }
                }
            }
            catch { }

            return "";
        }

        internal static string UnprotectFunkenwerkMainVaultSecret(string storedText)
        {
            const string prefixAes = "FWAES256GCM1:";
            const string prefixLegacy = "FWVAULT2:";
            string b64Payload;
            if (storedText.StartsWith(prefixAes, StringComparison.Ordinal))
                b64Payload = storedText.Substring(prefixAes.Length);
            else if (storedText.StartsWith(prefixLegacy, StringComparison.Ordinal))
                b64Payload = storedText.Substring(prefixLegacy.Length);
            else
                return "";

            byte[]? envelope = null;
            byte[]? key = null;
            byte[]? plainBytes = null;
            byte[]? pwdBytes = null;
            try
            {
                byte[] dpapiCipher = Convert.FromBase64String(b64Payload);
                envelope = DpapiUnprotectWin32(dpapiCipher, FunkenwerkMainEntropy);
                if (envelope == null || envelope.Length < 45 || envelope[0] != 0x02)
                    return "";

                byte[] salt = new byte[16];
                byte[] nonce = new byte[12];
                byte[] tag = new byte[16];
                int cipherLen = envelope.Length - 45;
                byte[] cipher = new byte[cipherLen];
                plainBytes = new byte[cipherLen];

                Buffer.BlockCopy(envelope, 1, salt, 0, 16);
                Buffer.BlockCopy(envelope, 17, nonce, 0, 12);
                Buffer.BlockCopy(envelope, 29, tag, 0, 16);
                Buffer.BlockCopy(envelope, 45, cipher, 0, cipherLen);

                string identity = $"{Environment.MachineName}|{Environment.UserName}|Funkenwerk_AES256GCM_KeyDerivation_v2";
                pwdBytes = Encoding.UTF8.GetBytes(identity);
                key = Rfc2898DeriveBytes.Pbkdf2(pwdBytes, salt, 100_000, HashAlgorithmName.SHA512, 32);

                using var aesGcm = new AesGcm(key, 16);
                aesGcm.Decrypt(nonce, cipher, tag, plainBytes, FunkenwerkMainAad);
                return Encoding.UTF8.GetString(plainBytes);
            }
            catch
            {
                return "";
            }
            finally
            {
                if (envelope != null) CryptographicOperations.ZeroMemory(envelope);
                if (key != null) CryptographicOperations.ZeroMemory(key);
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                if (pwdBytes != null) CryptographicOperations.ZeroMemory(pwdBytes);
            }
        }

        internal static string ProtectFunkenwerkMainVaultSecret(string plainSecret)
        {
            if (string.IsNullOrWhiteSpace(plainSecret))
                throw new ArgumentException("Geheimnis darf nicht leer sein.", nameof(plainSecret));

            byte[]? plainBytes = null;
            byte[]? pwdBytes = null;
            byte[]? key = null;
            byte[]? envelope = null;
            try
            {
                plainBytes = Encoding.UTF8.GetBytes(plainSecret.Trim());
                byte[] salt = RandomNumberGenerator.GetBytes(16);
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] tag = new byte[16];
                byte[] cipher = new byte[plainBytes.Length];

                string identity = $"{Environment.MachineName}|{Environment.UserName}|Funkenwerk_AES256GCM_KeyDerivation_v2";
                pwdBytes = Encoding.UTF8.GetBytes(identity);
                key = Rfc2898DeriveBytes.Pbkdf2(pwdBytes, salt, 100_000, HashAlgorithmName.SHA512, 32);

                using (var aesGcm = new AesGcm(key, 16))
                {
                    aesGcm.Encrypt(nonce, plainBytes, cipher, tag, FunkenwerkMainAad);
                }

                envelope = new byte[45 + cipher.Length];
                envelope[0] = 0x02;
                Buffer.BlockCopy(salt, 0, envelope, 1, 16);
                Buffer.BlockCopy(nonce, 0, envelope, 17, 12);
                Buffer.BlockCopy(tag, 0, envelope, 29, 16);
                Buffer.BlockCopy(cipher, 0, envelope, 45, cipher.Length);

                byte[] dpapiCipher = ProtectedData.Protect(envelope, FunkenwerkMainEntropy, DataProtectionScope.CurrentUser);
                return "FWAES256GCM1:" + Convert.ToBase64String(dpapiCipher);
            }
            finally
            {
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                if (pwdBytes != null) CryptographicOperations.ZeroMemory(pwdBytes);
                if (key != null) CryptographicOperations.ZeroMemory(key);
                if (envelope != null) CryptographicOperations.ZeroMemory(envelope);
            }
        }

        public static string StageLaserRelayVaultPath => Path.Combine(McpDataDir, "stage_laser_relay.dpapi");

        internal static string ResolveStageLaserRelayTokenFromVault()
        {
            try
            {
                string dedicatedPath = StageLaserRelayVaultPath;
                if (File.Exists(dedicatedPath) && !HasReparsePointInAncestry(dedicatedPath))
                {
                    string stored = File.ReadAllText(dedicatedPath, Encoding.UTF8).Trim();
                    string dec = UnprotectFunkenwerkMainVaultSecret(stored);
                    if (!string.IsNullOrWhiteSpace(dec))
                        return dec.Trim();
                }
            }
            catch { }

            return ResolveFunkenwerkBearerTokenInternal("https://stage.mein-seelenfunke.de/api");
        }

        internal static async Task<string> BootstrapStageLaserRelayScopedTokenZeroSecretAsync(string profileName = "Mittwald")
        {
            ValidateStageOnlyTarget(StageAllowedHost, StageWebRemoteDir, allowWorkerStage: false);

            using var creds = ResolveProfileInternal(profileName, StageWebRemoteDir, forceKeyOnlyNoPasswordDecrypt: true)
                ?? throw new InvalidOperationException($"Profil '{profileName}' wurde nicht gefunden.");

            ValidateStageOnlyTarget(creds.Host, creds.RemoteDirectory, allowWorkerStage: false);

            if (!HasAnySha256PinForHost(creds.Host, creds.Port))
            {
                AppendAuditLog("stage_laser_relay_bootstrap", $"{creds.Host}:{creds.Port}", "DENY:unpinned_host_key");
                throw new UnauthorizedAccessException($"Host '{creds.Host}:{creds.Port}' ist nicht per SHA-256 gepinnt.");
            }

            const string staticPhpTokenIssuer = """
                require 'vendor/autoload.php';
                $app = require_once 'bootstrap/app.php';
                $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                $appUrl = rtrim((string) config('app.url'), '/');
                if ($appUrl !== 'https://stage.mein-seelenfunke.de') {
                    fwrite(STDERR, 'LIVE-AIR-GAP SPERRE');
                    exit(2);
                }
                $admin = \App\Models\Admin\Admin::query()->orderBy('id')->first();
                if (!$admin) {
                    fwrite(STDERR, 'NO_STAGE_ADMIN');
                    exit(3);
                }
                $tokenName = 'FunkenwerkWinFailoverRelay-Desktop-admin';
                $admin->tokens()->where('name', $tokenName)->delete();
                $plain = $admin->createToken($tokenName, ['monitor:pulse'])->plainTextToken;
                echo 'FW_STAGE_RELAY_TOKEN:' . $plain;
                """;

            string b64Php = Convert.ToBase64String(Encoding.UTF8.GetBytes(staticPhpTokenIssuer.Replace("\r\n", "\n")));
            string remoteCmd = $"cd -- '{StageWebRemoteDir}' && php -r \"eval(base64_decode('{b64Php}'));\"";
            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();

            return await Task.Run(() =>
            {
                PrivateKeyFile? keyFile = null;
                try
                {
                    if (!string.IsNullOrWhiteSpace(creds.PrivateKeyPath) && File.Exists(creds.PrivateKeyPath))
                    {
                        keyFile = new PrivateKeyFile(creds.PrivateKeyPath);
                    }
                    if (keyFile == null)
                    {
                        AppendAuditLog("stage_laser_relay_bootstrap", creds.Name, "DENY:stage_requires_ed25519_key");
                        throw new UnauthorizedAccessException("Stage-Laser-Relay-Bootstrap erzwingt Ed25519-SSH-Key-Authentifizierung.");
                    }

                    var connInfo = new ConnectionInfo(creds.Host, creds.Port, creds.Username, new PrivateKeyAuthenticationMethod(creds.Username, keyFile))
                    {
                        Timeout = TimeSpan.FromSeconds(12)
                    };

                    using var ssh = new SshClient(connInfo);
                    bool hostVerified = false;
                    ssh.HostKeyReceived += (s, e) =>
                    {
                        hostVerified = VerifyPinnedHostKeyBytes(creds.Host, creds.Port, e.HostKey, e.HostKeyName, fwPinned);
                        e.CanTrust = hostVerified;
                    };

                    ssh.Connect();
                    if (!hostVerified || !ssh.IsConnected)
                        throw new UnauthorizedAccessException("SSH Host-Key-Prüfung für Stage-Laser-Relay-Bootstrap fehlgeschlagen.");

                    using var cmd = ssh.CreateCommand(remoteCmd);
                    cmd.CommandTimeout = TimeSpan.FromSeconds(20);
                    string stdout = cmd.Execute() ?? "";
                    int exitCode = cmd.ExitStatus ?? -1;
                    ssh.Disconnect();

                    if (exitCode != 0)
                    {
                        AppendAuditLog("stage_laser_relay_bootstrap", $"exit={exitCode}", "ERROR");
                        throw new InvalidOperationException($"Stage-Token-Ausstellung meldete Exit-Code {exitCode}.");
                    }

                    const string marker = "FW_STAGE_RELAY_TOKEN:";
                    int idx = stdout.IndexOf(marker, StringComparison.Ordinal);
                    if (idx < 0)
                        throw new InvalidOperationException("Stage-Token-Ausstellung lieferte kein gültiges Token-Präfix.");

                    string tokenCandidate = stdout.Substring(idx + marker.Length).Trim();
                    int nl = tokenCandidate.IndexOfAny(new[] { '\r', '\n', ' ', '\t' });
                    if (nl >= 0) tokenCandidate = tokenCandidate.Substring(0, nl).Trim();

                    if (!Regex.IsMatch(tokenCandidate, @"^\d+\|[A-Za-z0-9]{32,128}$"))
                        throw new InvalidOperationException("Ausgestelltes Stage-Relay-Token entspricht nicht dem Sanctum-Format.");

                    string encrypted = ProtectFunkenwerkMainVaultSecret(tokenCandidate);
                    string vaultPath = StageLaserRelayVaultPath;
                    string vaultDir = Path.GetDirectoryName(vaultPath) ?? McpDataDir;
                    Directory.CreateDirectory(vaultDir);
                    LockDownDirectoryAcl(vaultDir);
                    RejectReparsePointsInHierarchy(vaultPath);

                    string tmpPath = Path.Combine(vaultDir, $".srel_{Convert.ToHexString(RandomNumberGenerator.GetBytes(8)).ToLowerInvariant()}.tmp");
                    try
                    {
                        File.WriteAllText(tmpPath, encrypted, new UTF8Encoding(false));
                        RejectReparsePointsInHierarchy(vaultPath);
                        File.Move(tmpPath, vaultPath, overwrite: true);
                        LockDownFileAcl(vaultPath);
                    }
                    finally
                    {
                        try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
                    }

                    AppendAuditLog("stage_laser_relay_bootstrap", "FunkenwerkWinFailoverRelay-Desktop-admin(monitor:pulse)", "ALLOW");
                    return tokenCandidate;
                }
                finally
                {
                    keyFile?.Dispose();
                }
            });
        }

        private static byte[]? DpapiUnprotectWin32(byte[] cipherBytes, byte[] entropy)
        {
            IntPtr cipherPtr = IntPtr.Zero;
            IntPtr entropyPtr = IntPtr.Zero;
            DATA_BLOB outBlob = default;
            try
            {
                cipherPtr = Marshal.AllocHGlobal(cipherBytes.Length);
                Marshal.Copy(cipherBytes, 0, cipherPtr, cipherBytes.Length);

                entropyPtr = Marshal.AllocHGlobal(entropy.Length);
                Marshal.Copy(entropy, 0, entropyPtr, entropy.Length);

                var inBlob = new DATA_BLOB { cbData = cipherBytes.Length, pbData = cipherPtr };
                var entBlob = new DATA_BLOB { cbData = entropy.Length, pbData = entropyPtr };

                if (CryptUnprotectData(ref inBlob, IntPtr.Zero, ref entBlob, IntPtr.Zero, IntPtr.Zero, 0x1, out outBlob)
                    && outBlob.pbData != IntPtr.Zero && outBlob.cbData >= 0)
                {
                    byte[] plain = new byte[outBlob.cbData];
                    if (outBlob.cbData > 0)
                        Marshal.Copy(outBlob.pbData, plain, 0, outBlob.cbData);
                    return plain;
                }
            }
            catch { }
            finally
            {
                if (cipherPtr != IntPtr.Zero) Marshal.FreeHGlobal(cipherPtr);
                if (entropyPtr != IntPtr.Zero) Marshal.FreeHGlobal(entropyPtr);
                if (outBlob.pbData != IntPtr.Zero) LocalFree(outBlob.pbData);
            }
            return null;
        }

        // =====================================================================
        // LAYER 5: ZERO-SECRET SERVER PROFILES & FAIL-CLOSED PINNED SSH PRESETS
        // =====================================================================
        public static Dictionary<string, string> LoadFunkenwerkPinnedHostKeysSha256()
        {
            // Keys in FunkenwerkSsh / FunkenwerkFtp are "{host}:{port}:{algorithm}" -> "SHA256:{base64NoPadding}"
            var merged = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            string[] files =
            {
                Path.Combine(localAppData, "FunkenwerkSsh", "known_hosts_sha256.json"),
                Path.Combine(localAppData, "FunkenwerkFtp", "known_hosts_sha256.json")
            };

            foreach (string f in files)
            {
                if (!File.Exists(f)) continue;
                try
                {
                    var dict = JsonSerializer.Deserialize<Dictionary<string, string>>(File.ReadAllText(f));
                    if (dict != null)
                    {
                        foreach (var kv in dict)
                            merged[kv.Key.Trim()] = kv.Value.Trim();
                    }
                }
                catch { }
            }
            return merged;
        }

        private static List<string> FindOpenSshPinnedFingerprintsForHost(string host, int port, string? algorithmFilter = null)
        {
            var results = new List<string>();
            string khFile = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "known_hosts");
            if (!File.Exists(khFile)) return results;

            string cleanHost = host.Trim().ToLowerInvariant();
            string bracketHostPort = $"[{cleanHost}]:{port}";
            string[] candidateTokens = port == 22
                ? new[] { cleanHost, bracketHostPort }
                : new[] { bracketHostPort };

            try
            {
                foreach (string rawLine in File.ReadLines(khFile))
                {
                    string line = rawLine.Trim();
                    if (line.Length == 0 || line.StartsWith('#') || line.StartsWith('@')) continue;

                    string[] parts = line.Split(new[] { ' ', '\t' }, StringSplitOptions.RemoveEmptyEntries);
                    if (parts.Length < 3) continue;

                    string hostSpec = parts[0];
                    string algo = parts[1];
                    string pubKeyB64 = parts[2];

                    if (!string.IsNullOrEmpty(algorithmFilter) && !string.Equals(algo, algorithmFilter, StringComparison.OrdinalIgnoreCase))
                        continue;

                    bool matched = false;
                    if (hostSpec.StartsWith("|1|", StringComparison.Ordinal))
                    {
                        // OpenSSH HMAC-SHA1 hashed hostname: |1|<base64_salt>|<base64_hmac>
                        string[] hParts = hostSpec.Split('|', StringSplitOptions.RemoveEmptyEntries);
                        if (hParts.Length == 3 && hParts[0] == "1")
                        {
                            byte[] salt = Convert.FromBase64String(hParts[1]);
                            byte[] expectedMac = Convert.FromBase64String(hParts[2]);
                            foreach (string cand in candidateTokens)
                            {
                                byte[] actualMac = HMACSHA1.HashData(salt, Encoding.UTF8.GetBytes(cand));
                                if (CryptographicOperations.FixedTimeEquals(actualMac, expectedMac))
                                {
                                    matched = true;
                                    break;
                                }
                            }
                        }
                    }
                    else
                    {
                        foreach (string token in hostSpec.Split(','))
                        {
                            if (candidateTokens.Any(c => string.Equals(c, token.Trim(), StringComparison.OrdinalIgnoreCase)))
                            {
                                matched = true;
                                break;
                            }
                        }
                    }

                    if (matched)
                    {
                        byte[] keyBytes = Convert.FromBase64String(pubKeyB64);
                        string fp = "SHA256:" + Convert.ToBase64String(SHA256.HashData(keyBytes)).TrimEnd('=');
                        results.Add(fp);
                    }
                }
            }
            catch { }

            return results;
        }

        public static bool HasAnySha256PinForHost(string host, int port)
        {
            if (string.IsNullOrWhiteSpace(host)) return false;
            string prefix = $"{host.Trim().ToLowerInvariant()}:{port}:";
            var fwPins = LoadFunkenwerkPinnedHostKeysSha256();
            if (fwPins.Keys.Any(k => k.StartsWith(prefix, StringComparison.OrdinalIgnoreCase)
                                  || string.Equals(k, $"{host}:{port}", StringComparison.OrdinalIgnoreCase)
                                  || string.Equals(k, host, StringComparison.OrdinalIgnoreCase)))
            {
                return true;
            }
            return FindOpenSshPinnedFingerprintsForHost(host, port).Count > 0;
        }

        private static int CountTotalPinnedHostEntries()
        {
            int count = LoadFunkenwerkPinnedHostKeysSha256().Count;
            string khFile = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "known_hosts");
            if (File.Exists(khFile))
            {
                try
                {
                    count += File.ReadLines(khFile).Count(l => !string.IsNullOrWhiteSpace(l) && !l.TrimStart().StartsWith('#'));
                }
                catch { }
            }
            return count;
        }

        public static List<SanitizedServerProfileDto> GetSanitizedServerProfiles()
        {
            var list = new List<SanitizedServerProfileDto>();
            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);

            string ftpFile = Path.Combine(localAppData, "FunkenwerkFtp", "profiles_vault.json");
            if (File.Exists(ftpFile))
            {
                try
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(ftpFile), new JsonDocumentOptions { MaxDepth = 16 });
                    if (doc.RootElement.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var el in doc.RootElement.EnumerateArray())
                        {
                            string host = el.TryGetProperty("Host", out var h) ? (h.GetString() ?? "") : "";
                            int port = el.TryGetProperty("Port", out var p) && p.TryGetInt32(out int pv) ? pv : 22;
                            list.Add(new SanitizedServerProfileDto
                            {
                                SourceApp = "Funkenwerk FTP",
                                Name = el.TryGetProperty("Name", out var n) ? (n.GetString() ?? "") : "",
                                Protocol = el.TryGetProperty("Protocol", out var pr) ? (pr.GetString() ?? "SFTP") : "SFTP",
                                Host = host,
                                Port = port,
                                DefaultRemoteDirectory = el.TryGetProperty("RemoteDirectory", out var rd) ? (rd.GetString() ?? "/") : "/",
                                UsesPrivateKey = el.TryGetProperty("PrivateKeyPath", out var pk) && !string.IsNullOrWhiteSpace(pk.GetString()),
                                HasSavedPasswordInVault = el.TryGetProperty("EncryptedPassword", out var ep) && !string.IsNullOrWhiteSpace(ep.GetString()),
                                IsHostKeyPinnedSha256 = HasAnySha256PinForHost(host, port)
                            });
                        }
                    }
                }
                catch { }
            }

            string sshFile = Path.Combine(localAppData, "FunkenwerkSsh", "ssh_profiles_vault.json");
            if (File.Exists(sshFile))
            {
                try
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(sshFile), new JsonDocumentOptions { MaxDepth = 16 });
                    if (doc.RootElement.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var el in doc.RootElement.EnumerateArray())
                        {
                            string host = el.TryGetProperty("Host", out var h) ? (h.GetString() ?? "") : "";
                            int port = el.TryGetProperty("Port", out var p) && p.TryGetInt32(out int pv) ? pv : 22;
                            list.Add(new SanitizedServerProfileDto
                            {
                                SourceApp = "Funkenwerk SSH",
                                Name = el.TryGetProperty("Name", out var n) ? (n.GetString() ?? "") : "",
                                Protocol = "SSH",
                                Host = host,
                                Port = port,
                                DefaultRemoteDirectory = el.TryGetProperty("StartupDirectory", out var sd) && !string.IsNullOrWhiteSpace(sd.GetString()) ? sd.GetString()! : "/",
                                UsesPrivateKey = el.TryGetProperty("PrivateKeyPath", out var pk) && !string.IsNullOrWhiteSpace(pk.GetString()),
                                HasSavedPasswordInVault = el.TryGetProperty("EncryptedPassword", out var ep) && !string.IsNullOrWhiteSpace(ep.GetString()),
                                IsHostKeyPinnedSha256 = HasAnySha256PinForHost(host, port)
                            });
                        }
                    }
                }
                catch { }
            }

            return list;
        }

        private sealed class InternalResolvedSshCreds : IDisposable
        {
            public string Name = "";
            public string Host = "";
            public int Port = 22;
            public string Username = "";
            public byte[]? PasswordUtf8;
            public string PrivateKeyPath = "";
            public string RemoteDirectory = "/";

            public void Dispose()
            {
                if (PasswordUtf8 != null)
                {
                    CryptographicOperations.ZeroMemory(PasswordUtf8);
                    PasswordUtf8 = null;
                }
            }
        }

        private static void ValidateSshEndpointParameters(string host, int port, string username, string remoteDir)
        {
            if (string.IsNullOrWhiteSpace(host) || host.Length > 255 || !SafeHostRegex.IsMatch(host))
                throw new UnauthorizedAccessException("Ungültiger SSH-Hostname im Sicherheitsfilter blockiert.");

            if (port < 1 || port > 65535)
                throw new UnauthorizedAccessException("Ungültiger SSH-Port blockiert.");

            if (string.IsNullOrWhiteSpace(username) || username.Length > 128 || !SafeSshUserRegex.IsMatch(username))
                throw new UnauthorizedAccessException("Ungültiger SSH-Benutzername im Sicherheitsfilter blockiert.");

            if (!string.IsNullOrWhiteSpace(remoteDir))
            {
                if (remoteDir.Length > 512 || !SafeRemoteDirRegex.IsMatch(remoteDir) || remoteDir.Contains("..", StringComparison.Ordinal))
                    throw new UnauthorizedAccessException("Ungültiger Remote-Verzeichnispfad im Sicherheitsfilter blockiert.");
            }
        }

        private static InternalResolvedSshCreds? ResolveProfileInternal(
            string profileName,
            string? overrideRemoteDir,
            bool forceKeyOnlyNoPasswordDecrypt = false)
        {
            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);

            var sources = new (string VaultDir, string FileName, string Prefix, byte[] Entropy, string DirProp)[]
            {
                (Path.Combine(localAppData, "FunkenwerkSsh"), "ssh_profiles_vault.json", "FWSSHAES256GCM1:", SshVaultEntropy, "StartupDirectory"),
                (Path.Combine(localAppData, "FunkenwerkFtp"), "profiles_vault.json", "FWAES256GCM1:", FtpVaultEntropy, "RemoteDirectory")
            };

            foreach (var src in sources)
            {
                string path = Path.Combine(src.VaultDir, src.FileName);
                if (!File.Exists(path)) continue;

                try
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(path), new JsonDocumentOptions { MaxDepth = 16 });
                    if (doc.RootElement.ValueKind != JsonValueKind.Array) continue;

                    foreach (var el in doc.RootElement.EnumerateArray())
                    {
                        string name = el.TryGetProperty("Name", out var n) ? (n.GetString() ?? "") : "";
                        if (!string.Equals(name, profileName, StringComparison.OrdinalIgnoreCase))
                            continue;

                        string host = (el.TryGetProperty("Host", out var h) ? (h.GetString() ?? "") : "").Trim();
                        int port = el.TryGetProperty("Port", out var p) && p.TryGetInt32(out int pv) && pv > 0 && pv != 21 ? pv : 22;
                        string user = (el.TryGetProperty("Username", out var u) ? (u.GetString() ?? "") : "").Trim();
                        string keyPath = (el.TryGetProperty("PrivateKeyPath", out var pk) ? (pk.GetString() ?? "") : "").Trim();
                        string remDir = !string.IsNullOrWhiteSpace(overrideRemoteDir)
                            ? overrideRemoteDir!.Trim()
                            : (el.TryGetProperty(src.DirProp, out var dp) && !string.IsNullOrWhiteSpace(dp.GetString()) ? dp.GetString()!.Trim() : "/");

                        if (host.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase))
                        {
                            bool isNodeBridgeTarget = name.Equals("Mittwald Node.js App", StringComparison.OrdinalIgnoreCase)
                                || remDir.Contains("twilio-bridge", StringComparison.OrdinalIgnoreCase);

                            if (isNodeBridgeTarget)
                            {
                                if (string.IsNullOrWhiteSpace(user)
                                    || user.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase)
                                    || user.EndsWith("@a-bycqrf", StringComparison.OrdinalIgnoreCase))
                                {
                                    user = StageTwilioBridgeSshUser;
                                }

                                if (string.IsNullOrWhiteSpace(overrideRemoteDir)
                                    && (remDir == "/" || remDir.EndsWith("/seelenfunke-stage", StringComparison.OrdinalIgnoreCase)))
                                {
                                    remDir = StageTwilioBridgeRemoteDir;
                                }
                            }
                            else if (string.IsNullOrWhiteSpace(user) || user.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase))
                            {
                                user = remDir.Contains("worker-stage", StringComparison.OrdinalIgnoreCase)
                                    ? "kontakt@mein-seelenfunke.de@a-iurgq8"
                                    : "kontakt@mein-seelenfunke.de@a-lc6tkm";
                            }
                        }

                        if (string.IsNullOrWhiteSpace(keyPath) || keyPath.EndsWith(".ppk", StringComparison.OrdinalIgnoreCase) || !File.Exists(keyPath))
                        {
                            string defaultEd25519 = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "id_ed25519");
                            if (File.Exists(defaultEd25519)) keyPath = defaultEd25519;
                        }

                        ValidateSshEndpointParameters(host, port, user, remDir);

                        byte[]? pwdBytes = null;
                        bool skipPasswordDecrypt = forceKeyOnlyNoPasswordDecrypt
                            || (host.Equals(StageAllowedHost, StringComparison.OrdinalIgnoreCase)
                                && !string.IsNullOrWhiteSpace(keyPath)
                                && File.Exists(keyPath));

                        if (!skipPasswordDecrypt
                            && el.TryGetProperty("EncryptedPassword", out var ep)
                            && !string.IsNullOrWhiteSpace(ep.GetString()))
                        {
                            pwdBytes = DecryptFtpOrSshVaultBytes(src.VaultDir, ep.GetString()!, src.Prefix, src.Entropy);
                        }

                        return new InternalResolvedSshCreds
                        {
                            Name = name,
                            Host = host,
                            Port = port,
                            Username = user,
                            PasswordUtf8 = pwdBytes,
                            PrivateKeyPath = keyPath,
                            RemoteDirectory = remDir
                        };
                    }
                }
                catch (UnauthorizedAccessException)
                {
                    throw;
                }
                catch { }
            }

            return null;
        }

        private static byte[]? DecryptFtpOrSshVaultBytes(string vaultDir, string cipherText, string prefix, byte[] entropy)
        {
            if (!cipherText.StartsWith(prefix, StringComparison.Ordinal)) return null;
            string masterFile = Path.Combine(vaultDir, "master.dpapi");
            if (!File.Exists(masterFile)) return null;

            byte[]? key = null;
            byte[]? combined = null;
            try
            {
                key = ProtectedData.Unprotect(File.ReadAllBytes(masterFile), entropy, DataProtectionScope.CurrentUser);
                if (key.Length != 32) return null;

                byte[] dpapiWrapped = Convert.FromBase64String(cipherText.Substring(prefix.Length));
                combined = ProtectedData.Unprotect(dpapiWrapped, entropy, DataProtectionScope.CurrentUser);
                if (combined.Length < 28) return null;

                byte[] nonce = new byte[12];
                byte[] tag = new byte[16];
                byte[] cipher = new byte[combined.Length - 28];
                byte[] plain = new byte[cipher.Length];

                Buffer.BlockCopy(combined, 0, nonce, 0, 12);
                Buffer.BlockCopy(combined, 12, tag, 0, 16);
                Buffer.BlockCopy(combined, 28, cipher, 0, cipher.Length);

                using var aesGcm = new AesGcm(key, 16);
                aesGcm.Decrypt(nonce, cipher, tag, plain, entropy);
                return plain;
            }
            catch
            {
                return null;
            }
            finally
            {
                if (key != null) CryptographicOperations.ZeroMemory(key);
                if (combined != null) CryptographicOperations.ZeroMemory(combined);
            }
        }

        public static void CreateBlindSshHandoffTicket(string profileName, string? remoteDirectory)
        {
            if (!string.IsNullOrWhiteSpace(remoteDirectory))
            {
                if (remoteDirectory.Length > 512 || !SafeRemoteDirRegex.IsMatch(remoteDirectory) || remoteDirectory.Contains("..", StringComparison.Ordinal))
                    throw new UnauthorizedAccessException("Ungültige Sonderzeichen oder Traversal im Remote-Pfad blockiert.");
            }

            using var creds = ResolveProfileInternal(profileName, remoteDirectory)
                ?? throw new InvalidOperationException($"Profil '{profileName}' wurde im lokalen Tresor nicht gefunden.");

            string sshVaultDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkSsh");
            Directory.CreateDirectory(sshVaultDir);
            LockDownDirectoryAcl(sshVaultDir);
            string handoffFile = Path.Combine(sshVaultDir, "handoff_ticket.dpapi");

            var payload = new Dictionary<string, string>
            {
                ["Name"] = creds.Name,
                ["Host"] = creds.Host,
                ["Port"] = creds.Port.ToString(),
                ["Username"] = creds.Username,
                ["Password"] = creds.PasswordUtf8 != null ? Encoding.UTF8.GetString(creds.PasswordUtf8) : "",
                ["PrivateKeyPath"] = creds.PrivateKeyPath,
                ["RemoteDirectory"] = creds.RemoteDirectory,
                ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
            };

            byte[] plainBytes = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(payload));
            try
            {
                byte[] encrypted = ProtectedData.Protect(plainBytes, HandoffEntropy, DataProtectionScope.CurrentUser);
                File.WriteAllBytes(handoffFile, encrypted);
                LockDownFileAcl(handoffFile);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        public static void ValidateStageOnlyTarget(string host, string? remoteDirectory, bool allowWorkerStage = false)
        {
            string normHost = (host ?? "").Trim();
            string normDir = (remoteDirectory ?? "").Trim().Replace('\\', '/').TrimEnd('/');

            if (string.IsNullOrWhiteSpace(normDir)
                || normDir.Contains("..", StringComparison.Ordinal)
                || normDir.Contains("live", StringComparison.OrdinalIgnoreCase)
                || normDir.Contains("production", StringComparison.OrdinalIgnoreCase)
                || normDir.Contains("steinhauer-invest", StringComparison.OrdinalIgnoreCase))
            {
                AppendAuditLog("stage_airgap_guard", $"{normHost}:{normDir}", "DENY:live_airgap_blocked");
                throw new UnauthorizedAccessException("LIVE-AIR-GAP SPERRE: Zugriff auf Live-/Production- oder Fremdpfade ist technisch gesperrt.");
            }

            if (!string.Equals(normHost, StageAllowedHost, StringComparison.OrdinalIgnoreCase))
            {
                AppendAuditLog("stage_airgap_guard", $"{normHost}:{normDir}", "DENY:non_stage_host");
                throw new UnauthorizedAccessException($"Stage-Operationen sind ausschließlich auf '{StageAllowedHost}' erlaubt.");
            }

            bool isStageWeb = string.Equals(normDir, StageWebRemoteDir, StringComparison.Ordinal);
            bool isStageWorker = allowWorkerStage && (
                string.Equals(normDir, StageWorkerRemoteDir, StringComparison.Ordinal) ||
                string.Equals(normDir, StageTwilioBridgeRemoteDir, StringComparison.Ordinal));

            if (!isStageWeb && !isStageWorker)
            {
                AppendAuditLog("stage_airgap_guard", $"{normHost}:{normDir}", "DENY:non_stage_dir");
                string expected = allowWorkerStage
                    ? $"{StageWebRemoteDir}, {StageWorkerRemoteDir} oder {StageTwilioBridgeRemoteDir}"
                    : StageWebRemoteDir;
                throw new UnauthorizedAccessException($"Stage-Operation abgelehnt: Zielpfad '{normDir}' entspricht nicht exakt '{expected}'.");
            }
        }

        public static void ValidateMiniPcOnlyTarget(string host, string? safeSubdirectory = null)
        {
            string normHost = (host ?? "").Trim();
            string normDir = (safeSubdirectory ?? "").Trim().Replace('\\', '/');

            if (normDir.Contains("..", StringComparison.Ordinal)
                || normDir.Contains("live", StringComparison.OrdinalIgnoreCase)
                || normDir.Contains("production", StringComparison.OrdinalIgnoreCase)
                || normDir.Contains("steinhauer-invest", StringComparison.OrdinalIgnoreCase)
                || normDir.Contains("master.key", StringComparison.OrdinalIgnoreCase))
            {
                AppendAuditLog("minipc_airgap_guard", $"{normHost}:{normDir}", "DENY:blocked_path");
                throw new UnauthorizedAccessException("MINI-PC-SPERRE: Ungültiger oder gesperrter Zielpfad.");
            }

            if (!string.Equals(normHost, MiniPcAllowedHost, StringComparison.OrdinalIgnoreCase))
            {
                AppendAuditLog("minipc_airgap_guard", $"{normHost}:{normDir}", "DENY:non_minipc_host");
                throw new UnauthorizedAccessException($"Mini-PC-Sentinel-Operationen sind ausschließlich auf '{MiniPcAllowedHost}' erlaubt.");
            }
        }

        public static string ValidatePureShopAssetRelativePath(string relPath)
        {
            string norm = (relPath ?? "").Trim().Replace('\\', '/');

            if (string.IsNullOrWhiteSpace(norm)
                || norm.Length > 240
                || norm.StartsWith("/", StringComparison.Ordinal)
                || norm.EndsWith("/", StringComparison.Ordinal)
                || norm.Contains("//", StringComparison.Ordinal)
                || norm.Contains("..", StringComparison.Ordinal)
                || norm.Contains(":", StringComparison.Ordinal)
                || norm.Any(c => char.IsControl(c))
                || !SafeShopRelPathRegex.IsMatch(norm))
            {
                throw new UnauthorizedAccessException($"Ungültiger Shop-Asset-Pfad blockiert: '{relPath}'.");
            }

            foreach (string blockedPrefix in BlockedShopRootPrefixes)
            {
                if (norm.StartsWith(blockedPrefix, StringComparison.OrdinalIgnoreCase))
                {
                    AppendAuditLog("shop_asset_guard", norm, "DENY:local_only_root_blocked");
                    throw new UnauthorizedAccessException($"LOKAL-SPERRE: Pfad '{norm}' (Musik/Buchhaltung/Leitung/Privat) darf niemals synchronisiert werden.");
                }
            }

            string[] segments = norm.Split('/');
            foreach (string seg in segments)
            {
                if (string.IsNullOrWhiteSpace(seg) || seg == "." || seg == "..")
                    throw new UnauthorizedAccessException($"Ungültiges Pfadsegment in '{norm}'.");

                if (BlockedShopSegments.Contains(seg)
                    || seg.StartsWith(".env", StringComparison.OrdinalIgnoreCase)
                    || seg.StartsWith("firebase", StringComparison.OrdinalIgnoreCase))
                {
                    AppendAuditLog("shop_asset_guard", norm, "DENY:local_only_segment_blocked");
                    throw new UnauthorizedAccessException($"LOKAL-SPERRE: Segment '{seg}' in '{norm}' ist zu 100 % lokal gesperrt.");
                }
            }

            string fileName = segments[^1];
            if (fileName.Contains("_orig_backup.", StringComparison.OrdinalIgnoreCase)
                || fileName.EndsWith(".bak", StringComparison.OrdinalIgnoreCase)
                || fileName.StartsWith("echtgold-seelen-herz-", StringComparison.OrdinalIgnoreCase))
            {
                throw new UnauthorizedAccessException($"Backup-/Altdatei '{norm}' ist vom Stage-Sync ausgeschlossen.");
            }

            bool hasAllowedPrefix = AllowedShopAssetPrefixes.Any(p => norm.StartsWith(p, StringComparison.OrdinalIgnoreCase));
            if (!hasAllowedPrefix)
            {
                AppendAuditLog("shop_asset_guard", norm, "DENY:outside_shop_whitelist");
                throw new UnauthorizedAccessException($"Pfad '{norm}' liegt außerhalb der 4 erlaubten Shop-Asset-Verzeichnisse.");
            }

            string ext = Path.GetExtension(fileName);
            if (string.IsNullOrWhiteSpace(ext) || !AllowedShopExtensions.Contains(ext))
            {
                AppendAuditLog("shop_asset_guard", norm, "DENY:blocked_extension");
                throw new UnauthorizedAccessException($"Dateiendung '{ext}' ist für den Shop-Asset-Sync gesperrt.");
            }

            if (string.Equals(ext, ".json", StringComparison.OrdinalIgnoreCase))
            {
                if (!norm.StartsWith("public/produkte/configurator/", StringComparison.OrdinalIgnoreCase)
                    && !norm.StartsWith("public/produkte/configurator-presets/", StringComparison.OrdinalIgnoreCase))
                {
                    throw new UnauthorizedAccessException($"JSON-Datei '{norm}' außerhalb des Konfigurators ist gesperrt.");
                }
            }

            if (string.Equals(ext, ".zip", StringComparison.OrdinalIgnoreCase))
            {
                if (!SafeSecureZipRegex.IsMatch(norm))
                {
                    throw new UnauthorizedAccessException($"ZIP-Datei '{norm}' außerhalb von produkte/products-secure/ ist gesperrt.");
                }
            }

            return norm;
        }

        public static async Task<string> ExecutePinnedSshPresetAsync(string profileName, string preset, string? safeSubdirectory = null)
        {
            EnforceRateLimit("ssh_exec_safe_readonly", isSshCall: true);

            // Strict preset command allowlist - NO arbitrary shell strings ever accepted!
            string remoteCommand = preset switch
            {
                "git_status" => "git status --short --branch",
                "git_log" => "git log -n 5 --oneline",
                "git_branch" => "git branch -v",
                "php_version" => "php -v",
                "artisan_about" => "php artisan --version",
                "disk_usage" => "df -h .",
                "uptime_and_load" => "uptime",
                "list_dir" => "ls -la",
                "stage_git_pull" => "git pull --ff-only origin main",
                "stage_deploy" => "bash ./funki stage-deploy",
                "stage_worker_restart" => "(mittnitectl job restart 2>/dev/null || php artisan queue:restart) && (mittnitectl job status 2>/dev/null || true)",
                "stage_sentinel_export" => "(php artisan system:sentinel-triage --probe-bridges >/dev/null 2>&1 || true) && php artisan system:sentinel-inbox --export-json 2>/dev/null",
                "stage_sentinel_resolve_all" => "php artisan system:sentinel-inbox --resolve-all --resolved-by=Antigravity 2>/dev/null",
                "minipc_sentinel_export" =>
                    "python3 -c 'import json,os; d=\"/home/alina/.local/share/FunkenwerkOS\"; " +
                    "rd=lambda f: (json.load(open(os.path.join(d,f),\"r\",encoding=\"utf-8\")) if os.path.isfile(os.path.join(d,f)) and os.path.getsize(os.path.join(d,f))>0 else None); " +
                    "ho=rd(\"antigravity_error_handoff.json\"); " +
                    "ho=ho if isinstance(ho,dict) and ho.get(\"status\")!=\"resolved\" else None; " +
                    "hi=rd(\"sentinel_history.json\") or []; " +
                    "uh=[x for x in (hi if isinstance(hi,list) else []) if isinstance(x,dict) and not x.get(\"AutoHealed\",x.get(\"auto_healed\",False))][-25:]; " +
                    "qu=rd(\"sentinel_queue.json\") or []; " +
                    "print(json.dumps({\"source\":\"minipc_direct\",\"handoff\":ho,\"open_history\":uh,\"queued\":qu if isinstance(qu,list) else []}))'",
                "minipc_sentinel_resolve_all" =>
                    "python3 -c \"import base64; exec(base64.b64decode('" + Convert.ToBase64String(Encoding.UTF8.GetBytes("""
import json, os, datetime, time
d = "/home/alina/.local/share/FunkenwerkOS"
ad = os.path.join(d, "archive")
os.makedirs(ad, mode=0o700, exist_ok=True)
now = datetime.datetime.now(datetime.timezone.utc)
stamp = now.strftime("%Y-%m-%d_%H%M%S")
ym = now.strftime("%Y-%m")
c = 0
ab = 0
hp = os.path.join(d, "antigravity_error_handoff.json")
if os.path.isfile(hp) and os.path.getsize(hp) > 0:
    try:
        ho = json.load(open(hp, "r", encoding="utf-8"))
        if isinstance(ho, dict):
            ho.update({"status": "resolved", "resolved_by": "Antigravity", "resolved_at": now.isoformat()})
        ap = os.path.join(ad, f"handoff_{stamp}.json")
        json.dump(ho, open(ap, "w", encoding="utf-8"), indent=2, ensure_ascii=False)
        os.chmod(ap, 0o600)
        ab += os.path.getsize(hp)
        c += 1
    except Exception:
        pass
if os.path.isfile(hp):
    os.remove(hp)
sp = os.path.join(d, "sentinel_history.json")
if os.path.isfile(sp) and os.path.getsize(sp) > 0:
    ab += os.path.getsize(sp)
    try:
        hi = json.load(open(sp, "r", encoding="utf-8"))
        if isinstance(hi, list) and len(hi) > 0:
            af = os.path.join(ad, f"sentinel_archive_{ym}.jsonl")
            with open(af, "a", encoding="utf-8") as out:
                for x in hi:
                    if isinstance(x, dict):
                        x.update({"AutoHealed": True, "ArchivedAt": now.isoformat(), "ResolvedBy": "Antigravity"})
                    out.write(json.dumps(x, ensure_ascii=False) + "\n")
            os.chmod(af, 0o600)
    except Exception:
        pass
    open(sp, "w", encoding="utf-8").write("")
    os.chmod(sp, 0o600)
qp = os.path.join(d, "sentinel_queue.json")
if os.path.isfile(qp):
    os.remove(qp)
cut = time.time() - 30 * 86400
for f in os.listdir(ad):
    fp = os.path.join(ad, f)
    if os.path.isfile(fp) and os.path.getmtime(fp) < cut:
        os.remove(fp)
rem = os.path.getsize(sp) if os.path.isfile(sp) else 0
print(json.dumps({"status": "success", "archived_handoffs": c, "archived_bytes": ab, "active_log_bytes_remaining": rem}))
""".Replace("\r\n", "\n"))) + "'))\"",
                _ => throw new UnauthorizedAccessException($"SSH-Preset '{preset}' ist nicht in der Sicherheits-Allowlist enthalten.")
            };

            bool isStageMutationPreset = preset is "stage_git_pull" or "stage_deploy" or "stage_worker_restart" or "stage_sentinel_export" or "stage_sentinel_resolve_all";
            bool isMiniPcSentinelPreset = preset is "minipc_sentinel_export" or "minipc_sentinel_resolve_all";
            bool enforceEd25519KeyOnly = isStageMutationPreset || isMiniPcSentinelPreset;

            if (!string.IsNullOrWhiteSpace(safeSubdirectory))
            {
                if (safeSubdirectory.Length > 512 || !SafeRemoteDirRegex.IsMatch(safeSubdirectory) || safeSubdirectory.Contains("..", StringComparison.Ordinal))
                    throw new UnauthorizedAccessException("Ungültiger Server-Pfad im Sicherheitsfilter blockiert.");

                if (isStageMutationPreset)
                {
                    ValidateStageOnlyTarget(StageAllowedHost, safeSubdirectory, allowWorkerStage: preset == "stage_worker_restart");
                }
                else if (isMiniPcSentinelPreset)
                {
                    ValidateMiniPcOnlyTarget(MiniPcAllowedHost, safeSubdirectory);
                }
            }

            using var creds = ResolveProfileInternal(profileName, safeSubdirectory, forceKeyOnlyNoPasswordDecrypt: enforceEd25519KeyOnly)
                ?? throw new InvalidOperationException($"Profil '{profileName}' wurde nicht gefunden.");

            if (isStageMutationPreset)
            {
                ValidateStageOnlyTarget(creds.Host, creds.RemoteDirectory, allowWorkerStage: preset == "stage_worker_restart");
            }
            else if (isMiniPcSentinelPreset)
            {
                ValidateMiniPcOnlyTarget(creds.Host, safeSubdirectory);
            }

            // FAIL-CLOSED SHA-256 HOST-KEY PINNING (Checks FunkenwerkSsh, FunkenwerkFtp, and OpenSSH ~/.ssh/known_hosts)
            if (!HasAnySha256PinForHost(creds.Host, creds.Port))
            {
                string hostPortKey = $"{creds.Host}:{creds.Port}";
                AppendAuditLog("ssh_exec_safe_readonly", hostPortKey, "DENY:unpinned_host_key");
                throw new UnauthorizedAccessException(
                    $"Host '{hostPortKey}' ist noch nicht per SHA-256 gepinnt. Bitte verbinde dich einmalig interaktiv über Funkenwerk SSH / FTP zur Host-Key-Verifizierung.");
            }

            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();
            int timeoutSeconds = preset switch
            {
                "stage_deploy" => 150,
                "stage_sentinel_export" => 45,
                "stage_worker_restart" => 35,
                "stage_sentinel_resolve_all" => 25,
                "minipc_sentinel_export" => 15,
                "minipc_sentinel_resolve_all" => 15,
                _ => 15
            };

            return await Task.Run(() =>
            {
                var authMethods = new List<AuthenticationMethod>();
                PrivateKeyFile? keyFile = null;
                try
                {
                    if (!string.IsNullOrWhiteSpace(creds.PrivateKeyPath) && File.Exists(creds.PrivateKeyPath))
                    {
                        keyFile = new PrivateKeyFile(creds.PrivateKeyPath);
                        authMethods.Add(new PrivateKeyAuthenticationMethod(creds.Username, keyFile));
                    }
                    if (enforceEd25519KeyOnly)
                    {
                        if (keyFile == null)
                        {
                            AppendAuditLog("ssh_exec_safe_readonly", $"{creds.Name}:{preset}", "DENY:stage_requires_ed25519_key");
                            throw new UnauthorizedAccessException(
                                "Stage-Autopilot erzwingt reine Ed25519-SSH-Key-Authentifizierung (Passwort-Fallback ist aus Sicherheitsgründen hart deaktiviert).");
                        }
                    }
                    else if (creds.PasswordUtf8 != null && creds.PasswordUtf8.Length > 0)
                    {
                        authMethods.Add(new PasswordAuthenticationMethod(creds.Username, Encoding.UTF8.GetString(creds.PasswordUtf8)));
                    }
                    if (authMethods.Count == 0)
                        throw new InvalidOperationException("Keine gültige Authentifizierungsmethode im Tresor hinterlegt.");

                    var connInfo = new ConnectionInfo(creds.Host, creds.Port, creds.Username, authMethods.ToArray())
                    {
                        Timeout = TimeSpan.FromSeconds(12)
                    };

                    using var client = new SshClient(connInfo);
                    bool hostVerified = false;

                    client.HostKeyReceived += (s, e) =>
                    {
                        string actualFp = "SHA256:" + Convert.ToBase64String(SHA256.HashData(e.HostKey)).TrimEnd('=');
                        byte[] actualBytes = Encoding.ASCII.GetBytes(actualFp);

                        var allowedFps = new List<string>();
                        string exactKey = $"{creds.Host.ToLowerInvariant()}:{creds.Port}:{e.HostKeyName}";
                        if (fwPinned.TryGetValue(exactKey, out string? fwExact)) allowedFps.Add(fwExact);
                        if (fwPinned.TryGetValue($"{creds.Host}:{creds.Port}", out string? fwHostPort)) allowedFps.Add(fwHostPort);
                        allowedFps.AddRange(FindOpenSshPinnedFingerprintsForHost(creds.Host, creds.Port, e.HostKeyName));

                        bool matchedPin = false;
                        foreach (string candidateFp in allowedFps)
                        {
                            byte[] expectedBytes = Encoding.ASCII.GetBytes(candidateFp.Trim());
                            if (actualBytes.Length == expectedBytes.Length &&
                                CryptographicOperations.FixedTimeEquals(actualBytes, expectedBytes))
                            {
                                matchedPin = true;
                                break;
                            }
                        }

                        hostVerified = matchedPin;
                        e.CanTrust = matchedPin;
                    };

                    client.Connect();
                    if (!hostVerified || !client.IsConnected)
                        throw new UnauthorizedAccessException("SSH Host-Key-Prüfung fehlgeschlagen (MITM-Schutz aktiv).");

                    // RemoteDirectory was strictly validated against ^[A-Za-z0-9/_.\-~]+$ in ValidateSshEndpointParameters
                    string fullCmd;
                    if (preset == "stage_worker_restart" && !string.IsNullOrWhiteSpace(creds.RemoteDirectory) && creds.RemoteDirectory != "/")
                    {
                        fullCmd = $"(cd -- '{creds.RemoteDirectory}' 2>/dev/null || true) && {remoteCommand}";
                    }
                    else
                    {
                        fullCmd = !string.IsNullOrWhiteSpace(creds.RemoteDirectory) && creds.RemoteDirectory != "/"
                            ? $"cd -- '{creds.RemoteDirectory}' && {remoteCommand}"
                            : remoteCommand;
                    }

                    using var cmd = client.CreateCommand(fullCmd);
                    cmd.CommandTimeout = TimeSpan.FromSeconds(timeoutSeconds);
                    string stdout = cmd.Execute();
                    string stderr = cmd.Error ?? "";
                    int exitCode = cmd.ExitStatus ?? 0;
                    client.Disconnect();

                    bool isPureJsonPreset = preset is "stage_sentinel_export" or "stage_sentinel_resolve_all" or "minipc_sentinel_export" or "minipc_sentinel_resolve_all";
                    string combined = (isPureJsonPreset && exitCode == 0)
                        ? stdout
                        : (string.IsNullOrWhiteSpace(stderr) ? stdout : $"{stdout}\n{stderr}");
                    combined = AnsiEscapeRegex.Replace(combined, "");
                    int maxChars = (preset is "stage_sentinel_export" or "minipc_sentinel_export") ? 262144 : 16384;
                    if (combined.Length > maxChars)
                        combined = combined.Substring(0, maxChars) + "\n...[Ausgabe gekürzt]";

                    if ((isStageMutationPreset || isMiniPcSentinelPreset) && exitCode != 0)
                    {
                        AppendAuditLog("ssh_exec_safe_readonly", $"{creds.Name}:{preset}:exit={exitCode}", "ERROR");
                        throw new InvalidOperationException($"Stage-Preset '{preset}' meldete Exit-Code {exitCode}:\n{ScrubSecrets(combined.Trim())}");
                    }

                    AppendAuditLog("ssh_exec_safe_readonly", $"{creds.Name}:{preset}", "ALLOW");
                    return ScrubSecrets(combined.Trim());
                }
                finally
                {
                    keyFile?.Dispose();
                }
            });
        }

        public static async Task<StageShopAssetSyncResultDto> SyncStageShopAssetsDeltaAsync(
            string profileName = "Mittwald",
            bool dryRun = false,
            string? customLocalStorageAppRoot = null)
        {
            EnforceRateLimit("stage_sync_shop_assets", isSshCall: true);

            string storageRoot = string.IsNullOrWhiteSpace(customLocalStorageAppRoot)
                ? DefaultLocalStorageAppRoot
                : Path.GetFullPath(customLocalStorageAppRoot);

            if (!Directory.Exists(storageRoot))
                throw new DirectoryNotFoundException($"Lokales storage/app Verzeichnis nicht gefunden: {storageRoot}");

            string manifestPath = Path.Combine(storageRoot, "private", "shop-asset-manifest.json");
            if (!File.Exists(manifestPath))
                throw new FileNotFoundException("shop-asset-manifest.json fehlt. Bitte zuerst 'php artisan system:shop-asset-manifest --write' ausführen.");

            if (HasReparsePointInAncestry(manifestPath))
                throw new UnauthorizedAccessException("Reparse-Point / Symlink auf shop-asset-manifest.json blockiert.");

            var validatedEntries = new List<(string RelPath, string LocalPath, long Size, string Sha256Lower)>();
            using (var doc = JsonDocument.Parse(await File.ReadAllTextAsync(manifestPath), new JsonDocumentOptions { MaxDepth = 12 }))
            {
                var root = doc.RootElement;
                string scope = root.TryGetProperty("scope", out var sp) ? (sp.GetString() ?? "") : "";
                if (!string.Equals(scope, "pure_shop_assets_only", StringComparison.Ordinal))
                    throw new UnauthorizedAccessException("Ungültiger Manifest-Scope (erwartet: pure_shop_assets_only).");

                if (!root.TryGetProperty("files", out var filesArr) || filesArr.ValueKind != JsonValueKind.Array)
                    throw new InvalidOperationException("Manifest enthält kein gültiges 'files'-Array.");

                if (filesArr.GetArrayLength() > 2500)
                    throw new InvalidOperationException("Manifest überschreitet das Sicherheitslimit von 2.500 Shop-Assets.");

                foreach (var item in filesArr.EnumerateArray())
                {
                    string rawRel = item.TryGetProperty("rel_path", out var rp) ? (rp.GetString() ?? "") : "";
                    string safeRel = ValidatePureShopAssetRelativePath(rawRel);

                    string localFile = Path.Combine(storageRoot, safeRel.Replace('/', Path.DirectorySeparatorChar));
                    if (!File.Exists(localFile))
                        continue;

                    if (HasReparsePointInAncestry(localFile))
                        throw new UnauthorizedAccessException($"Symlink/Junction in Shop-Asset blockiert: {safeRel}");

                    var fi = new FileInfo(localFile);
                    if (fi.Length <= 0 || fi.Length > 60L * 1024L * 1024L)
                        throw new UnauthorizedAccessException($"Dateigröße außerhalb des zulässigen Shop-Asset-Rahmens (1B..60MB): {safeRel}");

                    await using var fs = new FileStream(localFile, FileMode.Open, FileAccess.Read, FileShare.Read);
                    byte[] hashBytes = await SHA256.HashDataAsync(fs);
                    string actualSha256 = Convert.ToHexString(hashBytes).ToLowerInvariant();

                    validatedEntries.Add((safeRel, localFile, fi.Length, actualSha256));
                }
            }

            using var creds = ResolveProfileInternal(profileName, StageWebRemoteDir, forceKeyOnlyNoPasswordDecrypt: true)
                ?? throw new InvalidOperationException($"Profil '{profileName}' wurde nicht gefunden.");

            ValidateStageOnlyTarget(creds.Host, creds.RemoteDirectory, allowWorkerStage: false);

            if (!HasAnySha256PinForHost(creds.Host, creds.Port))
            {
                AppendAuditLog("stage_sync_shop_assets", $"{creds.Host}:{creds.Port}", "DENY:unpinned_host_key");
                throw new UnauthorizedAccessException($"Host '{creds.Host}:{creds.Port}' ist nicht per SHA-256 gepinnt.");
            }

            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();
            string remoteStorageApp = $"{StageWebRemoteDir}/storage/app";

            return await Task.Run(() =>
            {
                var authMethods = new List<AuthenticationMethod>();
                PrivateKeyFile? keyFile = null;
                try
                {
                    if (!string.IsNullOrWhiteSpace(creds.PrivateKeyPath) && File.Exists(creds.PrivateKeyPath))
                    {
                        keyFile = new PrivateKeyFile(creds.PrivateKeyPath);
                        authMethods.Add(new PrivateKeyAuthenticationMethod(creds.Username, keyFile));
                    }
                    if (keyFile == null || authMethods.Count == 0)
                    {
                        AppendAuditLog("stage_sync_shop_assets", creds.Name, "DENY:stage_requires_ed25519_key");
                        throw new UnauthorizedAccessException(
                            "Stage-Shop-Asset-Sync erzwingt reine Ed25519-SSH-Key-Authentifizierung (Passwort-Fallback ist aus Sicherheitsgründen hart deaktiviert).");
                    }

                    bool VerifyPinnedKey(byte[] hostKeyBytes, string hostKeyName)
                    {
                        string actualFp = "SHA256:" + Convert.ToBase64String(SHA256.HashData(hostKeyBytes)).TrimEnd('=');
                        byte[] actualBytes = Encoding.ASCII.GetBytes(actualFp);

                        var allowedFps = new List<string>();
                        string exactKey = $"{creds.Host.ToLowerInvariant()}:{creds.Port}:{hostKeyName}";
                        if (fwPinned.TryGetValue(exactKey, out string? fwExact)) allowedFps.Add(fwExact);
                        if (fwPinned.TryGetValue($"{creds.Host}:{creds.Port}", out string? fwHostPort)) allowedFps.Add(fwHostPort);
                        allowedFps.AddRange(FindOpenSshPinnedFingerprintsForHost(creds.Host, creds.Port, hostKeyName));

                        foreach (string candidateFp in allowedFps)
                        {
                            byte[] expectedBytes = Encoding.ASCII.GetBytes(candidateFp.Trim());
                            if (actualBytes.Length == expectedBytes.Length &&
                                CryptographicOperations.FixedTimeEquals(actualBytes, expectedBytes))
                            {
                                return true;
                            }
                        }
                        return false;
                    }

                    var connInfo = new ConnectionInfo(creds.Host, creds.Port, creds.Username, authMethods.ToArray())
                    {
                        Timeout = TimeSpan.FromSeconds(15)
                    };

                    // 1. Schneller Remote-SHA256-Abgleich über SSH in 80er-Batches
                    var remoteHashes = new Dictionary<string, string>(StringComparer.Ordinal);
                    using (var ssh = new SshClient(connInfo))
                    {
                        bool sshPinned = false;
                        ssh.HostKeyReceived += (s, e) => { sshPinned = VerifyPinnedKey(e.HostKey, e.HostKeyName); e.CanTrust = sshPinned; };
                        ssh.Connect();
                        if (!sshPinned || !ssh.IsConnected)
                            throw new UnauthorizedAccessException("SSH Host-Key-Prüfung für Stage-Delta fehlgeschlagen.");

                        const int batchSize = 80;
                        for (int i = 0; i < validatedEntries.Count; i += batchSize)
                        {
                            var batch = validatedEntries.Skip(i).Take(batchSize).Select(x => $"'{x.RelPath}'");
                            string hashCmd = $"cd -- '{remoteStorageApp}' && sha256sum -- {string.Join(" ", batch)} 2>/dev/null || true";
                            using var cmd = ssh.CreateCommand(hashCmd);
                            cmd.CommandTimeout = TimeSpan.FromSeconds(30);
                            string outText = cmd.Execute() ?? "";
                            foreach (string line in outText.Split('\n', StringSplitOptions.RemoveEmptyEntries))
                            {
                                string trimmed = line.Trim();
                                if (trimmed.Length <= 66) continue;
                                int spaceIdx = trimmed.IndexOf(' ');
                                if (spaceIdx != 64) continue;
                                string hashHex = trimmed.Substring(0, 64).ToLowerInvariant();
                                string rel = trimmed.Substring(64).TrimStart(' ', '*');
                                remoteHashes[rel] = hashHex;
                            }
                        }
                        ssh.Disconnect();
                    }

                    var deltaList = validatedEntries
                        .Where(e => !remoteHashes.TryGetValue(e.RelPath, out string? rHash) || !string.Equals(rHash, e.Sha256Lower, StringComparison.Ordinal))
                        .ToList();

                    var result = new StageShopAssetSyncResultDto
                    {
                        Success = true,
                        DryRun = dryRun,
                        TargetHost = creds.Host,
                        TargetRemoteStorageDir = remoteStorageApp,
                        TotalManifestFiles = validatedEntries.Count,
                        UnchangedOnStageCount = validatedEntries.Count - deltaList.Count,
                        UploadedCount = 0,
                        UploadedBytes = 0
                    };

                    if (deltaList.Count == 0 || dryRun)
                    {
                        if (dryRun)
                        {
                            result.UploadedCount = deltaList.Count;
                            result.UploadedBytes = deltaList.Sum(x => x.Size);
                            result.UploadedFiles = deltaList.Select(x => x.RelPath).ToList();
                        }
                        AppendAuditLog("stage_sync_shop_assets", $"delta={deltaList.Count},dryRun={dryRun}", "ALLOW");
                        return result;
                    }

                    // 2. Atomarer SFTP-Upload ausschließlich der geänderten/neuen Shop-Dateien
                    using (var sftp = new SftpClient(connInfo))
                    {
                        bool sftpPinned = false;
                        sftp.HostKeyReceived += (s, e) => { sftpPinned = VerifyPinnedKey(e.HostKey, e.HostKeyName); e.CanTrust = sftpPinned; };
                        sftp.Connect();
                        if (!sftpPinned || !sftp.IsConnected)
                            throw new UnauthorizedAccessException("SFTP Host-Key-Prüfung für Stage-Sync fehlgeschlagen.");

                        var verifiedDirs = new HashSet<string>(StringComparer.Ordinal);
                        void EnsureSafeRemoteDir(string fullRemoteDir)
                        {
                            if (verifiedDirs.Contains(fullRemoteDir)) return;
                            string[] parts = fullRemoteDir.Trim('/').Split('/');
                            string current = "";
                            foreach (string part in parts)
                            {
                                current += "/" + part;
                                if (!current.StartsWith(StageWebRemoteDir, StringComparison.Ordinal))
                                    continue;

                                if (sftp.Exists(current))
                                {
                                    var attr = sftp.GetAttributes(current);
                                    if (attr.IsSymbolicLink)
                                        throw new UnauthorizedAccessException($"Remote-Symlink-Verzeichnis verweigert: {current}");
                                }
                                else
                                {
                                    sftp.CreateDirectory(current);
                                }
                            }
                            verifiedDirs.Add(fullRemoteDir);
                        }

                        foreach (var item in deltaList)
                        {
                            string finalRemote = $"{remoteStorageApp}/{item.RelPath}";
                            int lastSlash = finalRemote.LastIndexOf('/');
                            string parentDir = finalRemote.Substring(0, lastSlash);
                            EnsureSafeRemoteDir(parentDir);

                            string tmpRemote = $"{finalRemote}.fwtmp_{Guid.NewGuid():N}";
                            using (var fs = new FileStream(item.LocalPath, FileMode.Open, FileAccess.Read, FileShare.Read))
                            {
                                sftp.UploadFile(fs, tmpRemote, canOverride: true);
                            }

                            if (sftp.Exists(finalRemote))
                            {
                                var existingAttr = sftp.GetAttributes(finalRemote);
                                if (existingAttr.IsSymbolicLink)
                                {
                                    try { sftp.DeleteFile(tmpRemote); } catch { }
                                    throw new UnauthorizedAccessException($"Überschreiben eines Remote-Symlinks verweigert: {finalRemote}");
                                }
                                sftp.DeleteFile(finalRemote);
                            }

                            sftp.RenameFile(tmpRemote, finalRemote);
                            result.UploadedCount++;
                            result.UploadedBytes += item.Size;
                            result.UploadedFiles.Add(item.RelPath);
                        }

                        sftp.Disconnect();
                    }

                    AppendAuditLog("stage_sync_shop_assets", $"uploaded={result.UploadedCount},bytes={result.UploadedBytes}", "ALLOW");
                    return result;
                }
                finally
                {
                    keyFile?.Dispose();
                }
            });
        }

        public const string PinnedFunkenwerkApkCertSha256 = "e4c85cdd79648fa19430c91b47e1db036544d60d2fa6136a0c5106300c849064";
        public const string DefaultLocalReleaseApkPath = @"C:\Users\konta\AndroidStudioProjects\seelenfunke-android\app\build\outputs\apk\release\app-release.apk";
        private static readonly Regex SafeApkVersionNameRegex = new(@"^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(?:-[A-Za-z0-9._-]+)?$", RegexOptions.Compiled);

        public static (long FileSize, string ApkSha256, string CertSha256) VerifyLocalSignedFunkenwerkApk(string apkPath)
        {
            if (string.IsNullOrWhiteSpace(apkPath) || apkPath.Contains("..", StringComparison.Ordinal))
                throw new UnauthorizedAccessException("Ungültiger APK-Dateipfad blockiert.");

            string fullPath = Path.GetFullPath(apkPath);
            if (!string.Equals(Path.GetExtension(fullPath), ".apk", StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("Nur echte .apk-Release-Pakete sind zulässig.");

            if (!File.Exists(fullPath))
                throw new FileNotFoundException($"Lokales Release-APK nicht gefunden: {fullPath}");

            if (HasReparsePointInAncestry(fullPath))
                throw new UnauthorizedAccessException("Symlink oder Reparse-Point im APK-Pfad verweigert.");

            var fi = new FileInfo(fullPath);
            if (fi.Length < 512L * 1024L || fi.Length > 95L * 1024L * 1024L)
                throw new UnauthorizedAccessException($"APK-Größe außerhalb des erlaubten Sicherheitsrahmens (512 KB .. 95 MB): {fi.Length} Bytes.");

            using var fs = new FileStream(fullPath, FileMode.Open, FileAccess.Read, FileShare.Read);
            Span<byte> magic = stackalloc byte[4];
            if (fs.Read(magic) != 4 || magic[0] != 0x50 || magic[1] != 0x4B || magic[2] != 0x03 || magic[3] != 0x04)
                throw new UnauthorizedAccessException("Ungültiger ZIP/APK-Header (PK\\x03\\x04 fehlt).");

            fs.Position = 0;
            string apkSha256 = Convert.ToHexString(SHA256.HashData(fs)).ToLowerInvariant();

            int eocdWindow = (int)Math.Min(fi.Length, 65557L);
            byte[] tail = new byte[eocdWindow];
            fs.Position = fi.Length - eocdWindow;
            if (fs.Read(tail, 0, eocdWindow) != eocdWindow)
                throw new UnauthorizedAccessException("APK-EOCD konnte nicht gelesen werden.");

            int eocdPos = -1;
            for (int i = eocdWindow - 22; i >= 0; i--)
            {
                if (tail[i] == 0x50 && tail[i + 1] == 0x4B && tail[i + 2] == 0x05 && tail[i + 3] == 0x06)
                {
                    eocdPos = i;
                    break;
                }
            }
            if (eocdPos < 0)
                throw new UnauthorizedAccessException("Ungültiges ZIP-EOCD in APK.");

            uint cdOffset = BitConverter.ToUInt32(tail, eocdPos + 16);
            if (cdOffset < 32 || cdOffset >= fi.Length)
                throw new UnauthorizedAccessException("APK besitzt keinen gültigen V2/V3-Signaturblock.");

            byte[] footer = new byte[24];
            fs.Position = (long)cdOffset - 24L;
            if (fs.Read(footer, 0, 24) != 24 || Encoding.ASCII.GetString(footer, 8, 16) != "APK Sig Block 42")
                throw new UnauthorizedAccessException("APK Sig Block 42 fehlt (unsigniertes Paket blockiert).");

            ulong blockSizeFooter = BitConverter.ToUInt64(footer, 0);
            long blockStart = (long)cdOffset - 8L - (long)blockSizeFooter;
            if (blockSizeFooter < 32UL || blockSizeFooter > 16777216UL || blockStart < 0)
                throw new UnauthorizedAccessException("Ungültige Größe des APK-Signaturblocks.");

            byte[] header = new byte[8];
            fs.Position = blockStart;
            if (fs.Read(header, 0, 8) != 8 || BitConverter.ToUInt64(header, 0) != blockSizeFooter)
                throw new UnauthorizedAccessException("Inkonsistenter APK-Signaturblock.");

            int pairsLen = (int)(blockSizeFooter - 24UL);
            byte[] pairsBytes = new byte[pairsLen];
            if (fs.Read(pairsBytes, 0, pairsLen) != pairsLen)
                throw new UnauthorizedAccessException("APK-Signaturblock konnte nicht vollständig gelesen werden.");

            int pos = 0;
            while (pos + 12 <= pairsLen)
            {
                ulong pairLen = BitConverter.ToUInt64(pairsBytes, pos);
                pos += 8;
                if (pairLen < 4UL || (ulong)pos + pairLen > (ulong)pairsLen)
                    break;

                uint pairId = BitConverter.ToUInt32(pairsBytes, pos);
                int valOffset = pos + 4;
                int valLen = (int)(pairLen - 4UL);
                pos += (int)pairLen;

                if (pairId == 0x7109871au || pairId == 0xf05368c0u)
                {
                    if (VerifyV2OrV3BlockPinned(pairsBytes.AsSpan(valOffset, valLen), out string matchedCertHex))
                        return (fi.Length, apkSha256, matchedCertHex);
                }
            }

            throw new UnauthorizedAccessException(
                "Sicherheitsabbruch: APK ist nicht mit dem gepinnten 4096-Bit-RSA-Funkenwerk-Zertifikat signiert.");
        }

        private static bool VerifyV2OrV3BlockPinned(ReadOnlySpan<byte> blockVal, out string certHexLower)
        {
            certHexLower = "";
            if (blockVal.Length < 16) return false;

            uint signersLen = BitConverter.ToUInt32(blockVal.Slice(0, 4));
            if (signersLen < 12 || 4 + (int)signersLen > blockVal.Length) return false;
            var signers = blockVal.Slice(4, (int)signersLen);

            uint firstSignerLen = BitConverter.ToUInt32(signers.Slice(0, 4));
            if (firstSignerLen < 12 || 4 + (int)firstSignerLen > signers.Length) return false;
            var firstSigner = signers.Slice(4, (int)firstSignerLen);

            uint signedDataLen = BitConverter.ToUInt32(firstSigner.Slice(0, 4));
            if (signedDataLen < 8 || 4 + (int)signedDataLen + 4 > firstSigner.Length) return false;
            var signedData = firstSigner.Slice(4, (int)signedDataLen);

            uint digestsLen = BitConverter.ToUInt32(signedData.Slice(0, 4));
            int certsOffset = 4 + (int)digestsLen;
            if (certsOffset < 4 || certsOffset + 8 > signedData.Length) return false;

            uint certsLen = BitConverter.ToUInt32(signedData.Slice(certsOffset, 4));
            if (certsLen < 4 || certsOffset + 4 + (int)certsLen > signedData.Length) return false;
            var certsBuf = signedData.Slice(certsOffset + 4, (int)certsLen);

            uint firstCertLen = BitConverter.ToUInt32(certsBuf.Slice(0, 4));
            if (firstCertLen < 64 || 4 + (int)firstCertLen > certsBuf.Length) return false;
            byte[] firstCertDer = certsBuf.Slice(4, (int)firstCertLen).ToArray();

            string actualCertHex = Convert.ToHexString(SHA256.HashData(firstCertDer)).ToLowerInvariant();
            byte[] actualBytes = Encoding.ASCII.GetBytes(actualCertHex);
            byte[] expectedBytes = Encoding.ASCII.GetBytes(PinnedFunkenwerkApkCertSha256);
            if (!CryptographicOperations.FixedTimeEquals(actualBytes, expectedBytes))
                return false;

            using var cert = X509CertificateLoader.LoadCertificate(firstCertDer);
            using RSA? rsa = cert.GetRSAPublicKey();
            if (rsa == null) return false;

            int[] candidateSigOffsets = { 4 + (int)signedDataLen, 4 + (int)signedDataLen + 8 };
            byte[] signedDataBytes = signedData.ToArray();

            foreach (int sigsOffset in candidateSigOffsets)
            {
                if (sigsOffset < 4 || sigsOffset + 4 > firstSigner.Length) continue;
                uint sigsLen = BitConverter.ToUInt32(firstSigner.Slice(sigsOffset, 4));
                if (sigsLen < 12 || sigsOffset + 4 + (int)sigsLen > firstSigner.Length) continue;
                var sigsBuf = firstSigner.Slice(sigsOffset + 4, (int)sigsLen);

                int sigPos = 0;
                while (sigPos + 12 <= sigsBuf.Length)
                {
                    uint entryLen = BitConverter.ToUInt32(sigsBuf.Slice(sigPos, 4));
                    sigPos += 4;
                    if (entryLen < 8 || sigPos + (int)entryLen > sigsBuf.Length) break;
                    var entry = sigsBuf.Slice(sigPos, (int)entryLen);
                    sigPos += (int)entryLen;

                    uint sigAlgo = BitConverter.ToUInt32(entry.Slice(0, 4));
                    uint sigBytesLen = BitConverter.ToUInt32(entry.Slice(4, 4));
                    if (sigBytesLen < 64 || 8 + (int)sigBytesLen > entry.Length) continue;
                    byte[] sigBytes = entry.Slice(8, (int)sigBytesLen).ToArray();

                    HashAlgorithmName? hashAlgo = sigAlgo switch
                    {
                        0x0103u => HashAlgorithmName.SHA256,
                        0x0104u => HashAlgorithmName.SHA512,
                        _ => null
                    };
                    if (hashAlgo.HasValue && rsa.VerifyData(signedDataBytes, sigBytes, hashAlgo.Value, RSASignaturePadding.Pkcs1))
                    {
                        certHexLower = actualCertHex;
                        return true;
                    }
                }
            }

            return false;
        }

        public static async Task<StageAndroidApkPublishResultDto> PublishStageAndroidApkAsync(
            string profileName = "Mittwald",
            string? customApkPath = null,
            int versionCode = 0,
            string versionName = "",
            string releaseNotes = "",
            string? customRemoteStageDirForTest = null)
        {
            EnforceRateLimit("stage_publish_android_apk", isSshCall: true);

            string targetRemoteDir = string.IsNullOrWhiteSpace(customRemoteStageDirForTest)
                ? StageWebRemoteDir
                : customRemoteStageDirForTest.Trim();

            // 1. Hard Live-Air-Gap Guard BEFORE touching files, vaults, or opening sockets
            ValidateStageOnlyTarget(StageAllowedHost, targetRemoteDir, allowWorkerStage: false);

            if (versionCode < 1 || versionCode > 999999)
                throw new UnauthorizedAccessException($"Ungültiger versionCode ({versionCode}).");

            string cleanVersionName = (versionName ?? "").Trim();
            if (!SafeApkVersionNameRegex.IsMatch(cleanVersionName))
                throw new UnauthorizedAccessException($"Ungültiges versionName-Format ('{cleanVersionName}').");

            string cleanNotes = (releaseNotes ?? "").Trim();
            if (cleanNotes.Length > 500)
                cleanNotes = cleanNotes.Substring(0, 500);

            // 2. Cryptographic client-side verification of the signed Funkenwerk Release APK BEFORE opening network sockets
            string localApkPath = string.IsNullOrWhiteSpace(customApkPath) ? DefaultLocalReleaseApkPath : customApkPath;
            var (apkSize, apkSha256, certSha256) = VerifyLocalSignedFunkenwerkApk(localApkPath);

            // 3. Resolve Stage SSH/SFTP profile in strict Ed25519 Key-Only mode (zero vault password decryption)
            using var creds = ResolveProfileInternal(profileName, targetRemoteDir, forceKeyOnlyNoPasswordDecrypt: true)
                ?? throw new InvalidOperationException($"Profil '{profileName}' wurde nicht gefunden.");

            ValidateStageOnlyTarget(creds.Host, creds.RemoteDirectory, allowWorkerStage: false);

            if (!HasAnySha256PinForHost(creds.Host, creds.Port))
            {
                AppendAuditLog("stage_publish_android_apk", $"{creds.Host}:{creds.Port}", "DENY:unpinned_host_key");
                throw new UnauthorizedAccessException($"Host '{creds.Host}:{creds.Port}' ist nicht per SHA-256 gepinnt.");
            }

            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();
            string remoteVaultDir = $"{StageWebRemoteDir}/storage/app/private/releases/android";
            string remoteStageTmpName = $"_ota_stage_{Guid.NewGuid():N}.apk.tmp";
            string remoteStageTmpFull = $"{remoteVaultDir}/{remoteStageTmpName}";

            string payloadJson = JsonSerializer.Serialize(new
            {
                tmp_file = remoteStageTmpFull,
                version_code = versionCode,
                version_name = cleanVersionName,
                sha256 = apkSha256,
                release_notes = cleanNotes
            });
            string payloadB64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(payloadJson));

            return await Task.Run(() =>
            {
                var authMethods = new List<AuthenticationMethod>();
                PrivateKeyFile? keyFile = null;
                try
                {
                    if (!string.IsNullOrWhiteSpace(creds.PrivateKeyPath) && File.Exists(creds.PrivateKeyPath))
                    {
                        keyFile = new PrivateKeyFile(creds.PrivateKeyPath);
                        authMethods.Add(new PrivateKeyAuthenticationMethod(creds.Username, keyFile));
                    }
                    if (keyFile == null || authMethods.Count == 0)
                    {
                        AppendAuditLog("stage_publish_android_apk", creds.Name, "DENY:stage_requires_ed25519_key");
                        throw new UnauthorizedAccessException(
                            "Stage-APK-Publish erzwingt reine Ed25519-SSH-Key-Authentifizierung (Passwort-Fallback ist hart deaktiviert).");
                    }

                    bool VerifyPinnedKey(byte[] hostKeyBytes, string hostKeyName)
                    {
                        string actualFp = "SHA256:" + Convert.ToBase64String(SHA256.HashData(hostKeyBytes)).TrimEnd('=');
                        byte[] actualBytes = Encoding.ASCII.GetBytes(actualFp);

                        var allowedFps = new List<string>();
                        string exactKey = $"{creds.Host.ToLowerInvariant()}:{creds.Port}:{hostKeyName}";
                        if (fwPinned.TryGetValue(exactKey, out string? fwExact)) allowedFps.Add(fwExact);
                        if (fwPinned.TryGetValue($"{creds.Host}:{creds.Port}", out string? fwHostPort)) allowedFps.Add(fwHostPort);
                        allowedFps.AddRange(FindOpenSshPinnedFingerprintsForHost(creds.Host, creds.Port, hostKeyName));

                        foreach (string candidateFp in allowedFps)
                        {
                            byte[] expectedBytes = Encoding.ASCII.GetBytes(candidateFp.Trim());
                            if (actualBytes.Length == expectedBytes.Length &&
                                CryptographicOperations.FixedTimeEquals(actualBytes, expectedBytes))
                            {
                                return true;
                            }
                        }
                        return false;
                    }

                    var connInfo = new ConnectionInfo(creds.Host, creds.Port, creds.Username, authMethods.ToArray())
                    {
                        Timeout = TimeSpan.FromSeconds(20)
                    };

                    // 4. Upload verified APK via SFTP into private Stage vault temp file (symlink-guarded)
                    using (var sftp = new SftpClient(connInfo))
                    {
                        bool sftpPinned = false;
                        sftp.HostKeyReceived += (s, e) => { sftpPinned = VerifyPinnedKey(e.HostKey, e.HostKeyName); e.CanTrust = sftpPinned; };
                        sftp.Connect();
                        if (!sftpPinned || !sftp.IsConnected)
                            throw new UnauthorizedAccessException("SFTP Host-Key-Prüfung für Stage-APK-Publish fehlgeschlagen.");

                        string[] parts = remoteVaultDir.Trim('/').Split('/');
                        string current = "";
                        foreach (string part in parts)
                        {
                            current += "/" + part;
                            if (!current.StartsWith(StageWebRemoteDir, StringComparison.Ordinal))
                                continue;

                            if (sftp.Exists(current))
                            {
                                var attr = sftp.GetAttributes(current);
                                if (attr.IsSymbolicLink)
                                    throw new UnauthorizedAccessException($"Remote-Symlink-Verzeichnis verweigert: {current}");
                            }
                            else
                            {
                                sftp.CreateDirectory(current);
                            }
                        }

                        using (var fs = new FileStream(Path.GetFullPath(localApkPath), FileMode.Open, FileAccess.Read, FileShare.Read))
                        {
                            sftp.UploadFile(fs, remoteStageTmpFull, canOverride: true);
                        }

                        sftp.Disconnect();
                    }

                    // 5. Activate & server-side re-verify via AndroidReleaseService on Stage (strict APP_URL air-gap check)
                    string phpRunnerB64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(@"
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$appUrl = rtrim((string) config('app.url'), '/');
if ($appUrl !== 'https://stage.mein-seelenfunke.de') {
    fwrite(STDERR, 'LIVE-AIR-GAP SPERRE: APP_URL=' . $appUrl);
    exit(2);
}
$in = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$tmp = (string) $in['tmp_file'];
try {
    $svc = app(\App\Services\System\AndroidReleaseService::class);
    $manifest = $svc->storeVerifiedRelease(
        $tmp,
        (int) $in['version_code'],
        (string) $in['version_name'],
        (string) $in['sha256'],
        (string) ($in['release_notes'] ?? ''),
        true
    );
    @chmod(storage_path('app/private/releases'), 0750);
    @chmod(storage_path('app/private/releases/android'), 0750);
    echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
"));

                    string activateCmd = $"cd -- '{StageWebRemoteDir}' && php -r 'eval(base64_decode(\"{phpRunnerB64}\"));' -- '{payloadB64}'";
                    string remoteOut;
                    using (var ssh = new SshClient(connInfo))
                    {
                        bool sshPinned = false;
                        ssh.HostKeyReceived += (s, e) => { sshPinned = VerifyPinnedKey(e.HostKey, e.HostKeyName); e.CanTrust = sshPinned; };
                        ssh.Connect();
                        if (!sshPinned || !ssh.IsConnected)
                            throw new UnauthorizedAccessException("SSH Host-Key-Prüfung für Stage-APK-Aktivierung fehlgeschlagen.");

                        using var cmd = ssh.CreateCommand(activateCmd);
                        cmd.CommandTimeout = TimeSpan.FromSeconds(45);
                        string stdout = cmd.Execute() ?? "";
                        string stderr = cmd.Error ?? "";
                        int exitCode = cmd.ExitStatus ?? 0;

                        if (exitCode != 0)
                        {
                            // Best-effort cleanup of temp upload if PHP exited early before finally
                            try
                            {
                                using var cleanCmd = ssh.CreateCommand($"rm -f -- '{remoteStageTmpFull}'");
                                cleanCmd.Execute();
                            }
                            catch { }
                            ssh.Disconnect();
                            AppendAuditLog("stage_publish_android_apk", $"v{cleanVersionName}-b{versionCode}:exit={exitCode}", "ERROR");
                            throw new InvalidOperationException($"Stage-APK-Aktivierung fehlgeschlagen (Exit {exitCode}): {ScrubSecrets((stdout + "\n" + stderr).Trim())}");
                        }

                        ssh.Disconnect();
                        remoteOut = stdout.Trim();
                    }

                    AppendAuditLog("stage_publish_android_apk", $"v{cleanVersionName}-b{versionCode},sha={apkSha256.Substring(0, 12)}", "ALLOW");
                    return new StageAndroidApkPublishResultDto
                    {
                        Success = true,
                        TargetHost = creds.Host,
                        TargetRemoteVaultDir = remoteVaultDir,
                        VersionCode = versionCode,
                        VersionName = cleanVersionName,
                        ApkSha256 = apkSha256,
                        CertSha256 = certSha256,
                        FileSizeBytes = apkSize,
                        RemoteManifestJson = ScrubSecrets(remoteOut)
                    };
                }
                finally
                {
                    keyFile?.Dispose();
                }
            });
        }

        // =====================================================================
        // LAYER 8: SHA-256 HASH-CHAINED TAMPER-EVIDENT AUDIT LOG
        // =====================================================================
        public static void AppendAuditLog(string toolName, string targetSummary, string decision, string? customLogPath = null)
        {
            lock (AuditLock)
            {
                try
                {
                    string logFile = customLogPath ?? AuditLogFile;
                    bool isNewFile = !File.Exists(logFile);
                    string prevHash = "GENESIS";

                    if (!isNewFile)
                    {
                        string? lastLine = File.ReadLines(logFile).LastOrDefault(l => !string.IsNullOrWhiteSpace(l));
                        if (!string.IsNullOrWhiteSpace(lastLine))
                        {
                            using var doc = JsonDocument.Parse(lastLine, new JsonDocumentOptions { MaxDepth = 8 });
                            if (doc.RootElement.TryGetProperty("hash", out var hp))
                                prevHash = hp.GetString() ?? "GENESIS";
                        }
                    }

                    string ts = DateTime.UtcNow.ToString("O");
                    string safeTarget = ScrubSecrets(targetSummary);
                    if (safeTarget.Length > 180) safeTarget = safeTarget.Substring(0, 180);

                    string canonical = $"{prevHash}|{ts}|{toolName}|{safeTarget}|{decision}";
                    string entryHash = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(canonical)));

                    string jsonLine = JsonSerializer.Serialize(new
                    {
                        ts,
                        tool = toolName,
                        target = safeTarget,
                        decision,
                        prevHash,
                        hash = entryHash
                    });

                    File.AppendAllText(logFile, jsonLine + "\n", new UTF8Encoding(false));
                    if (isNewFile && customLogPath == null)
                    {
                        LockDownFileAcl(logFile);
                    }
                }
                catch { }
            }
        }

        public static (bool IsValid, int Count) VerifyAuditChain(string? customLogPath = null)
        {
            lock (AuditLock)
            {
                string logFile = customLogPath ?? AuditLogFile;
                if (!File.Exists(logFile))
                    return (true, 0);

                try
                {
                    string expectedPrev = "GENESIS";
                    int count = 0;
                    foreach (string line in File.ReadLines(logFile))
                    {
                        if (string.IsNullOrWhiteSpace(line)) continue;
                        using var doc = JsonDocument.Parse(line, new JsonDocumentOptions { MaxDepth = 8 });
                        var r = doc.RootElement;

                        string ts = r.GetProperty("ts").GetString() ?? "";
                        string tool = r.GetProperty("tool").GetString() ?? "";
                        string target = r.GetProperty("target").GetString() ?? "";
                        string decision = r.GetProperty("decision").GetString() ?? "";
                        string prevHash = r.GetProperty("prevHash").GetString() ?? "";
                        string hash = r.GetProperty("hash").GetString() ?? "";

                        if (!string.Equals(prevHash, expectedPrev, StringComparison.Ordinal))
                            return (false, count);

                        string canonical = $"{prevHash}|{ts}|{tool}|{target}|{decision}";
                        string computed = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(canonical)));

                        byte[] compBytes = Encoding.ASCII.GetBytes(computed);
                        byte[] hashBytes = Encoding.ASCII.GetBytes(hash);
                        if (compBytes.Length != hashBytes.Length ||
                            !CryptographicOperations.FixedTimeEquals(compBytes, hashBytes))
                        {
                            return (false, count);
                        }

                        expectedPrev = hash;
                        count++;
                    }
                    return (true, count);
                }
                catch
                {
                    return (false, 0);
                }
            }
        }

        internal static (bool ProfileFound, bool HasEd25519Key, bool PasswordDecryptedInRam) InspectStageKeyOnlyModeForSelfTest(string profileName)
        {
            using var creds = ResolveProfileInternal(profileName, StageWebRemoteDir, forceKeyOnlyNoPasswordDecrypt: true);
            if (creds == null)
                return (false, false, false);

            bool hasKey = !string.IsNullOrWhiteSpace(creds.PrivateKeyPath) && File.Exists(creds.PrivateKeyPath);
            bool hasPwd = creds.PasswordUtf8 != null && creds.PasswordUtf8.Length > 0;
            return (true, hasKey, hasPwd);
        }

        internal static (bool ProfileFound, string ResolvedUser, string ResolvedRemoteDir, bool PasswordDecryptedInRam) InspectNodeBridgeProfileForSelfTest()
        {
            using var creds = ResolveProfileInternal("Mittwald Node.js App", overrideRemoteDir: null, forceKeyOnlyNoPasswordDecrypt: true);
            if (creds == null)
                return (false, "", "", false);

            bool hasPwd = creds.PasswordUtf8 != null && creds.PasswordUtf8.Length > 0;
            return (true, creds.Username, creds.RemoteDirectory, hasPwd);
        }

        // =====================================================================
        // AG DIRECT BRIDGE (ANDROID -> STAGE -> LOCAL ANTIGRAVITY MASTER-CHAT)
        // =====================================================================
        public const string DefaultMasterConversationId = "a75e6db9-d026-4955-97c0-6e8695d6193d";
        public const int DefaultAgPollIntervalSeconds = 20;
        public const int MinAgPollIntervalSeconds = 10;
        public const long MaxAgAttachmentBytes = 20L * 1024L * 1024L;
        public const string DefaultLocalDirectInboxPath = @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\storage\app\private\antigravity_direct_inbox.json";

        private static readonly Regex SafeAgConversationIdRegex = new(@"^[A-Za-z0-9][A-Za-z0-9_\-]{2,79}$", RegexOptions.Compiled);
        private static readonly Regex SafeAgInboxSingleIdRegex = new(@"^[A-Za-z0-9][A-Za-z0-9_\-]{2,79}$", RegexOptions.Compiled);
        private static readonly Regex SafeAgInboxMarkDeliveredIdsRegex = new(@"^[A-Za-z0-9][A-Za-z0-9_,\-]{2,119}$", RegexOptions.Compiled);
        private static readonly Regex SafeBase64PayloadRegex = new(@"^[A-Za-z0-9+/=]*$", RegexOptions.Compiled);
        private static readonly Regex ConversationUuidExtractRegex = new(@"\b[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}\b", RegexOptions.Compiled);

        public static readonly IReadOnlyList<string> FixedAgChannels = new[]
        {
            "leitung",
            "shop",
            "android",
            "minipc",
            "buchhaltung"
        };

        public static string NormalizeAgChannel(string? rawChannel)
        {
            string norm = (rawChannel ?? "").Trim().ToLowerInvariant();
            return norm switch
            {
                "leitung" => "leitung",
                "shop" => "shop",
                "android" => "android",
                "minipc" or "mini-pc" => "minipc",
                "buchhaltung" => "buchhaltung",
                "new" => "new",
                _ => "leitung"
            };
        }

        public static string ValidateFixedAgDepartmentChannel(string? rawChannel)
        {
            string clean = (rawChannel ?? "").Trim().ToLowerInvariant();
            if (clean == "mini-pc") clean = "minipc";
            if (!FixedAgChannels.Contains(clean, StringComparer.Ordinal))
            {
                throw new ArgumentException(
                    $"Ungültiger Abteilungs-Kanal '{ScrubSecrets(rawChannel)}'. Erlaubte feste Kanäle: leitung, shop, android, minipc, buchhaltung.");
            }
            return clean;
        }

        public static string GetAgChannelDisplayTitle(string? channel)
        {
            return NormalizeAgChannel(channel) switch
            {
                "shop" => "Shop & Konfigurator",
                "android" => "Android & Funkenwerk",
                "minipc" => "Mini-PC & Laser",
                "buchhaltung" => "Buchhaltung & Marketing",
                "new" => "Einzel-Chat",
                _ => "Leitung"
            };
        }

        public static string? GetAgChannelScopeHint(string? channel)
        {
            return NormalizeAgChannel(channel) switch
            {
                "shop" => "Dateibereichs-Fokus (Dateikonflikt-Schutz): app/, resources/, routes/, database/ (ohne AccountingSeeder.php), tests/.",
                "android" => "Dateibereichs-Fokus (Dateikonflikt-Schutz): android-app/ + Mirror C:\\Users\\konta\\AndroidStudioProjects\\seelenfunke-android (sowie ggf. funkenwerk/).",
                "minipc" => "Dateibereichs-Fokus (Dateikonflikt-Schutz): funkenwerk-os-linux/ & Manufaktur Mini-PC (192.168.188.151).",
                "buchhaltung" => "Dateibereichs-Fokus (Dateikonflikt-Schutz): database/seeders/AccountingSeeder.php, storage/app/buchhaltung/ (receipts/ & contracts/), Marketing & Kalkulation.",
                _ => null
            };
        }

        public static string FunkenwerkSharedAppDataDir => !string.IsNullOrWhiteSpace(_vaultDirOverride)
            ? _vaultDirOverride!
            : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Funkenwerk");

        public static string AgDirectBridgeConfigPath => Path.Combine(FunkenwerkSharedAppDataDir, "ag_direct_bridge_config.json");

        public static string AgDirectMediaDir => Path.Combine(FunkenwerkSharedAppDataDir, "AgDirectMedia");

        public static string SentinelLastNotifiedPath => Path.Combine(FunkenwerkSharedAppDataDir, "sentinel_last_notified.json");

        public static string? LoadSentinelLastNotifiedHash(string? customPath = null)
        {
            string path = string.IsNullOrWhiteSpace(customPath) ? SentinelLastNotifiedPath : Path.GetFullPath(customPath);
            if (!File.Exists(path)) return null;
            try
            {
                if (HasReparsePointInAncestry(path)) return null;
                using var doc = JsonDocument.Parse(File.ReadAllText(path, Encoding.UTF8), new JsonDocumentOptions { MaxDepth = 8 });
                return doc.RootElement.TryGetProperty("state_hash", out var hp) ? hp.GetString() : null;
            }
            catch
            {
                return null;
            }
        }

        public static void SaveSentinelLastNotifiedHash(string stateHash, string conversationId, string? customPath = null)
        {
            string safeConvId = ValidateAgConversationId(conversationId);
            string cleanHash = (stateHash ?? "").Trim();
            if (string.IsNullOrWhiteSpace(cleanHash) || cleanHash.Length > 128)
                throw new ArgumentException("Ungültiger Sentinel-Deduplizierungs-Hash.");

            string path = string.IsNullOrWhiteSpace(customPath) ? SentinelLastNotifiedPath : Path.GetFullPath(customPath);
            string dir = Path.GetDirectoryName(path) ?? throw new InvalidOperationException("Ungültiges Verzeichnis für sentinel_last_notified.json.");
            if (!Directory.Exists(dir))
            {
                Directory.CreateDirectory(dir);
            }
            LockDownDirectoryAcl(dir);
            RejectReparsePointsInHierarchy(path);

            string json = JsonSerializer.Serialize(new
            {
                state_hash = cleanHash,
                conversation_id = safeConvId,
                notified_at_utc = DateTimeOffset.UtcNow.ToString("O")
            }, new JsonSerializerOptions { WriteIndented = true });

            string tmpPath = Path.Combine(dir, $".sentlast_{Convert.ToHexString(RandomNumberGenerator.GetBytes(8)).ToLowerInvariant()}.tmp");
            try
            {
                File.WriteAllText(tmpPath, json, new UTF8Encoding(false));
                RejectReparsePointsInHierarchy(path);
                File.Move(tmpPath, path, overwrite: true);
                LockDownFileAcl(path);
            }
            finally
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
            }
        }

        public static void ClearSentinelLastNotifiedHash(string? customPath = null)
        {
            string path = string.IsNullOrWhiteSpace(customPath) ? SentinelLastNotifiedPath : Path.GetFullPath(customPath);
            try
            {
                if (File.Exists(path) && !HasReparsePointInAncestry(path))
                {
                    File.Delete(path);
                }
            }
            catch { }
        }

        public static string ValidateAgConversationId(string? rawConversationId)
        {
            string clean = (rawConversationId ?? "").Trim();
            if (string.IsNullOrWhiteSpace(clean) || !SafeAgConversationIdRegex.IsMatch(clean) || clean.Contains("--", StringComparison.Ordinal))
                throw new UnauthorizedAccessException("Ungültige Master-Chat Konversations-ID (erwartet GUID oder sichere ID ohne Sonderzeichen).");
            return clean;
        }

        public static string ValidateAgInboxSingleId(string? rawId)
        {
            string clean = (rawId ?? "").Trim();
            if (string.IsNullOrWhiteSpace(clean) || !SafeAgInboxSingleIdRegex.IsMatch(clean) || clean.Contains("--", StringComparison.Ordinal))
                throw new UnauthorizedAccessException($"Ungültige AG-Inbox-ID blockiert ('{ScrubSecrets(clean)}').");
            return clean;
        }

        public static string ValidateAgInboxMarkDeliveredIds(string? rawIds)
        {
            string clean = (rawIds ?? "").Trim();
            if (string.IsNullOrWhiteSpace(clean)
                || !SafeAgInboxMarkDeliveredIdsRegex.IsMatch(clean)
                || clean.Contains("--", StringComparison.Ordinal)
                || clean.Contains(",,", StringComparison.Ordinal)
                || clean.EndsWith(",", StringComparison.Ordinal))
            {
                throw new UnauthorizedAccessException($"Ungültige AG-Inbox-ID-Liste blockiert ('{ScrubSecrets(clean)}').");
            }

            foreach (string part in clean.Split(',', StringSplitOptions.RemoveEmptyEntries))
            {
                ValidateAgInboxSingleId(part);
            }
            return clean;
        }

        public static AgDirectBridgeConfigDto LoadAgBridgeConfig(string? customConfigPath = null)
        {
            string path = string.IsNullOrWhiteSpace(customConfigPath) ? AgDirectBridgeConfigPath : Path.GetFullPath(customConfigPath);
            if (!File.Exists(path))
            {
                return new AgDirectBridgeConfigDto
                {
                    MasterConversationId = DefaultMasterConversationId,
                    PollIntervalSeconds = DefaultAgPollIntervalSeconds,
                    ChannelConversationIds = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
                    {
                        ["leitung"] = DefaultMasterConversationId
                    }
                };
            }

            if (HasReparsePointInAncestry(path))
                throw new UnauthorizedAccessException("Symlink/Reparse-Point in ag_direct_bridge_config.json blockiert.");

            string json = File.ReadAllText(path, Encoding.UTF8);
            var parsed = JsonSerializer.Deserialize<AgDirectBridgeConfigDto>(json, new JsonSerializerOptions
            {
                PropertyNameCaseInsensitive = false,
                MaxDepth = 8
            }) ?? new AgDirectBridgeConfigDto();

            string safeConvId = string.IsNullOrWhiteSpace(parsed.MasterConversationId)
                ? DefaultMasterConversationId
                : ValidateAgConversationId(parsed.MasterConversationId);

            int safePoll = parsed.PollIntervalSeconds <= 0
                ? DefaultAgPollIntervalSeconds
                : Math.Clamp(parsed.PollIntervalSeconds, MinAgPollIntervalSeconds, 3600);

            var channelMap = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            if (parsed.ChannelConversationIds != null)
            {
                foreach (var kv in parsed.ChannelConversationIds)
                {
                    string normKey = NormalizeAgChannel(kv.Key);
                    if (normKey == "new" || normKey == "leitung" || string.IsNullOrWhiteSpace(kv.Value))
                        continue;

                    try
                    {
                        channelMap[normKey] = ValidateAgConversationId(kv.Value);
                    }
                    catch { }
                }
            }

            // "leitung" mirrors MasterConversationId at all times
            channelMap["leitung"] = safeConvId;

            return new AgDirectBridgeConfigDto
            {
                MasterConversationId = safeConvId,
                PollIntervalSeconds = safePoll,
                ChannelConversationIds = channelMap
            };
        }

        public static AgDirectBridgeConfigDto SaveAgBridgeConfig(AgDirectBridgeConfigDto config, string? customConfigPath = null)
        {
            if (config == null) throw new ArgumentNullException(nameof(config));

            string safeConvId = ValidateAgConversationId(config.MasterConversationId);
            int safePoll = config.PollIntervalSeconds <= 0
                ? DefaultAgPollIntervalSeconds
                : Math.Clamp(config.PollIntervalSeconds, MinAgPollIntervalSeconds, 3600);

            var normalizedChannels = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            if (config.ChannelConversationIds != null)
            {
                foreach (var kv in config.ChannelConversationIds)
                {
                    if (string.IsNullOrWhiteSpace(kv.Key) || string.IsNullOrWhiteSpace(kv.Value))
                        continue;

                    string normKey = ValidateFixedAgDepartmentChannel(kv.Key);
                    if (normKey == "leitung")
                        continue;

                    normalizedChannels[normKey] = ValidateAgConversationId(kv.Value);
                }
            }

            // "leitung" always mirrors MasterConversationId
            normalizedChannels["leitung"] = safeConvId;

            var normalized = new AgDirectBridgeConfigDto
            {
                MasterConversationId = safeConvId,
                PollIntervalSeconds = safePoll,
                ChannelConversationIds = normalizedChannels
            };

            string path = string.IsNullOrWhiteSpace(customConfigPath) ? AgDirectBridgeConfigPath : Path.GetFullPath(customConfigPath);
            string dir = Path.GetDirectoryName(path) ?? throw new InvalidOperationException("Ungültiges Konfigurationsverzeichnis.");
            if (!Directory.Exists(dir))
            {
                Directory.CreateDirectory(dir);
            }
            LockDownDirectoryAcl(dir);
            RejectReparsePointsInHierarchy(path);

            string json = JsonSerializer.Serialize(normalized, new JsonSerializerOptions { WriteIndented = true });
            string tmpPath = Path.Combine(dir, $".agcfg_{Convert.ToHexString(RandomNumberGenerator.GetBytes(8)).ToLowerInvariant()}.tmp");
            try
            {
                File.WriteAllText(tmpPath, json, new UTF8Encoding(false));
                RejectReparsePointsInHierarchy(path);
                File.Move(tmpPath, path, overwrite: true);
                LockDownFileAcl(path);
            }
            finally
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
            }

            AppendAuditLog("ag_bridge_config", $"conv={safeConvId},channels={normalizedChannels.Count},poll={safePoll}s", "ALLOW");
            return normalized;
        }

        public static string SaveAgDirectAttachmentSafe(
            string itemId,
            string? attachmentName,
            string? attachmentMime,
            string attachmentBase64,
            string? customMediaDir = null)
        {
            string safeId = ValidateAgInboxSingleId(itemId);
            if (string.IsNullOrWhiteSpace(attachmentBase64))
                throw new ArgumentException("Leerer Base64-Bildanhang.");

            if (!string.IsNullOrWhiteSpace(attachmentName))
            {
                string rawExt = Path.GetExtension(attachmentName.Trim()).ToLowerInvariant();
                if (!string.IsNullOrEmpty(rawExt) && rawExt is not ".png" and not ".jpg" and not ".jpeg" and not ".webp")
                {
                    throw new UnauthorizedAccessException($"Nicht erlaubte Anhang-Dateiendung ('{rawExt}'). Nur .png, .jpg und .webp sind freigegeben.");
                }
            }

            if (!string.IsNullOrWhiteSpace(attachmentMime))
            {
                string normMime = attachmentMime.Trim().ToLowerInvariant();
                int semi = normMime.IndexOf(';');
                if (semi >= 0) normMime = normMime.Substring(0, semi).Trim();
                if (!string.IsNullOrEmpty(normMime) &&
                    normMime is not "image/png" and not "image/jpeg" and not "image/jpg" and not "image/webp" and not "application/octet-stream")
                {
                    throw new UnauthorizedAccessException($"Nicht erlaubter Anhang-MIME-Typ ('{normMime}'). Nur PNG, JPG und WEBP sind freigegeben.");
                }
            }

            string cleanB64 = attachmentBase64.Trim();
            int commaIdx = cleanB64.IndexOf(',');
            if (cleanB64.StartsWith("data:image/", StringComparison.OrdinalIgnoreCase) && commaIdx > 0)
            {
                cleanB64 = cleanB64.Substring(commaIdx + 1).Trim();
            }

            if (cleanB64.Length > 28_000_000)
                throw new InvalidOperationException("Bildanhang überschreitet das Sicherheitslimit von 20 MB.");

            byte[] bytes;
            try
            {
                bytes = Convert.FromBase64String(cleanB64);
            }
            catch (FormatException)
            {
                throw new UnauthorizedAccessException("Ungültige Base64-Kodierung im Bildanhang.");
            }

            if (bytes.Length < 12 || bytes.Length > MaxAgAttachmentBytes)
                throw new UnauthorizedAccessException("Bildanhang-Größe außerhalb des zulässigen Rahmens (12 Bytes .. 20 MB).");

            bool isPng = bytes[0] == 0x89 && bytes[1] == 0x50 && bytes[2] == 0x4E && bytes[3] == 0x47
                      && bytes[4] == 0x0D && bytes[5] == 0x0A && bytes[6] == 0x1A && bytes[7] == 0x0A;

            bool isJpg = bytes[0] == 0xFF && bytes[1] == 0xD8 && bytes[2] == 0xFF;

            bool isWebp = bytes[0] == 0x52 && bytes[1] == 0x49 && bytes[2] == 0x46 && bytes[3] == 0x46
                       && bytes[8] == 0x57 && bytes[9] == 0x45 && bytes[10] == 0x42 && bytes[11] == 0x50;

            string ext = isPng ? ".png"
                       : isJpg ? ".jpg"
                       : isWebp ? ".webp"
                       : throw new UnauthorizedAccessException("Ungültiger Bild-Magic-Header: Nur echte PNG-, JPG- oder WEBP-Binärdaten werden gespeichert.");

            string mediaDir = string.IsNullOrWhiteSpace(customMediaDir) ? AgDirectMediaDir : Path.GetFullPath(customMediaDir);
            if (!Directory.Exists(mediaDir))
            {
                Directory.CreateDirectory(mediaDir);
            }
            LockDownDirectoryAcl(mediaDir);

            string finalPath = Path.Combine(mediaDir, $"{safeId}{ext}");
            RejectReparsePointsInHierarchy(finalPath);

            string tmpPath = Path.Combine(mediaDir, $".agimg_{Convert.ToHexString(RandomNumberGenerator.GetBytes(8)).ToLowerInvariant()}.tmp");
            try
            {
                File.WriteAllBytes(tmpPath, bytes);
                RejectReparsePointsInHierarchy(finalPath);
                File.Move(tmpPath, finalPath, overwrite: true);
                LockDownFileAcl(finalPath);
            }
            finally
            {
                try { if (File.Exists(tmpPath)) File.Delete(tmpPath); } catch { }
            }

            AppendAuditLog("ag_direct_media", $"{safeId}{ext} ({bytes.Length}B)", "ALLOW");
            return finalPath;
        }

        public static string BuildAgDirectMasterPrompt(
            string itemId,
            string? title,
            string? body,
            IEnumerable<string>? checklist,
            string? executionMode,
            string? savedAttachmentPath,
            string? channel = null)
        {
            string safeId = ValidateAgInboxSingleId(itemId);
            bool isDirect = string.Equals((executionMode ?? "").Trim(), "direct", StringComparison.OrdinalIgnoreCase);
            string modeLabel = isDirect
                ? "Direkt umsetzen via Orchester-Subagenten"
                : "Erst Konzept (warte auf Go)";

            string normChannel = NormalizeAgChannel(channel);
            string channelTitle = GetAgChannelDisplayTitle(normChannel);
            string? scopeHint = GetAgChannelScopeHint(normChannel);

            string cleanTitle = ScrubSecrets((title ?? "").Replace('\0', ' ').Trim());
            if (string.IsNullOrWhiteSpace(cleanTitle))
                cleanTitle = "(Ohne Titel)";

            string cleanBody = ScrubSecrets((body ?? "").Replace('\0', ' ').Trim());

            var sb = new StringBuilder();
            sb.AppendLine($"[DIREKTNACHRICHT AUS ANDROID-APP | ID: {safeId} | MODUS: {modeLabel}] [KANAL: {channelTitle}]");
            if (!string.IsNullOrWhiteSpace(scopeHint))
            {
                sb.AppendLine(scopeHint);
            }
            sb.AppendLine();
            sb.AppendLine($"Hauptpunkt: {cleanTitle}");

            if (!string.IsNullOrWhiteSpace(cleanBody))
            {
                sb.AppendLine();
                sb.AppendLine("Beschreibung:");
                sb.AppendLine(cleanBody);
            }

            if (checklist != null)
            {
                var validItems = checklist
                    .Select(x => ScrubSecrets((x ?? "").Replace('\0', ' ').Trim()))
                    .Where(x => !string.IsNullOrWhiteSpace(x))
                    .Take(100)
                    .ToList();

                if (validItems.Count > 0)
                {
                    sb.AppendLine();
                    sb.AppendLine("Checkliste:");
                    for (int i = 0; i < validItems.Count; i++)
                    {
                        sb.AppendLine($"{i + 1}. {validItems[i]}");
                    }
                }
            }

            if (!string.IsNullOrWhiteSpace(savedAttachmentPath))
            {
                string cleanPath = savedAttachmentPath.Replace('\0', ' ').Trim();
                sb.AppendLine();
                sb.AppendLine($"Angehängtes Bild lokal gespeichert unter: {cleanPath} (bitte per read_file ansehen)");
            }

            sb.AppendLine();
            sb.Append($"Sobald dieser Punkt vollständig erledigt (bzw. das Konzept erstellt) ist, markiere ihn für Alinas Android-App als erledigt via: & 'C:\\Users\\konta\\AppData\\Local\\Funkenwerk\\SentinelCli\\FunkenwerkMcp.exe' --ag-inbox-complete {safeId} \"<kurze Zusammenfassung>\"");

            return sb.ToString();
        }

        public static string SanitizeAgConversationTitle(string? rawTitle)
        {
            string raw = ScrubSecrets(rawTitle ?? "");
            var sb = new StringBuilder(Math.Min(raw.Length, 120));
            foreach (char c in raw)
            {
                if (sb.Length >= 120) break;
                if (c == '\r' || c == '\n' || c == '\t' || char.IsControl(c))
                    sb.Append(' ');
                else
                    sb.Append(c);
            }
            string collapsed = Regex.Replace(sb.ToString(), @"\s+", " ").Trim();
            return string.IsNullOrWhiteSpace(collapsed) ? "Seelenfunke Auftrag" : collapsed;
        }

        public static ProcessStartInfo BuildAgentApiNewConversationStartInfo(
            string? title,
            string promptText,
            string? agentApiExeOverride = null,
            bool ignoreEnvironmentVariableForTest = false)
        {
            string safeTitle = SanitizeAgConversationTitle(title);
            string cleanPrompt = (promptText ?? "").Replace("\0", "").Trim();
            if (string.IsNullOrWhiteSpace(cleanPrompt))
                throw new ArgumentException("Prompt-Text darf nicht leer sein.");
            if (cleanPrompt.Length > 65536)
                cleanPrompt = cleanPrompt.Substring(0, 65536);

            string? configuredExe = !string.IsNullOrWhiteSpace(agentApiExeOverride)
                ? agentApiExeOverride.Trim()
                : (ignoreEnvironmentVariableForTest ? null : Environment.GetEnvironmentVariable("ANTIGRAVITY_AGENTAPI_EXE")?.Trim());

            string defaultLsExe = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Programs", "Antigravity", "resources", "bin", "language_server.exe");

            if (string.IsNullOrWhiteSpace(configuredExe) && !ignoreEnvironmentVariableForTest && File.Exists(defaultLsExe))
            {
                configuredExe = defaultLsExe;
            }

            var psi = new ProcessStartInfo
            {
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true
            };

            if (!string.IsNullOrWhiteSpace(configuredExe))
            {
                psi.FileName = configuredExe;
                psi.ArgumentList.Add("agentapi");
                psi.ArgumentList.Add("new-conversation");
                psi.ArgumentList.Add("--title=" + safeTitle);
                psi.ArgumentList.Add("--");
                psi.ArgumentList.Add(cleanPrompt);
            }
            else
            {
                psi.FileName = "agentapi";
                psi.ArgumentList.Add("new-conversation");
                psi.ArgumentList.Add("--title=" + safeTitle);
                psi.ArgumentList.Add("--");
                psi.ArgumentList.Add(cleanPrompt);
            }

            return psi;
        }

        public static HashSet<string> SnapshotAntigravityBrainUuidFolders(string? customBrainRootDir = null)
        {
            var result = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            string brainRoot = !string.IsNullOrWhiteSpace(customBrainRootDir)
                ? Path.GetFullPath(customBrainRootDir)
                : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".gemini", "antigravity", "brain");

            if (!Directory.Exists(brainRoot))
                return result;

            try
            {
                foreach (string dir in Directory.GetDirectories(brainRoot))
                {
                    string name = Path.GetFileName(dir);
                    if (ConversationUuidExtractRegex.IsMatch(name) && name.Length == 36)
                    {
                        result.Add(name.ToLowerInvariant());
                    }
                }
            }
            catch { }

            return result;
        }

        public static string? ExtractConversationUuid(
            string? stdOut,
            string? stdErr,
            IReadOnlySet<string>? preExistingBrainFolders = null,
            string? customBrainRootDir = null)
        {
            if (!string.IsNullOrWhiteSpace(stdOut))
            {
                var mOut = ConversationUuidExtractRegex.Match(stdOut);
                if (mOut.Success)
                    return ValidateAgConversationId(mOut.Value.ToLowerInvariant());
            }

            if (!string.IsNullOrWhiteSpace(stdErr))
            {
                var mErr = ConversationUuidExtractRegex.Match(stdErr);
                if (mErr.Success)
                    return ValidateAgConversationId(mErr.Value.ToLowerInvariant());
            }

            string brainRoot = !string.IsNullOrWhiteSpace(customBrainRootDir)
                ? Path.GetFullPath(customBrainRootDir)
                : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".gemini", "antigravity", "brain");

            if (Directory.Exists(brainRoot))
            {
                try
                {
                    var newDirs = Directory.GetDirectories(brainRoot)
                        .Select(d => new DirectoryInfo(d))
                        .Where(di => di.Name.Length == 36
                                     && ConversationUuidExtractRegex.IsMatch(di.Name)
                                     && (preExistingBrainFolders == null || !preExistingBrainFolders.Contains(di.Name)))
                        .OrderByDescending(di => di.CreationTimeUtc)
                        .ThenByDescending(di => di.LastWriteTimeUtc)
                        .ToList();

                    if (newDirs.Count > 0)
                    {
                        return ValidateAgConversationId(newDirs[0].Name.ToLowerInvariant());
                    }
                }
                catch { }
            }

            return null;
        }

        public static ProcessStartInfo BuildAgentApiSendMessageStartInfo(
            string targetConversationId,
            string promptText,
            string? agentApiExeOverride = null,
            bool ignoreEnvironmentVariableForTest = false)
        {
            string safeConvId = ValidateAgConversationId(targetConversationId);
            string cleanPrompt = (promptText ?? "").Replace("\0", "").Trim();
            if (string.IsNullOrWhiteSpace(cleanPrompt))
                throw new ArgumentException("Prompt-Text darf nicht leer sein.");
            if (cleanPrompt.Length > 65536)
                cleanPrompt = cleanPrompt.Substring(0, 65536);

            string? configuredExe = !string.IsNullOrWhiteSpace(agentApiExeOverride)
                ? agentApiExeOverride.Trim()
                : (ignoreEnvironmentVariableForTest ? null : Environment.GetEnvironmentVariable("ANTIGRAVITY_AGENTAPI_EXE")?.Trim());

            string defaultLsExe = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Programs", "Antigravity", "resources", "bin", "language_server.exe");

            if (string.IsNullOrWhiteSpace(configuredExe) && !ignoreEnvironmentVariableForTest && File.Exists(defaultLsExe))
            {
                configuredExe = defaultLsExe;
            }

            var psi = new ProcessStartInfo
            {
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true
            };

            if (!string.IsNullOrWhiteSpace(configuredExe))
            {
                psi.FileName = configuredExe;
                psi.ArgumentList.Add("agentapi");
                psi.ArgumentList.Add("send-message");
                psi.ArgumentList.Add("--");
                psi.ArgumentList.Add(safeConvId);
                psi.ArgumentList.Add(cleanPrompt);
            }
            else
            {
                psi.FileName = "agentapi";
                psi.ArgumentList.Add("send-message");
                psi.ArgumentList.Add("--");
                psi.ArgumentList.Add(safeConvId);
                psi.ArgumentList.Add(cleanPrompt);
            }

            return psi;
        }

        public static async Task<string> ExecuteStageAgInboxCommandAsync(
            string operation,
            string? itemId = null,
            string? summary = null,
            string profileName = "Mittwald",
            bool isDaemonInternal = false,
            string? customRemoteStageDirForTest = null)
        {
            if (!isDaemonInternal)
            {
                EnforceRateLimit("ag_direct_inbox", isSshCall: true);
            }

            string targetRemoteDir = string.IsNullOrWhiteSpace(customRemoteStageDirForTest)
                ? StageWebRemoteDir
                : customRemoteStageDirForTest.Trim();

            // Hard Live-Air-Gap Guard BEFORE touching vaults or opening any sockets
            ValidateStageOnlyTarget(StageAllowedHost, targetRemoteDir, allowWorkerStage: false);

            string remoteCommand;
            string auditSummary;
            switch ((operation ?? "").Trim().ToLowerInvariant())
            {
                case "peek_pending":
                    remoteCommand = "php artisan system:ag-inbox --peek-pending 2>/dev/null";
                    auditSummary = "peek_pending";
                    break;

                case "pull_pending":
                    remoteCommand = "php artisan system:ag-inbox --pull-pending 2>/dev/null";
                    auditSummary = "pull_pending";
                    break;

                case "list":
                    remoteCommand = "php artisan system:ag-inbox --list 2>/dev/null";
                    auditSummary = "list";
                    break;

                case "mark_delivered":
                {
                    string safeIds = ValidateAgInboxMarkDeliveredIds(itemId);
                    remoteCommand = $"php artisan system:ag-inbox --mark-delivered={safeIds} 2>/dev/null";
                    auditSummary = $"mark_delivered:{safeIds}";
                    break;
                }

                case "complete":
                {
                    string safeId = ValidateAgInboxSingleId(itemId);
                    string cleanSummary = ScrubSecrets((summary ?? "").Replace('\0', ' ').Trim());
                    if (string.IsNullOrWhiteSpace(cleanSummary))
                        cleanSummary = "Erledigt durch Antigravity.";
                    if (cleanSummary.Length > 4000)
                        cleanSummary = cleanSummary.Substring(0, 4000);

                    string summaryB64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(cleanSummary));
                    if (!SafeBase64PayloadRegex.IsMatch(summaryB64))
                        throw new UnauthorizedAccessException("Ungültige Base64-Zusammenfassung blockiert.");

                    remoteCommand = $"php artisan system:ag-inbox --complete={safeId} --summary-b64='{summaryB64}' 2>/dev/null";
                    auditSummary = $"complete:{safeId}";
                    break;
                }

                default:
                    throw new UnauthorizedAccessException($"AG-Inbox-Operation '{operation}' ist nicht in der Sicherheits-Allowlist enthalten.");
            }

            using var creds = ResolveProfileInternal(profileName, targetRemoteDir, forceKeyOnlyNoPasswordDecrypt: true)
                ?? throw new InvalidOperationException($"Profil '{profileName}' wurde nicht gefunden.");

            ValidateStageOnlyTarget(creds.Host, creds.RemoteDirectory, allowWorkerStage: false);

            if (!HasAnySha256PinForHost(creds.Host, creds.Port))
            {
                AppendAuditLog("ag_direct_inbox", $"{creds.Host}:{creds.Port}", "DENY:unpinned_host_key");
                throw new UnauthorizedAccessException($"Host '{creds.Host}:{creds.Port}' ist nicht per SHA-256 gepinnt.");
            }

            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();

            return await Task.Run(() =>
            {
                var authMethods = new List<AuthenticationMethod>();
                PrivateKeyFile? keyFile = null;
                try
                {
                    if (!string.IsNullOrWhiteSpace(creds.PrivateKeyPath) && File.Exists(creds.PrivateKeyPath))
                    {
                        keyFile = new PrivateKeyFile(creds.PrivateKeyPath);
                        authMethods.Add(new PrivateKeyAuthenticationMethod(creds.Username, keyFile));
                    }
                    if (keyFile == null || authMethods.Count == 0)
                    {
                        AppendAuditLog("ag_direct_inbox", $"{creds.Name}:{auditSummary}", "DENY:stage_requires_ed25519_key");
                        throw new UnauthorizedAccessException(
                            "Stage AG-Inbox erzwingt reine Ed25519-SSH-Key-Authentifizierung (Passwort-Fallback ist hart deaktiviert).");
                    }

                    var connInfo = new ConnectionInfo(creds.Host, creds.Port, creds.Username, authMethods.ToArray())
                    {
                        Timeout = TimeSpan.FromSeconds(12)
                    };

                    using var client = new SshClient(connInfo);
                    bool hostVerified = false;
                    client.HostKeyReceived += (s, e) =>
                    {
                        string actualFp = "SHA256:" + Convert.ToBase64String(SHA256.HashData(e.HostKey)).TrimEnd('=');
                        byte[] actualBytes = Encoding.ASCII.GetBytes(actualFp);

                        var allowedFps = new List<string>();
                        string exactKey = $"{creds.Host.ToLowerInvariant()}:{creds.Port}:{e.HostKeyName}";
                        if (fwPinned.TryGetValue(exactKey, out string? fwExact)) allowedFps.Add(fwExact);
                        if (fwPinned.TryGetValue($"{creds.Host}:{creds.Port}", out string? fwHostPort)) allowedFps.Add(fwHostPort);
                        allowedFps.AddRange(FindOpenSshPinnedFingerprintsForHost(creds.Host, creds.Port, e.HostKeyName));

                        bool matchedPin = false;
                        foreach (string candidateFp in allowedFps)
                        {
                            byte[] expectedBytes = Encoding.ASCII.GetBytes(candidateFp.Trim());
                            if (actualBytes.Length == expectedBytes.Length &&
                                CryptographicOperations.FixedTimeEquals(actualBytes, expectedBytes))
                            {
                                matchedPin = true;
                                break;
                            }
                        }

                        hostVerified = matchedPin;
                        e.CanTrust = matchedPin;
                    };

                    client.Connect();
                    if (!hostVerified || !client.IsConnected)
                        throw new UnauthorizedAccessException("SSH Host-Key-Prüfung für Stage AG-Inbox fehlgeschlagen (MITM-Schutz aktiv).");

                    string fullCmd = $"cd -- '{StageWebRemoteDir}' && {remoteCommand}";
                    using var cmd = client.CreateCommand(fullCmd);
                    cmd.CommandTimeout = TimeSpan.FromSeconds(30);
                    string stdout = cmd.Execute() ?? "";
                    string stderr = cmd.Error ?? "";
                    int exitCode = cmd.ExitStatus ?? 0;
                    client.Disconnect();

                    string cleanOut = AnsiEscapeRegex.Replace(stdout, "").Trim();
                    if (cleanOut.Length > 32 * 1024 * 1024)
                        throw new InvalidOperationException("Stage AG-Inbox Antwort überschreitet das 32 MB Sicherheitslimit.");

                    if (exitCode != 0)
                    {
                        string errDetail = ScrubSecrets(AnsiEscapeRegex.Replace($"{stdout}\n{stderr}", "").Trim());
                        if (errDetail.Length > 2000) errDetail = errDetail.Substring(0, 2000);
                        AppendAuditLog("ag_direct_inbox", $"{creds.Name}:{auditSummary}:exit={exitCode}", "ERROR");
                        throw new InvalidOperationException($"Stage AG-Inbox ({auditSummary}) meldete Exit-Code {exitCode}: {errDetail}");
                    }

                    if (!isDaemonInternal || auditSummary != "peek_pending")
                    {
                        AppendAuditLog("ag_direct_inbox", $"{creds.Name}:{auditSummary}", "ALLOW");
                    }
                    return cleanOut;
                }
                finally
                {
                    keyFile?.Dispose();
                }
            });
        }

        // =====================================================================
        // PINNED SEELENFUNKE-LIVE CLI PROVISIONING HELPERS (ZERO-SECRET, CLI-ONLY)
        // =====================================================================
        public const string PinnedLiveProjectId = "a7a6fd9f-3844-4bdd-8fe0-c7d1c0827cc5";
        public const string PinnedLiveProjectShortId = "p-g27wim";
        public const string PinnedLiveAppUuid = "93f33f15-d27c-4d15-9f85-155eb9adace8";
        public const string PinnedLiveAppShortId = "a-bycqrf";
        public const string PinnedLiveSshUser = "kontakt@mein-seelenfunke.de@a-bycqrf";
        public const string PinnedStageWebSshUser = "kontakt@mein-seelenfunke.de@a-lc6tkm";
        public const string PinnedLiveRemoteDir = "/home/p-g27wim/html/seelenfunke-live";
        public const string PinnedLiveEnvRemotePath = "/home/p-g27wim/html/seelenfunke-live/.env";
        public const string PinnedStageEnvRemotePath = "/home/p-g27wim/html/seelenfunke-stage/.env";
        public const string PinnedLiveMysqlDbUuid = "e57a404b-b9b4-477f-878a-d0a53fe3dc06";
        public const string PinnedLiveMysqlHost = "mysql-gv2avu.pg-s-pis5pf.db.project.host";
        public const int PinnedLiveMysqlPort = 3306;
        public const string PinnedLiveMysqlDatabase = "mysql_gv2avu";
        public const string PinnedLiveMysqlUser = "dbu_gv2avu";
        public const string PinnedLiveMysqlUserUuid = "86a83822-c63e-44e0-8c81-0ce0d6a119e8";
        public const string PinnedLiveApexIngressUuid = "713ea3f6-bf74-4176-82df-4b495e6d6952";
        public const string PinnedLiveRootZoneUuid = "4fbe5480-bc45-4edc-8a38-6c9648c00f4e";
        public const string PinnedLiveWwwHostname = "www.mein-seelenfunke.de";

        public static void ValidatePinnedSeelenfunkeLiveTarget(
            string? projectId,
            string? appUuid,
            string? remoteDir,
            string? sshUser,
            string? mysqlDatabase,
            string? mysqlUserUuid)
        {
            string combined = $"{projectId} | {appUuid} | {remoteDir} | {sshUser} | {mysqlDatabase} | {mysqlUserUuid}";
            if (combined.Contains("steinhauer-invest", StringComparison.OrdinalIgnoreCase)
                || combined.Contains("a-s4sdym", StringComparison.OrdinalIgnoreCase)
                || combined.Contains("2be54dff-9651-416e-8aba-79a5b63749ff", StringComparison.OrdinalIgnoreCase)
                || combined.Contains("mysql_fg9zlg", StringComparison.OrdinalIgnoreCase))
            {
                AppendAuditLog("mittwald_prepare_live", ScrubSecrets(combined), "DENY:steinhauer_invest_blocked");
                throw new UnauthorizedAccessException(
                    "HARTE SICHERHEITSSPERRE: Zugriff auf Fremd-App 'steinhauer-invest' (a-s4sdym / mysql_fg9zlg) ist zu 100 % technisch gesperrt!");
            }

            if (!string.Equals(projectId, PinnedLiveProjectId, StringComparison.Ordinal)
                || !string.Equals(appUuid, PinnedLiveAppUuid, StringComparison.Ordinal)
                || !string.Equals(remoteDir, PinnedLiveRemoteDir, StringComparison.Ordinal)
                || !string.Equals(sshUser, PinnedLiveSshUser, StringComparison.Ordinal)
                || !string.Equals(mysqlDatabase, PinnedLiveMysqlDatabase, StringComparison.Ordinal)
                || !string.Equals(mysqlUserUuid, PinnedLiveMysqlUserUuid, StringComparison.Ordinal))
            {
                AppendAuditLog("mittwald_prepare_live", ScrubSecrets(combined), "DENY:unpinned_live_target");
                throw new UnauthorizedAccessException(
                    "PINNING-SPERRE: --mittwald-prepare-live akzeptiert ausschließlich die hart gepinnten Seelenfunke-Live-UUIDs.");
            }
        }

        internal static string ResolveMittwaldEd25519KeyPath()
        {
            using var creds = ResolveProfileInternal("Mittwald", StageWebRemoteDir, forceKeyOnlyNoPasswordDecrypt: true);
            if (creds != null && !string.IsNullOrWhiteSpace(creds.PrivateKeyPath) && File.Exists(creds.PrivateKeyPath))
                return creds.PrivateKeyPath;

            string fallback = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "id_ed25519");
            if (File.Exists(fallback))
                return fallback;

            throw new FileNotFoundException("Ed25519-SSH-Schlüssel (id_ed25519) für Mittwald wurde nicht gefunden.");
        }

        private static bool VerifyPinnedHostKeyBytes(
            string host,
            int port,
            byte[] hostKeyBytes,
            string hostKeyName,
            Dictionary<string, string> fwPinned)
        {
            string actualFp = "SHA256:" + Convert.ToBase64String(SHA256.HashData(hostKeyBytes)).TrimEnd('=');
            byte[] actualBytes = Encoding.ASCII.GetBytes(actualFp);

            var allowedFps = new List<string>();
            string exactKey = $"{host.ToLowerInvariant()}:{port}:{hostKeyName}";
            if (fwPinned.TryGetValue(exactKey, out string? fwExact)) allowedFps.Add(fwExact);
            if (fwPinned.TryGetValue($"{host}:{port}", out string? fwHostPort)) allowedFps.Add(fwHostPort);
            allowedFps.AddRange(FindOpenSshPinnedFingerprintsForHost(host, port, hostKeyName));

            foreach (string candidateFp in allowedFps)
            {
                byte[] expectedBytes = Encoding.ASCII.GetBytes(candidateFp.Trim());
                if (actualBytes.Length == expectedBytes.Length &&
                    CryptographicOperations.FixedTimeEquals(actualBytes, expectedBytes))
                {
                    return true;
                }
            }
            return false;
        }

        internal static async Task<byte[]?> ReadPinnedContainerEnvBytesZeroSecretAsync(string sshUser, string exactRemotePath)
        {
            bool allowedCombo =
                (string.Equals(sshUser, PinnedLiveSshUser, StringComparison.Ordinal) &&
                 (string.Equals(exactRemotePath, PinnedLiveEnvRemotePath, StringComparison.Ordinal) ||
                  string.Equals(exactRemotePath, PinnedStageEnvRemotePath, StringComparison.Ordinal)))
                || (string.Equals(sshUser, PinnedStageWebSshUser, StringComparison.Ordinal) &&
                    string.Equals(exactRemotePath, PinnedStageEnvRemotePath, StringComparison.Ordinal));

            if (!allowedCombo)
                throw new UnauthorizedAccessException("PINNING-SPERRE: Unzulässige SSH-User/Pfad-Kombination beim Zero-Secret .env-Read.");

            if (!HasAnySha256PinForHost(StageAllowedHost, 22))
                throw new UnauthorizedAccessException($"Host '{StageAllowedHost}:22' ist nicht per SHA-256 gepinnt.");

            string keyPath = ResolveMittwaldEd25519KeyPath();
            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();

            return await Task.Run(() =>
            {
                using var keyFile = new PrivateKeyFile(keyPath);
                var connInfo = new ConnectionInfo(StageAllowedHost, 22, sshUser, new PrivateKeyAuthenticationMethod(sshUser, keyFile))
                {
                    Timeout = TimeSpan.FromSeconds(15)
                };

                using var sftp = new SftpClient(connInfo);
                bool pinned = false;
                sftp.HostKeyReceived += (s, e) => { pinned = VerifyPinnedHostKeyBytes(StageAllowedHost, 22, e.HostKey, e.HostKeyName, fwPinned); e.CanTrust = pinned; };
                sftp.Connect();
                if (!pinned || !sftp.IsConnected)
                    throw new UnauthorizedAccessException("SFTP Host-Key-Prüfung fehlgeschlagen.");

                if (!sftp.Exists(exactRemotePath))
                {
                    sftp.Disconnect();
                    return null;
                }

                var attr = sftp.GetAttributes(exactRemotePath);
                if (attr.IsSymbolicLink)
                    throw new UnauthorizedAccessException($"Symlink auf '{exactRemotePath}' aus Sicherheitsgründen blockiert.");
                if (attr.Size < 0 || attr.Size > 256 * 1024)
                    throw new InvalidOperationException($"Unerwartete .env Dateigröße ({attr.Size} Bytes) auf '{exactRemotePath}'.");

                using var ms = new MemoryStream((int)Math.Max(attr.Size, 4096));
                try
                {
                    sftp.DownloadFile(exactRemotePath, ms);
                    sftp.Disconnect();
                    byte[] exact = new byte[ms.Length];
                    Buffer.BlockCopy(ms.GetBuffer(), 0, exact, 0, exact.Length);
                    return exact;
                }
                finally
                {
                    byte[] internalBuf = ms.GetBuffer();
                    CryptographicOperations.ZeroMemory(internalBuf);
                }
            });
        }

        internal static async Task<(bool Connected, string StatusToken)> ProbePinnedLiveMysqlPdoZeroSecretAsync()
        {
            ValidatePinnedSeelenfunkeLiveTarget(
                PinnedLiveProjectId,
                PinnedLiveAppUuid,
                PinnedLiveRemoteDir,
                PinnedLiveSshUser,
                PinnedLiveMysqlDatabase,
                PinnedLiveMysqlUserUuid);

            if (!HasAnySha256PinForHost(StageAllowedHost, 22))
                throw new UnauthorizedAccessException($"Host '{StageAllowedHost}:22' ist nicht per SHA-256 gepinnt.");

            string keyPath = ResolveMittwaldEd25519KeyPath();
            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();

            // Static Zero-Secret PHP script: reads /home/p-g27wim/html/seelenfunke-live/.env locally inside container a-bycqrf,
            // verifies exact pinned Live DB host/name/user, tests PDO connection, and emits ONLY a sanitized status token.
            const string staticPhpProbe = """
                $f = '/home/p-g27wim/html/seelenfunke-live/.env';
                if (!is_file($f) || is_link($f)) { echo 'NO_ENV'; exit(2); }
                $vars = [];
                foreach (file($f, FILE_IGNORE_NEW_LINES) as $line) {
                    $t = trim($line);
                    if ($t === '' || $t[0] === '#') continue;
                    if (str_starts_with($t, 'export ')) $t = trim(substr($t, 7));
                    $eq = strpos($t, '=');
                    if ($eq === false) continue;
                    $k = trim(substr($t, 0, $eq));
                    $v = trim(substr($t, $eq + 1));
                    if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                        $v = substr($v, 1, -1);
                    }
                    $vars[$k] = $v;
                }
                $h = $vars['DB_HOST'] ?? '';
                $p = $vars['DB_PORT'] ?? '3306';
                $d = $vars['DB_DATABASE'] ?? '';
                $u = $vars['DB_USERNAME'] ?? '';
                $pw = $vars['DB_PASSWORD'] ?? '';
                if ($h !== 'mysql-gv2avu.pg-s-pis5pf.db.project.host' || $d !== 'mysql_gv2avu' || $u !== 'dbu_gv2avu' || $pw === '') {
                    echo 'MISMATCH_DB_TARGET';
                    exit(3);
                }
                try {
                    $pdo = new PDO("mysql:host={$h};port={$p};dbname={$d};charset=utf8mb4", $u, $pw, [
                        PDO::ATTR_TIMEOUT => 5,
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $row = $pdo->query('SELECT DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
                    if (($row['db'] ?? '') === 'mysql_gv2avu') {
                        echo 'PDO_OK';
                        exit(0);
                    }
                    echo 'PDO_WRONG_DB';
                    exit(4);
                } catch (Throwable $e) {
                    echo 'PDO_ERR:' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$e->getCode());
                    exit(1);
                }
                """;

            string b64Php = Convert.ToBase64String(Encoding.UTF8.GetBytes(staticPhpProbe.Replace("\r\n", "\n")));
            string remoteCmd = $"php -r \"eval(base64_decode('{b64Php}'));\"";

            return await Task.Run(() =>
            {
                using var keyFile = new PrivateKeyFile(keyPath);
                var connInfo = new ConnectionInfo(StageAllowedHost, 22, PinnedLiveSshUser, new PrivateKeyAuthenticationMethod(PinnedLiveSshUser, keyFile))
                {
                    Timeout = TimeSpan.FromSeconds(15)
                };

                using var ssh = new SshClient(connInfo);
                bool pinned = false;
                ssh.HostKeyReceived += (s, e) => { pinned = VerifyPinnedHostKeyBytes(StageAllowedHost, 22, e.HostKey, e.HostKeyName, fwPinned); e.CanTrust = pinned; };
                ssh.Connect();
                if (!pinned || !ssh.IsConnected)
                    throw new UnauthorizedAccessException("SSH Host-Key-Prüfung fehlgeschlagen.");

                using var cmd = ssh.CreateCommand(remoteCmd);
                cmd.CommandTimeout = TimeSpan.FromSeconds(12);
                string stdout = (cmd.Execute() ?? "").Trim();
                int exit = cmd.ExitStatus ?? -1;
                ssh.Disconnect();

                string safeToken = Regex.Replace(stdout, @"[^A-Za-z0-9_:\-]", "");
                if (safeToken.Length > 48) safeToken = safeToken.Substring(0, 48);
                bool ok = exit == 0 && string.Equals(safeToken, "PDO_OK", StringComparison.Ordinal);
                return (ok, string.IsNullOrEmpty(safeToken) ? $"EXIT_{exit}" : safeToken);
            });
        }

        internal static async Task<string> WritePinnedLiveEnvBytesZeroSecretAsync(ReadOnlyMemory<byte> envUtf8)
        {
            ValidatePinnedSeelenfunkeLiveTarget(
                PinnedLiveProjectId,
                PinnedLiveAppUuid,
                PinnedLiveRemoteDir,
                PinnedLiveSshUser,
                PinnedLiveMysqlDatabase,
                PinnedLiveMysqlUserUuid);

            if (envUtf8.Length == 0 || envUtf8.Length > 256 * 1024)
                throw new ArgumentException("Ungültige Live-.env Puffergröße.");

            if (!HasAnySha256PinForHost(StageAllowedHost, 22))
                throw new UnauthorizedAccessException($"Host '{StageAllowedHost}:22' ist nicht per SHA-256 gepinnt.");

            string keyPath = ResolveMittwaldEd25519KeyPath();
            var fwPinned = LoadFunkenwerkPinnedHostKeysSha256();
            string tmpRemotePath = $"{PinnedLiveEnvRemotePath}.tmp.{Guid.NewGuid():N}";

            return await Task.Run(() =>
            {
                using var keyFile = new PrivateKeyFile(keyPath);
                var connInfo = new ConnectionInfo(StageAllowedHost, 22, PinnedLiveSshUser, new PrivateKeyAuthenticationMethod(PinnedLiveSshUser, keyFile))
                {
                    Timeout = TimeSpan.FromSeconds(15)
                };

                using var ssh = new SshClient(connInfo);
                bool sshPinned = false;
                ssh.HostKeyReceived += (s, e) => { sshPinned = VerifyPinnedHostKeyBytes(StageAllowedHost, 22, e.HostKey, e.HostKeyName, fwPinned); e.CanTrust = sshPinned; };
                ssh.Connect();
                if (!sshPinned || !ssh.IsConnected)
                    throw new UnauthorizedAccessException("SSH Host-Key-Prüfung für Live-.env Write fehlgeschlagen.");

                // 1. Pre-create 0600 file before writing a single secret byte
                string prepCmd =
                    $"if [ -L '{PinnedLiveRemoteDir}' ] || [ -L '{PinnedLiveEnvRemotePath}' ]; then echo SYMLINK_BLOCKED; exit 9; fi && " +
                    $"mkdir -p -m 750 '{PinnedLiveRemoteDir}' && " +
                    $"install -m 600 /dev/null '{tmpRemotePath}'";
                using (var cPrep = ssh.CreateCommand(prepCmd))
                {
                    cPrep.CommandTimeout = TimeSpan.FromSeconds(10);
                    string prepOut = (cPrep.Execute() ?? "").Trim();
                    if ((cPrep.ExitStatus ?? -1) != 0)
                        throw new InvalidOperationException($"Vorbereitung der 0600-Temp-Datei fehlgeschlagen ({ScrubSecrets(prepOut)}).");
                }

                byte[] writeBuf = envUtf8.ToArray();
                try
                {
                    using var sftp = new SftpClient(connInfo);
                    bool sftpPinned = false;
                    sftp.HostKeyReceived += (s, e) => { sftpPinned = VerifyPinnedHostKeyBytes(StageAllowedHost, 22, e.HostKey, e.HostKeyName, fwPinned); e.CanTrust = sftpPinned; };
                    sftp.Connect();
                    if (!sftpPinned || !sftp.IsConnected)
                        throw new UnauthorizedAccessException("SFTP Host-Key-Prüfung für Live-.env Write fehlgeschlagen.");

                    using (var remoteStream = sftp.Open(tmpRemotePath, FileMode.Truncate, FileAccess.Write))
                    {
                        remoteStream.Write(writeBuf, 0, writeBuf.Length);
                        remoteStream.Flush();
                    }
                    sftp.Disconnect();

                    // 2. Atomic rename + strict 0600 verification
                    string finalizeCmd =
                        $"chmod 600 '{tmpRemotePath}' && " +
                        $"mv -f '{tmpRemotePath}' '{PinnedLiveEnvRemotePath}' && " +
                        $"chmod 600 '{PinnedLiveEnvRemotePath}' && " +
                        $"stat -c '%a' '{PinnedLiveEnvRemotePath}'";
                    using var cFin = ssh.CreateCommand(finalizeCmd);
                    cFin.CommandTimeout = TimeSpan.FromSeconds(10);
                    string modeOut = (cFin.Execute() ?? "").Trim();
                    if ((cFin.ExitStatus ?? -1) != 0 || !string.Equals(modeOut, "600", StringComparison.Ordinal))
                        throw new InvalidOperationException($"Atomares Setzen von Modus 0600 auf Live-.env fehlgeschlagen (Modus='{ScrubSecrets(modeOut)}').");

                    ssh.Disconnect();
                    AppendAuditLog("mittwald_prepare_live", "write_live_env_0600", "ALLOW");
                    return modeOut;
                }
                catch
                {
                    try
                    {
                        if (ssh.IsConnected)
                        {
                            using var cClean = ssh.CreateCommand($"rm -f '{tmpRemotePath}'");
                            cClean.Execute();
                        }
                    }
                    catch { }
                    throw;
                }
                finally
                {
                    CryptographicOperations.ZeroMemory(writeBuf);
                }
            });
        }

        public static SecurityAuditStatusDto RunSecuritySelfAudit()
        {
            bool dirAclOk = LockDownDirectoryAcl(McpDataDir);
            if (!File.Exists(AuditLogFile))
            {
                AppendAuditLog("security_self_audit", "audit_log_bootstrap", "INIT");
            }
            bool fileAclOk = LockDownFileAcl(AuditLogFile);
            var (chainOk, count) = VerifyAuditChain();
            int pinnedCount = CountTotalPinnedHostEntries();

            return new SecurityAuditStatusDto
            {
                DirectoryAclRestrictedToCurrentUser = dirAclOk,
                AuditFileAclRestrictedToCurrentUser = fileAclOk,
                AuditHashChainValid = chainOk,
                AuditEntriesCount = count,
                PinnedSshHostsCount = pinnedCount,
                AllowedFilesystemRoots = GetAllowedFilesystemRoots(),
                AllowedApiHosts = AllowedApiHosts.ToList(),
                ActiveHardeningLayers = new List<string>
                {
                    "1. Stdio-Only Transport + Stdout-Wire-Isolation (0 offene TCP/UDP Ports, Console.Out -> Stderr)",
                    "2. Dateisystem-Sandbox + Anti-Traversal + Symlink/ADS/DOS-Device-Blocker + %PDF- Magic-Header + Atomic-Write",
                    "3. Zero-Secret Vault-Bridge (DPAPI + PBKDF2-SHA512 + AES-256-GCM + ZeroMemory + 14-Pattern Outbound-DLP)",
                    "4. Prompt-Injection-Sanitizer + JSON-MaxDepth-24 + Bounded-Chunk-Reader (512 KB Limit)",
                    "5. Fail-Closed SHA-256 SSH Host-Key-Pinning + Ed25519 Key-Only Stage-Autopilot (0 Passwort-Entschlüsselung im RAM)",
                    "6. SSRF-Schutz (AllowAutoRedirect=false, UseProxy=false, UserInfo-Block, TLS 1.2/1.3 Host-Pinning)",
                    "7. Sliding-Window Rate-Limiter (60 Calls/Min gesamt, 10 SSH-Calls/Min)",
                    "8. Manipulationssicheres SHA-256 Hash-Chain Security-Audit-Log + NTFS CurrentUser Ordner/Datei-ACL"
                }
            };
        }
    }
}

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
using System.Security.Principal;
using System.Text;
using System.Text.Json;
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
            @"\funkenwerkssh\"
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
            new(@"\bsk-[A-Za-z0-9\-_]{24,}\b", RegexOptions.Compiled),                                  // OpenAI/Stripe secret keys
            new(@"\b(?:ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,})\b", RegexOptions.Compiled),   // GitHub PATs
            new(@"\beyJ[A-Za-z0-9_\-]{10,}\.eyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b", RegexOptions.Compiled), // JWTs
            new(@"(?i)https?://[^/\s:@]+:[^/\s@]+@[^\s/]+", RegexOptions.Compiled),                     // Basic-Auth credentials in HTTP URLs
            new(@"(?i)rtsps?://[^/\s:@""']+:[^/\s@""']+@[^\s""']+", RegexOptions.Compiled),             // RTSP camera credentials in URLs
            new(@"(?i)Bearer\s+[A-Za-z0-9\-._~+/|=]{20,}", RegexOptions.Compiled)                       // Raw Bearer headers
        };

        public const string StageAllowedHost = "ssh.altgemeinde.project.host";
        public const string StageWebRemoteDir = "/home/p-g27wim/html/seelenfunke-stage";
        public const string StageWorkerRemoteDir = "/home/p-g27wim/html/worker-stage-3";
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
            "stage_worker_restart"
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

        private static InternalResolvedSshCreds? ResolveProfileInternal(string profileName, string? overrideRemoteDir)
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

                        if (host.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase) &&
                            (string.IsNullOrWhiteSpace(user) || user.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase)))
                        {
                            user = remDir.Contains("worker-stage", StringComparison.OrdinalIgnoreCase)
                                ? "kontakt@mein-seelenfunke.de@a-iurgq8"
                                : "kontakt@mein-seelenfunke.de@a-lc6tkm";
                        }

                        if (string.IsNullOrWhiteSpace(keyPath) || keyPath.EndsWith(".ppk", StringComparison.OrdinalIgnoreCase) || !File.Exists(keyPath))
                        {
                            string defaultEd25519 = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "id_ed25519");
                            if (File.Exists(defaultEd25519)) keyPath = defaultEd25519;
                        }

                        ValidateSshEndpointParameters(host, port, user, remDir);

                        byte[]? pwdBytes = null;
                        if (el.TryGetProperty("EncryptedPassword", out var ep) && !string.IsNullOrWhiteSpace(ep.GetString()))
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
            bool isStageWorker = allowWorkerStage && string.Equals(normDir, StageWorkerRemoteDir, StringComparison.Ordinal);

            if (!isStageWeb && !isStageWorker)
            {
                AppendAuditLog("stage_airgap_guard", $"{normHost}:{normDir}", "DENY:non_stage_dir");
                string expected = allowWorkerStage ? $"{StageWebRemoteDir} oder {StageWorkerRemoteDir}" : StageWebRemoteDir;
                throw new UnauthorizedAccessException($"Stage-Operation abgelehnt: Zielpfad '{normDir}' entspricht nicht exakt '{expected}'.");
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
                _ => throw new UnauthorizedAccessException($"SSH-Preset '{preset}' ist nicht in der Sicherheits-Allowlist enthalten.")
            };

            bool isStageMutationPreset = preset is "stage_git_pull" or "stage_deploy" or "stage_worker_restart";

            if (!string.IsNullOrWhiteSpace(safeSubdirectory))
            {
                if (safeSubdirectory.Length > 512 || !SafeRemoteDirRegex.IsMatch(safeSubdirectory) || safeSubdirectory.Contains("..", StringComparison.Ordinal))
                    throw new UnauthorizedAccessException("Ungültiger Server-Pfad im Sicherheitsfilter blockiert.");

                if (isStageMutationPreset)
                {
                    ValidateStageOnlyTarget(StageAllowedHost, safeSubdirectory, allowWorkerStage: preset == "stage_worker_restart");
                }
            }

            using var creds = ResolveProfileInternal(profileName, safeSubdirectory)
                ?? throw new InvalidOperationException($"Profil '{profileName}' wurde nicht gefunden.");

            if (isStageMutationPreset)
            {
                ValidateStageOnlyTarget(creds.Host, creds.RemoteDirectory, allowWorkerStage: preset == "stage_worker_restart");
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
                "stage_worker_restart" => 35,
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
                    if (creds.PasswordUtf8 != null && creds.PasswordUtf8.Length > 0)
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
                    string fullCmd = !string.IsNullOrWhiteSpace(creds.RemoteDirectory) && creds.RemoteDirectory != "/"
                        ? $"cd -- '{creds.RemoteDirectory}' && {remoteCommand}"
                        : remoteCommand;

                    using var cmd = client.CreateCommand(fullCmd);
                    cmd.CommandTimeout = TimeSpan.FromSeconds(timeoutSeconds);
                    string stdout = cmd.Execute();
                    string stderr = cmd.Error ?? "";
                    int exitCode = cmd.ExitStatus ?? 0;
                    client.Disconnect();

                    string combined = string.IsNullOrWhiteSpace(stderr) ? stdout : $"{stdout}\n{stderr}";
                    combined = AnsiEscapeRegex.Replace(combined, "");
                    if (combined.Length > 16384)
                        combined = combined.Substring(0, 16384) + "\n...[Ausgabe gekürzt]";

                    if (isStageMutationPreset && exitCode != 0)
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

            using var creds = ResolveProfileInternal(profileName, StageWebRemoteDir)
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
                    if (creds.PasswordUtf8 != null && creds.PasswordUtf8.Length > 0)
                    {
                        authMethods.Add(new PasswordAuthenticationMethod(creds.Username, Encoding.UTF8.GetString(creds.PasswordUtf8)));
                    }
                    if (authMethods.Count == 0)
                        throw new InvalidOperationException("Keine gültige Authentifizierungsmethode im Tresor hinterlegt.");

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
                    "5. Fail-Closed SHA-256 SSH Host-Key-Pinning (Funkenwerk + OpenSSH HMAC) + Strikte Preset-Allowlist",
                    "6. SSRF-Schutz (AllowAutoRedirect=false, UseProxy=false, UserInfo-Block, TLS 1.2/1.3 Host-Pinning)",
                    "7. Sliding-Window Rate-Limiter (60 Calls/Min gesamt, 10 SSH-Calls/Min)",
                    "8. Manipulationssicheres SHA-256 Hash-Chain Security-Audit-Log + NTFS CurrentUser Ordner/Datei-ACL"
                }
            };
        }
    }
}

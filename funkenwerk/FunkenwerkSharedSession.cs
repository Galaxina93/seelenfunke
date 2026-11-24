using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using Microsoft.Win32;

namespace Funkenwerk
{
    public sealed class SharedSessionDto
    {
        public string ApiBaseUrl { get; set; } = "https://stage.mein-seelenfunke.de/api";
        public string BearerToken { get; set; } = "";
        public string UserEmail { get; set; } = "";
        public string UserType { get; set; } = "employee";
        public string[] AllowedSpaces { get; set; } = new[] { "launcher" };
        public string[] AllowedModules { get; set; } = Array.Empty<string>();
        public bool HasVerifiedOnlineOnce { get; set; }
        public bool IsSessionLocked { get; set; }
        public string PasswordSaltB64 { get; set; } = "";
        public string PasswordVerifierB64 { get; set; } = "";
        public bool IsDarkMode { get; set; } = true;
        public string UpdatedAtUtc { get; set; } = "";
    }

    public sealed class CollectionModuleSpec
    {
        public string Key { get; init; } = "";
        public string ShortTitle { get; init; } = "";
        public string FullName { get; init; } = "";
        public string FolderName { get; init; } = "";
        public string ExeName { get; init; } = "";
        public string ProcessName { get; init; } = "";
        public string IconFileName { get; init; } = "";
        public string VectorPathData { get; init; } = "";
        public string[] Keywords { get; init; } = Array.Empty<string>();
    }

    public static class FunkenwerkSharedSession
    {
        private const string SharedVaultFileName = "shared_session_vault.dpapi";
        private const string SharedAesKeyFileName = "shared_session_key.dpapi";
        private const int Pbkdf2Iterations = 210_000;

        private static readonly byte[] EntropyBytes = Encoding.UTF8.GetBytes("Seelenfunke.Funkenwerk.SharedSession.v2");
        private static readonly object FileLock = new();

        public static string GetSharedDataDirectory()
        {
            string dir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Funkenwerk");
            EnsureRestrictedDirectory(dir);
            return dir;
        }

        private static void EnsureRestrictedDirectory(string dirPath)
        {
            if (!Directory.Exists(dirPath))
            {
                Directory.CreateDirectory(dirPath);
            }
            try
            {
                var currentSid = WindowsIdentity.GetCurrent().User;
                if (currentSid != null)
                {
                    var sec = new DirectorySecurity();
                    sec.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                    sec.AddAccessRule(new FileSystemAccessRule(
                        currentSid,
                        FileSystemRights.FullControl,
                        InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                        PropagationFlags.None,
                        AccessControlType.Allow));
                    new DirectoryInfo(dirPath).SetAccessControl(sec);
                }
            }
            catch { }
        }

        private static byte[] GetOrCreateAesKey()
        {
            string keyPath = Path.Combine(GetSharedDataDirectory(), SharedAesKeyFileName);
            if (File.Exists(keyPath))
            {
                try
                {
                    byte[] encKey = File.ReadAllBytes(keyPath);
                    byte[] rawKey = ProtectedData.Unprotect(encKey, EntropyBytes, DataProtectionScope.CurrentUser);
                    if (rawKey.Length == 32) return rawKey;
                }
                catch { }
            }

            byte[] freshKey = RandomNumberGenerator.GetBytes(32);
            byte[] protectedKey = ProtectedData.Protect(freshKey, EntropyBytes, DataProtectionScope.CurrentUser);
            File.WriteAllBytes(keyPath, protectedKey);
            return freshKey;
        }

        public static SharedSessionDto Load()
        {
            lock (FileLock)
            {
                string vaultPath = Path.Combine(GetSharedDataDirectory(), SharedVaultFileName);
                if (!File.Exists(vaultPath))
                {
                    return BootstrapFromLegacyFunkenwerkVault();
                }

                byte[]? key = null;
                try
                {
                    byte[] dpapiWrapped = File.ReadAllBytes(vaultPath);
                    byte[] envelope = ProtectedData.Unprotect(dpapiWrapped, EntropyBytes, DataProtectionScope.CurrentUser);
                    if (envelope.Length < 29) return new SharedSessionDto();

                    key = GetOrCreateAesKey();
                    ReadOnlySpan<byte> nonce = envelope.AsSpan(0, 12);
                    ReadOnlySpan<byte> tag = envelope.AsSpan(12, 16);
                    ReadOnlySpan<byte> cipher = envelope.AsSpan(28);
                    byte[] plain = new byte[cipher.Length];
                    using (var aes = new AesGcm(key, 16))
                    {
                        aes.Decrypt(nonce, cipher, tag, plain);
                    }
                    string json = Encoding.UTF8.GetString(plain);
                    return JsonSerializer.Deserialize<SharedSessionDto>(json) ?? new SharedSessionDto();
                }
                catch
                {
                    return BootstrapFromLegacyFunkenwerkVault();
                }
                finally
                {
                    if (key != null) CryptographicOperations.ZeroMemory(key);
                }
            }
        }

        private static SharedSessionDto BootstrapFromLegacyFunkenwerkVault()
        {
            try
            {
                string legacyLayout = Path.Combine(GetSharedDataDirectory(), "layout_settings.json");
                if (!File.Exists(legacyLayout)) return new SharedSessionDto();
                string raw = File.ReadAllText(legacyLayout, Encoding.UTF8);
                using var doc = JsonDocument.Parse(raw);
                var root = doc.RootElement;
                string url = root.TryGetProperty("ApiBaseUrl", out var u) && u.ValueKind == JsonValueKind.String
                    ? (u.GetString() ?? "https://stage.mein-seelenfunke.de/api")
                    : "https://stage.mein-seelenfunke.de/api";
                string email = root.TryGetProperty("SavedAuthEmail", out var em) && em.ValueKind == JsonValueKind.String
                    ? (em.GetString() ?? "kontakt@mein-seelenfunke.de")
                    : "kontakt@mein-seelenfunke.de";
                return new SharedSessionDto
                {
                    ApiBaseUrl = url,
                    UserEmail = email
                };
            }
            catch
            {
                return new SharedSessionDto();
            }
        }

        public static void Save(SharedSessionDto dto)
        {
            lock (FileLock)
            {
                dto.UpdatedAtUtc = DateTime.UtcNow.ToString("O");
                byte[]? key = null;
                try
                {
                    key = GetOrCreateAesKey();
                    byte[] plain = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(dto));
                    byte[] nonce = RandomNumberGenerator.GetBytes(12);
                    byte[] tag = new byte[16];
                    byte[] cipher = new byte[plain.Length];
                    using (var aes = new AesGcm(key, 16))
                    {
                        aes.Encrypt(nonce, plain, cipher, tag);
                    }
                    byte[] envelope = new byte[28 + cipher.Length];
                    Buffer.BlockCopy(nonce, 0, envelope, 0, 12);
                    Buffer.BlockCopy(tag, 0, envelope, 12, 16);
                    Buffer.BlockCopy(cipher, 0, envelope, 28, cipher.Length);
                    byte[] dpapiWrapped = ProtectedData.Protect(envelope, EntropyBytes, DataProtectionScope.CurrentUser);

                    string vaultPath = Path.Combine(GetSharedDataDirectory(), SharedVaultFileName);
                    string tmpPath = vaultPath + ".tmp";
                    File.WriteAllBytes(tmpPath, dpapiWrapped);
                    File.Move(tmpPath, vaultPath, overwrite: true);
                }
                catch { }
                finally
                {
                    if (key != null) CryptographicOperations.ZeroMemory(key);
                }
            }
        }

        public static void SaveVerifiedOnlineSession(
            string apiBaseUrl,
            string bearerToken,
            string email,
            string? plainPasswordForOfflineVerifier)
        {
            var current = Load();
            current.ApiBaseUrl = string.IsNullOrWhiteSpace(apiBaseUrl) ? current.ApiBaseUrl : apiBaseUrl.Trim();
            if (!string.IsNullOrWhiteSpace(bearerToken))
            {
                current.BearerToken = bearerToken.Trim();
            }
            if (!string.IsNullOrWhiteSpace(email))
            {
                current.UserEmail = email.Trim();
            }
            current.HasVerifiedOnlineOnce = true;
            current.IsSessionLocked = false;

            if (!string.IsNullOrEmpty(plainPasswordForOfflineVerifier))
            {
                byte[] salt = RandomNumberGenerator.GetBytes(16);
                byte[] verifier = Rfc2898DeriveBytes.Pbkdf2(
                    Encoding.UTF8.GetBytes(plainPasswordForOfflineVerifier),
                    salt,
                    Pbkdf2Iterations,
                    HashAlgorithmName.SHA512,
                    32);
                current.PasswordSaltB64 = Convert.ToBase64String(salt);
                current.PasswordVerifierB64 = Convert.ToBase64String(verifier);
                CryptographicOperations.ZeroMemory(verifier);
            }

            Save(current);
        }

        public static void SetLockedState(bool isLocked, bool clearToken = false)
        {
            var current = Load();
            current.IsSessionLocked = isLocked;
            if (clearToken)
            {
                current.BearerToken = "";
            }
            Save(current);
        }

        public static bool VerifyOfflinePassword(string? email, string? candidatePassword)
        {
            if (string.IsNullOrEmpty(candidatePassword)) return false;
            var current = Load();
            if (!current.HasVerifiedOnlineOnce ||
                string.IsNullOrWhiteSpace(current.PasswordSaltB64) ||
                string.IsNullOrWhiteSpace(current.PasswordVerifierB64))
            {
                return false;
            }

            if (!string.IsNullOrWhiteSpace(email) &&
                !string.IsNullOrWhiteSpace(current.UserEmail) &&
                !string.Equals(email.Trim(), current.UserEmail.Trim(), StringComparison.OrdinalIgnoreCase))
            {
                return false;
            }

            try
            {
                byte[] salt = Convert.FromBase64String(current.PasswordSaltB64);
                byte[] expected = Convert.FromBase64String(current.PasswordVerifierB64);
                if (salt.Length < 12 || expected.Length < 16) return false;
                byte[] actual = Rfc2898DeriveBytes.Pbkdf2(
                    Encoding.UTF8.GetBytes(candidatePassword),
                    salt,
                    Pbkdf2Iterations,
                    HashAlgorithmName.SHA512,
                    expected.Length);
                bool ok = CryptographicOperations.FixedTimeEquals(actual, expected);
                CryptographicOperations.ZeroMemory(actual);
                return ok;
            }
            catch
            {
                return false;
            }
        }

        public static void SaveRolePermissions(string? userType, IEnumerable<string>? allowedSpaces, IEnumerable<string>? allowedModules)
        {
            var current = Load();
            current.UserType = string.IsNullOrWhiteSpace(userType) ? "employee" : userType.Trim().ToLowerInvariant();
            if (string.Equals(current.UserType, "admin", StringComparison.OrdinalIgnoreCase))
            {
                current.AllowedSpaces = new[] { "*" };
                current.AllowedModules = new[] { "*" };
            }
            else
            {
                current.AllowedSpaces = (allowedSpaces ?? new[] { "launcher" })
                    .Where(s => !string.IsNullOrWhiteSpace(s))
                    .Select(s => s.Trim().ToLowerInvariant())
                    .Distinct(StringComparer.OrdinalIgnoreCase)
                    .ToArray();
                current.AllowedModules = (allowedModules ?? Array.Empty<string>())
                    .Where(s => !string.IsNullOrWhiteSpace(s))
                    .Select(s => s.Trim().ToLowerInvariant())
                    .Distinct(StringComparer.OrdinalIgnoreCase)
                    .ToArray();
            }
            Save(current);
        }

        public static bool IsAdminSession(SharedSessionDto? dto = null)
        {
            var s = dto ?? Load();
            return string.Equals(s.UserType, "admin", StringComparison.OrdinalIgnoreCase);
        }

        public static bool IsSpaceAllowed(string? spaceKey, SharedSessionDto? dto = null)
        {
            if (string.IsNullOrWhiteSpace(spaceKey)) return false;
            string norm = spaceKey.Trim().ToLowerInvariant();
            if (norm == "launcher") return true;
            var s = dto ?? Load();
            if (IsAdminSession(s)) return true;
            if (s.AllowedSpaces == null || s.AllowedSpaces.Length == 0) return false;
            return s.AllowedSpaces.Any(x => x == "*" || string.Equals(x, norm, StringComparison.OrdinalIgnoreCase));
        }

        public static bool IsModuleAllowed(string? moduleKey, SharedSessionDto? dto = null)
        {
            if (string.IsNullOrWhiteSpace(moduleKey)) return false;
            string norm = moduleKey.Trim().ToLowerInvariant();
            var s = dto ?? Load();
            if (IsAdminSession(s)) return true;
            if (s.AllowedModules == null || s.AllowedModules.Length == 0) return false;
            return s.AllowedModules.Any(x => x == "*" || string.Equals(x, norm, StringComparison.OrdinalIgnoreCase));
        }
    }

    public static class CollectionModuleRegistry
    {
        [DllImport("user32.dll")]
        private static extern bool SetForegroundWindow(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

        [DllImport("user32.dll")]
        private static extern bool IsIconic(IntPtr hWnd);

        private const int SW_RESTORE = 9;

        public static IReadOnlyList<CollectionModuleSpec> Modules => AllModules;

        public static readonly IReadOnlyList<CollectionModuleSpec> AllModules = new[]
        {
            new CollectionModuleSpec
            {
                Key = "3d",
                ShortTitle = "3D",
                FullName = "Funkenwerk 3D",
                FolderName = "Funkenwerk 3D",
                ExeName = "Funkenwerk3D.exe",
                ProcessName = "Funkenwerk3D",
                IconFileName = "funkenwerk_3d.ico",
                VectorPathData = "M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5",
                Keywords = new[] { "3d", "glb", "gltf", "stl", "obj", "step", "modell", "cad", "mesh", "vorschau", "objekt" }
            },
            new CollectionModuleSpec
            {
                Key = "browser",
                ShortTitle = "Browser",
                FullName = "Funkenwerk Browser",
                FolderName = "Funkenwerk Browser",
                ExeName = "FunkenwerkBrowser.exe",
                ProcessName = "FunkenwerkBrowser",
                IconFileName = "funkenwerk_browser.ico",
                VectorPathData = "M12 2a10 10 0 100 20 10 10 0 000-20zM2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z",
                Keywords = new[] { "browser", "internet", "web", "webseite", "suche", "chrome", "edge", "surfen", "url", "recherche" }
            },
            new CollectionModuleSpec
            {
                Key = "craft",
                ShortTitle = "Craft",
                FullName = "Funkenwerk Craft",
                FolderName = "Funkenwerk Craft",
                ExeName = "FunkenwerkCraft.exe",
                ProcessName = "FunkenwerkCraft",
                IconFileName = "funkenwerk_craft.ico",
                VectorPathData = "M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z",
                Keywords = new[] { "craft", "kreativ", "animation", "schnitt", "artcraft", "filmcraft", "soundcraft", "design", "grafik", "atelier" }
            },
            new CollectionModuleSpec
            {
                Key = "drucker",
                ShortTitle = "Drucker",
                FullName = "Funkenwerk Drucker",
                FolderName = "Funkenwerk Drucker",
                ExeName = "FunkenwerkDrucker.exe",
                ProcessName = "FunkenwerkDrucker",
                IconFileName = "funkenwerk_drucker.ico",
                VectorPathData = "M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z",
                Keywords = new[] { "drucker", "drucken", "scan", "scannen", "scanner", "kopie", "kopieren", "hp", "papier", "a4" }
            },
            new CollectionModuleSpec
            {
                Key = "ftp",
                ShortTitle = "FTP",
                FullName = "Funkenwerk FTP",
                FolderName = "Funkenwerk FTP",
                ExeName = "FunkenwerkFtp.exe",
                ProcessName = "FunkenwerkFtp",
                IconFileName = "funkenwerk_ftp.ico",
                VectorPathData = "M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4",
                Keywords = new[] { "ftp", "sftp", "ftps", "winscp", "filezilla", "server", "upload", "download", "dateitransfer", "mittwald" }
            },
            new CollectionModuleSpec
            {
                Key = "kamera",
                ShortTitle = "Kamera",
                FullName = "Funkenwerk Kamera",
                FolderName = "Funkenwerk Kamera",
                ExeName = "FunkenwerkKamera.exe",
                ProcessName = "FunkenwerkKamera",
                IconFileName = "funkenwerk_kamera.ico",
                VectorPathData = "M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z",
                Keywords = new[] { "kamera", "streaming", "webcam", "obs", "greenscreen", "chroma", "stimme", "mikrofon", "aufnahme", "studio", "deepfake" }
            },
            new CollectionModuleSpec
            {
                Key = "marketing",
                ShortTitle = "Marketing",
                FullName = "Funkenwerk Marketing",
                FolderName = "Funkenwerk Marketing",
                ExeName = "FunkenwerkMarketing.exe",
                ProcessName = "FunkenwerkMarketing",
                IconFileName = "funkenwerk_marketing.ico",
                VectorPathData = "M13 10V3L4 14h7v7l9-11h-7z",
                Keywords = new[] { "marketing", "content", "planer", "instagram", "tiktok", "reel", "viral", "social", "kampagne", "beitrag", "skript" }
            },
            new CollectionModuleSpec
            {
                Key = "os",
                ShortTitle = "OS",
                FullName = "Funkenwerk OS",
                FolderName = "Funkenwerk OS",
                ExeName = "FunkenwerkOS.exe",
                ProcessName = "FunkenwerkOS",
                IconFileName = "funkenwerk_os.ico",
                VectorPathData = "M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z",
                Keywords = new[] { "os", "betriebssystem", "system", "hardware", "monitor", "festung", "desktop", "diagnose" }
            },
            new CollectionModuleSpec
            {
                Key = "pdf",
                ShortTitle = "PDF",
                FullName = "Funkenwerk PDF",
                FolderName = "Funkenwerk PDF",
                ExeName = "FunkenwerkPdf.exe",
                ProcessName = "FunkenwerkPdf",
                IconFileName = "funkenwerk_pdf.ico",
                VectorPathData = "M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z M14 2v6h6 M16 13H8 M16 17H8",
                Keywords = new[] { "pdf", "acrobat", "formular", "unterschrift", "unterschreiben", "signieren", "ausfuellen", "dokument", "vertrag" }
            },
            new CollectionModuleSpec
            {
                Key = "player",
                ShortTitle = "Player",
                FullName = "Funkenwerk Player",
                FolderName = "Funkenwerk Player",
                ExeName = "FunkenwerkPlayer.exe",
                ProcessName = "FunkenwerkPlayer",
                IconFileName = "funkenwerk_player.ico",
                VectorPathData = "M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z",
                Keywords = new[] { "player", "musik", "musikplayer", "soundplayer", "playlist", "fokus", "audio", "mp3", "mp4", "vlc", "mediaplayer", "lied", "songs" }
            },
            new CollectionModuleSpec
            {
                Key = "remote",
                ShortTitle = "Remote",
                FullName = "Funkenwerk Remote",
                FolderName = "Funkenwerk Remote",
                ExeName = "FunkenwerkRemote.exe",
                ProcessName = "FunkenwerkRemote",
                IconFileName = "funkenwerk_remote.ico",
                VectorPathData = "M2 5a2 2 0 012-2h11a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm7 12h11a2 2 0 002-2V8a2 2 0 00-2-2h-2M12 19v2m-4 0h8",
                Keywords = new[] { "remote", "rdp", "remotedesktop", "mstsc", "minipc", "mini-pc", "werkstatt", "bildschirm", "fernwartung", "fernsteuerung", "xrdp", "vnc" }
            },
            new CollectionModuleSpec
            {
                Key = "smarthome",
                ShortTitle = "SmartHome",
                FullName = "Funkenwerk SmartHome",
                FolderName = "Funkenwerk SmartHome",
                ExeName = "FunkenwerkSmartHome.exe",
                ProcessName = "FunkenwerkSmartHome",
                IconFileName = "funkenwerk_smarthome.ico",
                VectorPathData = "M3 10.5L12 3l9 7.5M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9M9 20v-6a1 1 0 011-1h4a1 1 0 011 1v6",
                Keywords = new[] { "smarthome", "smart", "haus", "rollladen", "rolladen", "rolllaeden", "connexoon", "connecoon", "window", "fenster", "tahoma", "somfy", "jalousie", "beschattung", "licht", "steckdose", "tapo", "ueberwachungskamera", "onvif", "rtsp" }
            },
            new CollectionModuleSpec
            {
                Key = "ssh",
                ShortTitle = "SSH",
                FullName = "Funkenwerk SSH",
                FolderName = "Funkenwerk SSH",
                ExeName = "FunkenwerkSsh.exe",
                ProcessName = "FunkenwerkSsh",
                IconFileName = "funkenwerk_ssh.ico",
                VectorPathData = "M4 17l6-6-6-6M12 19h8",
                Keywords = new[] { "ssh", "putty", "terminal", "konsole", "shell", "linux", "befehl", "kommandozeile" }
            },
            new CollectionModuleSpec
            {
                Key = "tresor",
                ShortTitle = "Tresor",
                FullName = "Funkenwerk Tresor",
                FolderName = "Funkenwerk Tresor",
                ExeName = "FunkenwerkTresor.exe",
                ProcessName = "FunkenwerkTresor",
                IconFileName = "funkenwerk_tresor.ico",
                VectorPathData = "M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z",
                Keywords = new[] { "tresor", "passwort", "passwoerter", "keepass", "kdbx", "2fa", "totp", "authentifizierung", "schluessel", "zugangsdaten", "login" }
            },
            new CollectionModuleSpec
            {
                Key = "video",
                ShortTitle = "Video",
                FullName = "Funkenwerk Video",
                FolderName = "Funkenwerk Video",
                ExeName = "FunkenwerkVideo.exe",
                ProcessName = "FunkenwerkVideo",
                IconFileName = "funkenwerk_video.ico",
                VectorPathData = "M7 4v16l13-8L7 4z",
                Keywords = new[] { "video", "videograph", "kurzvideo", "ffmpeg", "effekt", "untertitel", "clip", "export" }
            }
        };

        public static string ResolveCollectionRoot()
        {
            string? baseDir = AppContext.BaseDirectory?.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
            if (!string.IsNullOrEmpty(baseDir))
            {
                var parent = Directory.GetParent(baseDir);
                if (parent != null)
                {
                    if (string.Equals(parent.Name, "Module", StringComparison.OrdinalIgnoreCase) && parent.Parent != null)
                        return parent.Parent.FullName;
                    if (string.Equals(parent.Name, "Funkenwerk Collection", StringComparison.OrdinalIgnoreCase))
                        return parent.FullName;
                }
            }

            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            if (!string.IsNullOrWhiteSpace(localAppData))
            {
                string programsCol = Path.Combine(localAppData, "Programs", "Funkenwerk Collection");
                if (Directory.Exists(programsCol)) return programsCol;
            }
            if (Directory.Exists(@"C:\Users\konta\AppData\Local\Programs\Funkenwerk Collection"))
                return @"C:\Users\konta\AppData\Local\Programs\Funkenwerk Collection";

            string desktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
            if (!string.IsNullOrWhiteSpace(desktop))
            {
                string candidate = Path.Combine(desktop, "Funkenwerk Collection");
                if (Directory.Exists(candidate)) return candidate;
            }
            if (Directory.Exists(@"C:\Users\konta\Desktop\Funkenwerk Collection"))
                return @"C:\Users\konta\Desktop\Funkenwerk Collection";
            if (Directory.Exists(@"C:\Funkenwerk Collection"))
                return @"C:\Funkenwerk Collection";

            return !string.IsNullOrWhiteSpace(localAppData)
                ? Path.Combine(localAppData, "Programs", "Funkenwerk Collection")
                : @"C:\Users\konta\AppData\Local\Programs\Funkenwerk Collection";
        }

        public static string ResolveModuleDirectory(string folderName)
        {
            string root = ResolveCollectionRoot();
            string modular = Path.Combine(root, "Module", folderName);
            if (Directory.Exists(modular)) return modular;
            string legacy = Path.Combine(root, folderName);
            if (Directory.Exists(legacy)) return legacy;
            return modular;
        }

        public static string ResolveModuleExePath(CollectionModuleSpec spec)
        {
            string root = ResolveCollectionRoot();
            string modularExe = Path.Combine(root, "Module", spec.FolderName, spec.ExeName);
            if (File.Exists(modularExe)) return modularExe;
            string legacyExe = Path.Combine(root, spec.FolderName, spec.ExeName);
            if (File.Exists(legacyExe)) return legacyExe;
            return modularExe;
        }

        public static string ResolveModuleIconPath(CollectionModuleSpec spec)
        {
            string dir = ResolveModuleDirectory(spec.FolderName);
            string png = Path.Combine(dir, Path.ChangeExtension(spec.IconFileName, ".png"));
            if (File.Exists(png)) return png;
            string ico = Path.Combine(dir, spec.IconFileName);
            if (File.Exists(ico)) return ico;
            return "";
        }

        public static bool IsModuleRunning(CollectionModuleSpec spec)
        {
            try
            {
                return Process.GetProcessesByName(spec.ProcessName).Length > 0;
            }
            catch
            {
                return false;
            }
        }

        public static (bool Ok, bool FocusedExisting, string Message) LaunchOrFocusModule(string keyOrAlias, string? validatedExtraArg = null)
        {
            var spec = FindByKeyOrAlias(keyOrAlias);
            if (spec == null)
                return (false, false, $"Modul '{keyOrAlias}' ist nicht registriert.");

            if (!FunkenwerkSharedSession.IsModuleAllowed(spec.Key))
                return (false, false, $"Kein Zugriff auf '{spec.FullName}' für dieses Mitarbeiter-Konto.");

            if (string.IsNullOrWhiteSpace(validatedExtraArg))
            {
                try
                {
                    foreach (var p in Process.GetProcessesByName(spec.ProcessName))
                    {
                        using (p)
                        {
                            if (p.MainWindowHandle != IntPtr.Zero)
                            {
                                if (IsIconic(p.MainWindowHandle))
                                    ShowWindow(p.MainWindowHandle, SW_RESTORE);
                                SetForegroundWindow(p.MainWindowHandle);
                                return (true, true, $"{spec.FullName} wurde in den Vordergrund geholt.");
                            }
                        }
                    }
                }
                catch { }
            }

            string exePath = ResolveModuleExePath(spec);
            if (!File.Exists(exePath))
                return (false, false, $"{spec.FullName} wurde unter '{exePath}' nicht gefunden.");

            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = exePath,
                    WorkingDirectory = Path.GetDirectoryName(exePath) ?? ResolveCollectionRoot(),
                    UseShellExecute = false
                };
                if (!string.IsNullOrWhiteSpace(validatedExtraArg))
                {
                    psi.ArgumentList.Add(validatedExtraArg);
                }
                Process.Start(psi);
                return (true, false, $"{spec.FullName} wurde gestartet.");
            }
            catch (Exception ex)
            {
                return (false, false, $"Start fehlgeschlagen: {ex.Message}");
            }
        }

        public static CollectionModuleSpec? FindByKeyOrAlias(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return null;
            string norm = NormalizeSearchToken(raw.Trim());
            var direct = AllModules.FirstOrDefault(m =>
                NormalizeSearchToken(m.Key) == norm ||
                NormalizeSearchToken(m.ShortTitle) == norm ||
                NormalizeSearchToken(m.FullName) == norm);
            if (direct != null) return direct;

            return FilterAndRankModules(raw).FirstOrDefault();
        }

        public static IReadOnlyList<CollectionModuleSpec> FilterAndRankModules(string? rawQuery)
        {
            if (string.IsNullOrWhiteSpace(rawQuery))
                return AllModules;

            string trimmed = rawQuery.Trim();
            string[] tokens = trimmed
                .Split(new[] { ' ', ',', ';', '-', '_', '/' }, StringSplitOptions.RemoveEmptyEntries)
                .Select(NormalizeSearchToken)
                .Where(t => t.Length > 0)
                .ToArray();

            if (tokens.Length == 0)
                return AllModules;

            var scored = new List<(CollectionModuleSpec Spec, int Score)>();
            foreach (var spec in AllModules)
            {
                int totalScore = 0;
                bool allTokensMatched = true;

                string normTitle = NormalizeSearchToken(spec.ShortTitle);
                string normKey = NormalizeSearchToken(spec.Key);
                string[] normKeywords = spec.Keywords.Select(NormalizeSearchToken).ToArray();

                foreach (string tok in tokens)
                {
                    int bestTokenScore = 0;

                    if (normTitle == tok || normKey == tok)
                        bestTokenScore = Math.Max(bestTokenScore, 120);
                    else if (normTitle.StartsWith(tok, StringComparison.Ordinal) || normKey.StartsWith(tok, StringComparison.Ordinal))
                        bestTokenScore = Math.Max(bestTokenScore, 95);
                    else if (normTitle.Contains(tok, StringComparison.Ordinal))
                        bestTokenScore = Math.Max(bestTokenScore, 75);

                    foreach (string kw in normKeywords)
                    {
                        if (kw == tok)
                            bestTokenScore = Math.Max(bestTokenScore, 90);
                        else if (kw.StartsWith(tok, StringComparison.Ordinal))
                            bestTokenScore = Math.Max(bestTokenScore, 72);
                        else if (kw.Contains(tok, StringComparison.Ordinal) || (tok.Length >= 4 && tok.Contains(kw, StringComparison.Ordinal)))
                            bestTokenScore = Math.Max(bestTokenScore, 55);
                        else if (tok.Length >= 3)
                        {
                            int maxDist = tok.Length >= 5 ? 2 : 1;
                            int dist = ComputeLevenshteinDistance(tok, kw.Length > tok.Length + 2 ? kw.Substring(0, Math.Min(kw.Length, tok.Length + 1)) : kw);
                            int fullDist = ComputeLevenshteinDistance(tok, kw);
                            int minDist = Math.Min(dist, fullDist);
                            if (minDist <= maxDist)
                                bestTokenScore = Math.Max(bestTokenScore, 42 - (minDist * 10));
                        }
                    }

                    if (bestTokenScore == 0 && tok.Length >= 3)
                    {
                        int maxTitleDist = tok.Length >= 5 ? 2 : 1;
                        int titleDist = ComputeLevenshteinDistance(tok, normTitle);
                        if (titleDist <= maxTitleDist)
                            bestTokenScore = 48 - (titleDist * 10);
                    }

                    if (bestTokenScore == 0)
                    {
                        allTokensMatched = false;
                        break;
                    }
                    totalScore += bestTokenScore;
                }

                if (allTokensMatched && totalScore > 0)
                {
                    scored.Add((spec, totalScore));
                }
            }

            return scored
                .OrderByDescending(x => x.Score)
                .ThenBy(x => x.Spec.ShortTitle, StringComparer.OrdinalIgnoreCase)
                .Select(x => x.Spec)
                .ToList();
        }

        public static string NormalizeSearchToken(string input)
        {
            if (string.IsNullOrEmpty(input)) return "";
            var sb = new StringBuilder(input.Length + 4);
            foreach (char raw in input.ToLowerInvariant())
            {
                switch (raw)
                {
                    case 'ä': sb.Append("ae"); break;
                    case 'ö': sb.Append("oe"); break;
                    case 'ü': sb.Append("ue"); break;
                    case 'ß': sb.Append("ss"); break;
                    default:
                        if (char.IsLetterOrDigit(raw)) sb.Append(raw);
                        break;
                }
            }
            return sb.ToString();
        }

        public static int ComputeLevenshteinDistance(string a, string b)
        {
            if (a == b) return 0;
            if (a.Length == 0) return b.Length;
            if (b.Length == 0) return a.Length;

            int[] prev = new int[b.Length + 1];
            int[] curr = new int[b.Length + 1];
            for (int j = 0; j <= b.Length; j++) prev[j] = j;

            for (int i = 1; i <= a.Length; i++)
            {
                curr[0] = i;
                for (int j = 1; j <= b.Length; j++)
                {
                    int cost = a[i - 1] == b[j - 1] ? 0 : 1;
                    curr[j] = Math.Min(
                        Math.Min(curr[j - 1] + 1, prev[j] + 1),
                        prev[j - 1] + cost);
                }
                (prev, curr) = (curr, prev);
            }
            return prev[b.Length];
        }
    }

    public sealed class SavedMailCredentialEntry
    {
        public string Email { get; set; } = "";
        public string Name { get; set; } = "";
        public string ImapHost { get; set; } = "";
        public int ImapPort { get; set; } = 993;
        public string ImapEncryption { get; set; } = "ssl";
        public string SmtpHost { get; set; } = "";
        public int SmtpPort { get; set; } = 465;
        public string SmtpEncryption { get; set; } = "ssl";
        public string? Signature { get; set; }
        public bool IsDefault { get; set; }
        public bool IsCommercial { get; set; } = true;
        public string PlainPassword { get; set; } = "";
        public string UpdatedAtUtc { get; set; } = "";
    }

    /// <summary>
    /// Encrypted local DPAPI + AES-256-GCM vault for mailbox credentials so passwords & accounts
    /// never need to be re-entered across test reseeds (migrate:fresh --seed) or environment switches.
    /// </summary>
    public static class FunkenwerkMailCredentialsVault
    {
        private const string VaultFileName = "mail_accounts_vault.dpapi";
        private const string KeyFileName = "mail_accounts_key.dpapi";
        private static readonly byte[] MailVaultEntropy = Encoding.UTF8.GetBytes("Funkenwerk::MailCredentialsVault::v1::2026");
        private static readonly object VaultLock = new();

        private static byte[] GetOrCreateVaultAesKey()
        {
            string dir = FunkenwerkSharedSession.GetSharedDataDirectory();
            string keyPath = Path.Combine(dir, KeyFileName);
            if (File.Exists(keyPath))
            {
                try
                {
                    byte[] encKey = File.ReadAllBytes(keyPath);
                    byte[] rawKey = ProtectedData.Unprotect(encKey, MailVaultEntropy, DataProtectionScope.CurrentUser);
                    if (rawKey.Length == 32) return rawKey;
                }
                catch { }
            }

            byte[] fresh = RandomNumberGenerator.GetBytes(32);
            byte[] wrapped = ProtectedData.Protect(fresh, MailVaultEntropy, DataProtectionScope.CurrentUser);
            File.WriteAllBytes(keyPath, wrapped);
            return fresh;
        }

        public static List<SavedMailCredentialEntry> LoadAll()
        {
            lock (VaultLock)
            {
                string vaultPath = Path.Combine(FunkenwerkSharedSession.GetSharedDataDirectory(), VaultFileName);
                if (!File.Exists(vaultPath)) return new List<SavedMailCredentialEntry>();

                byte[]? key = null;
                byte[]? plain = null;
                try
                {
                    byte[] dpapiWrapped = File.ReadAllBytes(vaultPath);
                    byte[] envelope = ProtectedData.Unprotect(dpapiWrapped, MailVaultEntropy, DataProtectionScope.CurrentUser);
                    if (envelope.Length < 29) return new List<SavedMailCredentialEntry>();

                    key = GetOrCreateVaultAesKey();
                    ReadOnlySpan<byte> nonce = envelope.AsSpan(0, 12);
                    ReadOnlySpan<byte> tag = envelope.AsSpan(12, 16);
                    ReadOnlySpan<byte> cipher = envelope.AsSpan(28);
                    plain = new byte[cipher.Length];
                    using (var aes = new AesGcm(key, 16))
                    {
                        aes.Decrypt(nonce, cipher, tag, plain);
                    }
                    string json = Encoding.UTF8.GetString(plain);
                    return JsonSerializer.Deserialize<List<SavedMailCredentialEntry>>(json) ?? new List<SavedMailCredentialEntry>();
                }
                catch
                {
                    return new List<SavedMailCredentialEntry>();
                }
                finally
                {
                    if (key != null) CryptographicOperations.ZeroMemory(key);
                    if (plain != null) CryptographicOperations.ZeroMemory(plain);
                }
            }
        }

        public static void SaveAccount(SavedMailCredentialEntry entry)
        {
            if (entry == null || string.IsNullOrWhiteSpace(entry.Email)) return;
            lock (VaultLock)
            {
                var all = LoadAll();
                string normEmail = entry.Email.Trim().ToLowerInvariant();
                var existing = all.FirstOrDefault(x => string.Equals(x.Email.Trim(), normEmail, StringComparison.OrdinalIgnoreCase));

                string effectivePw = !string.IsNullOrWhiteSpace(entry.PlainPassword)
                    ? entry.PlainPassword
                    : (existing?.PlainPassword ?? "");

                if (string.IsNullOrWhiteSpace(effectivePw))
                    return;

                if (existing != null)
                {
                    all.Remove(existing);
                }

                entry.Email = entry.Email.Trim();
                entry.PlainPassword = effectivePw;
                entry.UpdatedAtUtc = DateTime.UtcNow.ToString("O");
                all.Add(entry);

                SaveAllInternal(all);
            }
        }

        public static void RemoveAccount(string email)
        {
            if (string.IsNullOrWhiteSpace(email)) return;
            lock (VaultLock)
            {
                var all = LoadAll();
                int removed = all.RemoveAll(x => string.Equals(x.Email.Trim(), email.Trim(), StringComparison.OrdinalIgnoreCase));
                if (removed > 0)
                {
                    SaveAllInternal(all);
                }
            }
        }

        public static bool TryGetByEmail(string? email, out SavedMailCredentialEntry? found)
        {
            found = null;
            if (string.IsNullOrWhiteSpace(email)) return false;
            var all = LoadAll();
            found = all.FirstOrDefault(x => string.Equals(x.Email.Trim(), email.Trim(), StringComparison.OrdinalIgnoreCase));
            return found != null && !string.IsNullOrWhiteSpace(found.PlainPassword);
        }

        private static void SaveAllInternal(List<SavedMailCredentialEntry> list)
        {
            byte[]? key = null;
            byte[]? plain = null;
            try
            {
                key = GetOrCreateVaultAesKey();
                plain = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(list));
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] tag = new byte[16];
                byte[] cipher = new byte[plain.Length];
                using (var aes = new AesGcm(key, 16))
                {
                    aes.Encrypt(nonce, plain, cipher, tag);
                }
                byte[] envelope = new byte[28 + cipher.Length];
                Buffer.BlockCopy(nonce, 0, envelope, 0, 12);
                Buffer.BlockCopy(tag, 0, envelope, 12, 16);
                Buffer.BlockCopy(cipher, 0, envelope, 28, cipher.Length);

                byte[] dpapiWrapped = ProtectedData.Protect(envelope, MailVaultEntropy, DataProtectionScope.CurrentUser);
                string vaultPath = Path.Combine(FunkenwerkSharedSession.GetSharedDataDirectory(), VaultFileName);
                File.WriteAllBytes(vaultPath, dpapiWrapped);
            }
            finally
            {
                if (key != null) CryptographicOperations.ZeroMemory(key);
                if (plain != null) CryptographicOperations.ZeroMemory(plain);
            }
        }
    }

    /// <summary>
    /// Self-heals Windows HKCU file associations, icons, FriendlyAppNames & OpenWith lists
    /// after modularizing Funkenwerk Collection into Module\&lt;App&gt;\.
    /// </summary>
    public static class CollectionFileAssociationHealer
    {
        [DllImport("shell32.dll")]
        private static extern void SHChangeNotify(int wEventId, uint uFlags, IntPtr dwItem1, IntPtr dwItem2);

        private const int SHCNE_ASSOCCHANGED = 0x08000000;
        private const uint SHCNF_IDLIST = 0x0000;

        public static void EnsureCollectionAssociationsRegistered()
        {
            try
            {
                string pdfDir = CollectionModuleRegistry.ResolveModuleDirectory("Funkenwerk PDF");
                string pdfExe = Path.Combine(pdfDir, "FunkenwerkPdf.exe");
                string pdfIco = Path.Combine(pdfDir, "funkenwerk_pdf.ico");
                if (File.Exists(pdfExe))
                {
                    RegisterModuleAppAssociation(
                        progId: "FunkenwerkPdf.Document",
                        friendlyTypeName: "PDF-Dokument (Funkenwerk PDF)",
                        friendlyAppName: "Funkenwerk PDF",
                        description: "Funkenwerk PDF · Oeffnen, Ausfuellen, Unterschreiben & Bearbeiten",
                        capabilitiesSubPath: @"Software\Funkenwerk\FunkenwerkPdf\Capabilities",
                        exeName: "FunkenwerkPdf.exe",
                        exePath: pdfExe,
                        icoPath: pdfIco,
                        extensions: new[] { ".pdf" },
                        setDefaultProgIdOnExt: true);
                }

                string threeDDir = CollectionModuleRegistry.ResolveModuleDirectory("Funkenwerk 3D");
                string threeDExe = Path.Combine(threeDDir, "Funkenwerk3D.exe");
                string threeDIco = Path.Combine(threeDDir, "funkenwerk_3d.ico");
                if (File.Exists(threeDExe))
                {
                    RegisterModuleAppAssociation(
                        progId: "Funkenwerk3D.Model",
                        friendlyTypeName: "3D-Modell (Funkenwerk 3D)",
                        friendlyAppName: "Funkenwerk 3D",
                        description: "Funkenwerk 3D · Standard-Viewer fuer .glb, .gltf, .stl, .obj, .3mf, .ply & .fbx",
                        capabilitiesSubPath: @"Software\Funkenwerk\Funkenwerk3D\Capabilities",
                        exeName: "Funkenwerk3D.exe",
                        exePath: threeDExe,
                        icoPath: threeDIco,
                        extensions: new[] { ".glb", ".gltf", ".stl", ".obj", ".ply", ".3mf", ".fbx" },
                        setDefaultProgIdOnExt: true);
                }

                string playerDir = CollectionModuleRegistry.ResolveModuleDirectory("Funkenwerk Player");
                string playerExe = Path.Combine(playerDir, "FunkenwerkPlayer.exe");
                string playerIco = Path.Combine(playerDir, "funkenwerk_player.ico");
                if (File.Exists(playerExe))
                {
                    RegisterModuleAppAssociation(
                        progId: "FunkenwerkPlayer.Media",
                        friendlyTypeName: "Mediendatei (Funkenwerk Player)",
                        friendlyAppName: "Funkenwerk Player",
                        description: "Funkenwerk Player · Musik- & Video-Mediaplayer",
                        capabilitiesSubPath: @"Software\FunkenwerkPlayer\Capabilities",
                        exeName: "FunkenwerkPlayer.exe",
                        exePath: playerExe,
                        icoPath: playerIco,
                        extensions: new[]
                        {
                            ".mp4", ".mov", ".mkv", ".webm", ".avi", ".wmv", ".m4v", ".mpg", ".mpeg",
                            ".flv", ".ts", ".3gp", ".mp3", ".wav", ".flac", ".m4a", ".ogg", ".aac", ".wma"
                        },
                        setDefaultProgIdOnExt: true);
                }

                string druckerDir = CollectionModuleRegistry.ResolveModuleDirectory("Funkenwerk Drucker");
                string druckerExe = Path.Combine(druckerDir, "FunkenwerkDrucker.exe");
                string druckerIco = Path.Combine(druckerDir, "funkenwerk_drucker.ico");
                if (File.Exists(druckerExe))
                {
                    RegisterModuleAppAssociation(
                        progId: "FunkenwerkDrucker.PrintJob",
                        friendlyTypeName: "Mit Funkenwerk Drucker drucken",
                        friendlyAppName: "Funkenwerk Drucker",
                        description: "Funkenwerk Drucker · Drucken, Scannen & Kopieren",
                        capabilitiesSubPath: @"Software\Funkenwerk\FunkenwerkDrucker\Capabilities",
                        exeName: "FunkenwerkDrucker.exe",
                        exePath: druckerExe,
                        icoPath: druckerIco,
                        extensions: new[] { ".pdf", ".png", ".jpg", ".jpeg" },
                        setDefaultProgIdOnExt: false);
                }

                string tresorDir = CollectionModuleRegistry.ResolveModuleDirectory("Funkenwerk Tresor");
                string tresorExe = Path.Combine(tresorDir, "FunkenwerkTresor.exe");
                string tresorIco = Path.Combine(tresorDir, "funkenwerk_tresor.ico");
                if (File.Exists(tresorExe))
                {
                    RegisterModuleAppAssociation(
                        progId: "FunkenwerkTresor.Vault",
                        friendlyTypeName: "Passwort-Tresor (Funkenwerk Tresor)",
                        friendlyAppName: "Funkenwerk Tresor",
                        description: "Funkenwerk Tresor · Lokaler Passwort- & TOTP-Tresor (.kdbx / .keyx)",
                        capabilitiesSubPath: @"Software\Funkenwerk\FunkenwerkTresor\Capabilities",
                        exeName: "FunkenwerkTresor.exe",
                        exePath: tresorExe,
                        icoPath: tresorIco,
                        extensions: new[] { ".kdbx", ".keyx" },
                        setDefaultProgIdOnExt: true);

                    using var kdbxOwp = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Explorer\FileExts\.kdbx\OpenWithProgids");
                    kdbxOwp?.SetValue("FunkenwerkTresor.Vault", Array.Empty<byte>(), RegistryValueKind.Binary);
                    using var keyxOwp = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Explorer\FileExts\.keyx\OpenWithProgids");
                    keyxOwp?.SetValue("FunkenwerkTresor.Vault", Array.Empty<byte>(), RegistryValueKind.Binary);
                }

                // Prevent main launcher Funkenwerk.exe from cluttering file OpenWith dialogs
                using (var mainAppKey = Registry.CurrentUser.CreateSubKey(@"Software\Classes\Applications\Funkenwerk.exe"))
                {
                    mainAppKey?.SetValue("NoOpenWith", "");
                }

                CleanStaleMuiCacheAndPdfOpenWith();
                SHChangeNotify(SHCNE_ASSOCCHANGED, SHCNF_IDLIST, IntPtr.Zero, IntPtr.Zero);
            }
            catch { }
        }

        private static void RegisterModuleAppAssociation(
            string progId,
            string friendlyTypeName,
            string friendlyAppName,
            string description,
            string capabilitiesSubPath,
            string exeName,
            string exePath,
            string icoPath,
            string[] extensions,
            bool setDefaultProgIdOnExt)
        {
            string iconSpec = File.Exists(icoPath) ? $"\"{icoPath}\",0" : $"\"{exePath}\",0";
            string openCmd = $"\"{exePath}\" \"%1\"";

            using (var cls = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{progId}"))
            {
                cls?.SetValue("", friendlyTypeName);
                cls?.SetValue("FriendlyTypeName", friendlyTypeName);
                cls?.SetValue("FriendlyAppName", friendlyAppName);
                using (var ik = cls?.CreateSubKey("DefaultIcon")) ik?.SetValue("", iconSpec);
                using (var ck = cls?.CreateSubKey(@"shell\open\command")) ck?.SetValue("", openCmd);
            }

            using (var appKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\Applications\{exeName}"))
            {
                appKey?.SetValue("FriendlyAppName", friendlyAppName);
                using (var ik = appKey?.CreateSubKey("DefaultIcon")) ik?.SetValue("", iconSpec);
                using (var ck = appKey?.CreateSubKey(@"shell\open\command")) ck?.SetValue("", openCmd);
                using (var st = appKey?.CreateSubKey("SupportedTypes"))
                {
                    if (st != null)
                    {
                        foreach (string oldVal in st.GetValueNames()) st.DeleteValue(oldVal, false);
                        foreach (string ext in extensions) st.SetValue(ext, "");
                    }
                }
            }

            using (var capKey = Registry.CurrentUser.CreateSubKey(capabilitiesSubPath))
            {
                capKey?.SetValue("ApplicationName", friendlyAppName);
                capKey?.SetValue("ApplicationDescription", description);
                capKey?.SetValue("ApplicationIcon", iconSpec);
                using (var fa = capKey?.CreateSubKey("FileAssociations"))
                {
                    if (fa != null)
                    {
                        foreach (string ext in extensions) fa.SetValue(ext, progId);
                    }
                }
            }

            using (var regApps = Registry.CurrentUser.CreateSubKey(@"Software\RegisteredApplications"))
            {
                regApps?.SetValue(friendlyAppName, capabilitiesSubPath);
            }

            foreach (string ext in extensions)
            {
                using var extKey = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{ext}");
                if (setDefaultProgIdOnExt)
                {
                    extKey?.SetValue("", progId);
                }
                using var owp = extKey?.CreateSubKey("OpenWithProgids");
                owp?.SetValue(progId, Array.Empty<byte>(), RegistryValueKind.Binary);
            }
        }

        private static void CleanStaleMuiCacheAndPdfOpenWith()
        {
            try
            {
                using var mui = Registry.CurrentUser.OpenSubKey(@"Software\Classes\Local Settings\Software\Microsoft\Windows\Shell\MuiCache", writable: true);
                if (mui != null)
                {
                    foreach (string name in mui.GetValueNames())
                    {
                        if (name.Contains(@"Funkenwerk Collection\", StringComparison.OrdinalIgnoreCase) &&
                            !name.Contains(@"Funkenwerk Collection\Module\", StringComparison.OrdinalIgnoreCase) &&
                            !name.Contains(@"Funkenwerk Collection\Funkenwerk\", StringComparison.OrdinalIgnoreCase))
                        {
                            mui.DeleteValue(name, false);
                        }
                    }
                }
            }
            catch { }

            try
            {
                using var pdfOw = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\Explorer\FileExts\.pdf\OpenWithList", writable: true);
                if (pdfOw != null)
                {
                    string mru = pdfOw.GetValue("MRUList")?.ToString() ?? "";
                    foreach (string vn in pdfOw.GetValueNames().ToList())
                    {
                        if (vn.Equals("MRUList", StringComparison.OrdinalIgnoreCase)) continue;
                        string val = pdfOw.GetValue(vn)?.ToString() ?? "";
                        if (val.Equals("Funkenwerk.exe", StringComparison.OrdinalIgnoreCase) ||
                            val.Equals("Funkenwerk3D.exe", StringComparison.OrdinalIgnoreCase) ||
                            val.Equals("FunkenwerkPlayer.exe", StringComparison.OrdinalIgnoreCase))
                        {
                            pdfOw.DeleteValue(vn, false);
                            mru = mru.Replace(vn, "", StringComparison.OrdinalIgnoreCase);
                        }
                    }
                    pdfOw.SetValue("MRUList", mru);
                }

                using var pdfOwp = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Explorer\FileExts\.pdf\OpenWithProgids");
                pdfOwp?.SetValue("FunkenwerkPdf.Document", Array.Empty<byte>(), RegistryValueKind.Binary);
            }
            catch { }
        }
    }
}

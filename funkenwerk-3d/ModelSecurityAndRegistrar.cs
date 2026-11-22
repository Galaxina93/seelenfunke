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

namespace Funkenwerk3D
{
    public sealed class Funkenwerk3DSettings
    {
        public static string? TestOverrideBaseDir { get; set; }

        public bool DarkMode { get; set; } = true;
        public bool ShowGrid { get; set; } = true;
        public bool ShowDimensions { get; set; } = false;
        public bool ShowSidebar { get; set; } = true;
        public string BackdropPreset { get; set; } = "auto";
        public string UnitMode { get; set; } = "auto";
        public double Exposure { get; set; } = 1.05;
        public List<string> RecentFiles { get; set; } = new();

        public static string GetAppDataDirectory()
        {
            string dir = !string.IsNullOrWhiteSpace(TestOverrideBaseDir)
                ? TestOverrideBaseDir
                : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Funkenwerk3D");

            EnsureSecureDirectory(dir);
            return dir;
        }

        public static string GetWebViewUserDataDirectory()
        {
            string dir = Path.Combine(GetAppDataDirectory(), "WebView2RuntimeData");
            EnsureSecureDirectory(dir);
            return dir;
        }

        public static void EnsureSecureDirectory(string dirPath)
        {
            Directory.CreateDirectory(dirPath);
            try
            {
                var currentSid = WindowsIdentity.GetCurrent().User;
                if (currentSid == null) return;

                var dirInfo = new DirectoryInfo(dirPath);
                var security = new DirectorySecurity();
                security.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                security.AddAccessRule(new FileSystemAccessRule(
                    currentSid,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));

                var systemSid = new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null);
                security.AddAccessRule(new FileSystemAccessRule(
                    systemSid,
                    FileSystemRights.FullControl,
                    InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                    PropagationFlags.None,
                    AccessControlType.Allow));

                dirInfo.SetAccessControl(security);
            }
            catch { }
        }

        public static void LockDownFileAcl(string filePath)
        {
            try
            {
                var currentSid = WindowsIdentity.GetCurrent().User;
                if (currentSid == null || !File.Exists(filePath)) return;

                var fi = new FileInfo(filePath);
                var security = new FileSecurity();
                security.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
                security.AddAccessRule(new FileSystemAccessRule(currentSid, FileSystemRights.FullControl, AccessControlType.Allow));
                security.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, AccessControlType.Allow));
                fi.SetAccessControl(security);
            }
            catch { }
        }

        public static Funkenwerk3DSettings Load()
        {
            try
            {
                string file = Path.Combine(GetAppDataDirectory(), "settings.json");
                if (File.Exists(file))
                {
                    string json = File.ReadAllText(file, Encoding.UTF8);
                    var loaded = JsonSerializer.Deserialize<Funkenwerk3DSettings>(json);
                    if (loaded != null)
                    {
                        loaded.Exposure = Math.Clamp(loaded.Exposure, 0.2, 3.0);
                        loaded.RecentFiles ??= new List<string>();
                        return loaded;
                    }
                }
            }
            catch { }
            return new Funkenwerk3DSettings();
        }

        public void Save()
        {
            try
            {
                string dir = GetAppDataDirectory();
                string file = Path.Combine(dir, "settings.json");
                string tmp = file + ".tmp";
                string json = JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(tmp, json, new UTF8Encoding(false));
                LockDownFileAcl(tmp);
                File.Move(tmp, file, overwrite: true);
                LockDownFileAcl(file);
            }
            catch { }
        }

        public void AddRecentFile(string fullPath)
        {
            if (string.IsNullOrWhiteSpace(fullPath)) return;
            RecentFiles.RemoveAll(p => string.Equals(p, fullPath, StringComparison.OrdinalIgnoreCase));
            RecentFiles.Insert(0, fullPath);
            if (RecentFiles.Count > 10)
                RecentFiles.RemoveRange(10, RecentFiles.Count - 10);
        }
    }

    public static class ModelSecurityValidator
    {
        public const long MaxModelFileBytes = 512L * 1024L * 1024L;     // 512 MB cap for 3D models
        public const long MaxSiblingAssetBytes = 128L * 1024L * 1024L;  // 128 MB cap per texture / .bin

        public static readonly HashSet<string> SupportedModelExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".glb", ".gltf", ".stl", ".obj", ".ply", ".3mf", ".fbx"
        };

        public static readonly HashSet<string> AllowedSiblingAssetExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".bin", ".mtl", ".png", ".jpg", ".jpeg", ".webp", ".bmp", ".gif", ".ktx2"
        };

        private static readonly HashSet<string> ReservedDosNames = new(StringComparer.OrdinalIgnoreCase)
        {
            "CON", "PRN", "AUX", "NUL",
            "COM1", "COM2", "COM3", "COM4", "COM5", "COM6", "COM7", "COM8", "COM9",
            "LPT1", "LPT2", "LPT3", "LPT4", "LPT5", "LPT6", "LPT7", "LPT8", "LPT9"
        };

        public static bool TryValidateModelFile(string? rawPath, out string canonicalPath, out string errorMessage)
        {
            canonicalPath = "";
            errorMessage = "";

            if (string.IsNullOrWhiteSpace(rawPath))
            {
                errorMessage = "Kein Dateipfad angegeben.";
                return false;
            }

            string trimmed = rawPath.Trim();
            if (trimmed.StartsWith("-", StringComparison.Ordinal) ||
                trimmed.StartsWith("/", StringComparison.Ordinal) ||
                trimmed.StartsWith(@"\\?\", StringComparison.Ordinal) ||
                trimmed.StartsWith(@"\\.\", StringComparison.Ordinal))
            {
                errorMessage = "Unzulässiges Pfad- oder Flag-Präfix blockiert.";
                return false;
            }

            // Block NTFS Alternate Data Streams (any ':' after drive letter index 1)
            int colonIdx = trimmed.IndexOf(':', 2);
            if (colonIdx >= 0)
            {
                errorMessage = "NTFS Alternate Data Streams sind aus Sicherheitsgründen gesperrt.";
                return false;
            }

            try
            {
                canonicalPath = Path.GetFullPath(trimmed);
            }
            catch
            {
                errorMessage = "Ungültiger Dateipfad.";
                return false;
            }

            string stem = Path.GetFileNameWithoutExtension(canonicalPath);
            if (ReservedDosNames.Contains(stem))
            {
                errorMessage = "Reservierte System-Gerätenamen sind blockiert.";
                return false;
            }

            string ext = Path.GetExtension(canonicalPath);
            if (!SupportedModelExtensions.Contains(ext))
            {
                errorMessage = $"Dateiendung '{ext}' ist kein unterstütztes 3D-Format (.glb, .gltf, .stl, .obj, .ply, .3mf, .fbx).";
                return false;
            }

            if (!File.Exists(canonicalPath))
            {
                errorMessage = "Die 3D-Datei wurde nicht gefunden.";
                return false;
            }

            if (HasReparsePointInPath(canonicalPath))
            {
                errorMessage = "Symlinks und NTFS-Junctions sind aus Sicherheitsgründen blockiert.";
                return false;
            }

            var fi = new FileInfo(canonicalPath);
            if (fi.Length <= 0)
            {
                errorMessage = "Die 3D-Datei ist leer (0 Bytes).";
                return false;
            }

            if (fi.Length > MaxModelFileBytes)
            {
                errorMessage = "Die 3D-Datei überschreitet das Sicherheitslimit von 512 MB.";
                return false;
            }

            // Binary GLB Structural Header Validation (Magic 'glTF' 0x46546C67, Version 2, Length check)
            if (string.Equals(ext, ".glb", StringComparison.OrdinalIgnoreCase))
            {
                Span<byte> hdr = stackalloc byte[12];
                using var fs = new FileStream(canonicalPath, FileMode.Open, FileAccess.Read, FileShare.Read);
                if (fs.Read(hdr) < 12)
                {
                    errorMessage = "Ungültige .glb-Datei (Header zu kurz).";
                    return false;
                }

                uint magic = BitConverter.ToUInt32(hdr.Slice(0, 4));
                uint version = BitConverter.ToUInt32(hdr.Slice(4, 4));
                uint declaredLength = BitConverter.ToUInt32(hdr.Slice(8, 4));

                if (magic != 0x46546C67u) // "glTF"
                {
                    errorMessage = "Ungültige .glb-Signatur (kein glTF-Binärheader).";
                    return false;
                }

                if (version != 2u)
                {
                    errorMessage = $"Nicht unterstützte GLB-Version ({version}). Erwartet wird glTF 2.0.";
                    return false;
                }

                if (declaredLength < 20u || declaredLength > fi.Length)
                {
                    errorMessage = "Beschädigter .glb-Container (Längenangabe inkonsistent).";
                    return false;
                }
            }

            return true;
        }

        public static bool TryResolveSandboxedSiblingAsset(
            string activeModelCanonicalPath,
            string decodedRelativeSubPath,
            out string resolvedAssetPath,
            out string mimeType)
        {
            resolvedAssetPath = "";
            mimeType = "application/octet-stream";

            if (string.IsNullOrWhiteSpace(activeModelCanonicalPath) || string.IsNullOrWhiteSpace(decodedRelativeSubPath))
                return false;

            string? modelDir = Path.GetDirectoryName(activeModelCanonicalPath);
            if (string.IsNullOrWhiteSpace(modelDir))
                return false;

            string cleanRel = decodedRelativeSubPath.Replace('/', Path.DirectorySeparatorChar).TrimStart(Path.DirectorySeparatorChar);
            if (string.IsNullOrWhiteSpace(cleanRel) || cleanRel.IndexOf(':', StringComparison.Ordinal) >= 0)
                return false;

            // Primary model file itself
            if (string.Equals(cleanRel, Path.GetFileName(activeModelCanonicalPath), StringComparison.OrdinalIgnoreCase))
            {
                if (!File.Exists(activeModelCanonicalPath) || HasReparsePointInPath(activeModelCanonicalPath))
                    return false;

                resolvedAssetPath = activeModelCanonicalPath;
                mimeType = GetMimeTypeForExtension(Path.GetExtension(activeModelCanonicalPath));
                return true;
            }

            string baseDirWithSep = Path.GetFullPath(modelDir).TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
            string candidate;
            try
            {
                candidate = Path.GetFullPath(Path.Combine(baseDirWithSep, cleanRel));
            }
            catch
            {
                return false;
            }

            // Strict directory jail: sibling .bin / textures must reside inside the opened model's directory (or subdirectory)
            if (!candidate.StartsWith(baseDirWithSep, StringComparison.OrdinalIgnoreCase))
                return false;

            string ext = Path.GetExtension(candidate);
            if (!AllowedSiblingAssetExtensions.Contains(ext))
                return false;

            string stem = Path.GetFileNameWithoutExtension(candidate);
            if (ReservedDosNames.Contains(stem))
                return false;

            if (!File.Exists(candidate) || HasReparsePointInPath(candidate, stopAtDirectory: modelDir))
                return false;

            var fi = new FileInfo(candidate);
            if (fi.Length <= 0 || fi.Length > MaxSiblingAssetBytes)
                return false;

            resolvedAssetPath = candidate;
            mimeType = GetMimeTypeForExtension(ext);
            return true;
        }

        public static bool HasReparsePointInPath(string fullPath, string? stopAtDirectory = null)
        {
            try
            {
                string? stopCanonical = string.IsNullOrWhiteSpace(stopAtDirectory)
                    ? null
                    : Path.GetFullPath(stopAtDirectory).TrimEnd(Path.DirectorySeparatorChar);

                var fi = new FileInfo(fullPath);
                if (fi.Exists && (fi.Attributes & FileAttributes.ReparsePoint) != 0)
                    return true;

                DirectoryInfo? cur = fi.Directory;
                while (cur != null && cur.Exists)
                {
                    if ((cur.Attributes & FileAttributes.ReparsePoint) != 0)
                        return true;

                    if (stopCanonical != null &&
                        string.Equals(cur.FullName.TrimEnd(Path.DirectorySeparatorChar), stopCanonical, StringComparison.OrdinalIgnoreCase))
                    {
                        break;
                    }

                    // Stop before root drive / UNC share root
                    if (cur.Parent == null)
                        break;

                    cur = cur.Parent;
                }
            }
            catch
            {
                return true;
            }
            return false;
        }

        public static string GetMimeTypeForExtension(string ext)
        {
            return ext.ToLowerInvariant() switch
            {
                ".glb" => "model/gltf-binary",
                ".gltf" => "model/gltf+json",
                ".stl" => "model/stl",
                ".obj" => "model/obj",
                ".mtl" => "model/mtl",
                ".3mf" => "model/3mf",
                ".ply" => "application/octet-stream",
                ".fbx" => "application/octet-stream",
                ".bin" => "application/octet-stream",
                ".png" => "image/png",
                ".jpg" or ".jpeg" => "image/jpeg",
                ".webp" => "image/webp",
                ".bmp" => "image/bmp",
                ".gif" => "image/gif",
                ".ktx2" => "image/ktx2",
                ".html" => "text/html; charset=utf-8",
                ".js" => "text/javascript; charset=utf-8",
                ".wasm" => "application/wasm",
                ".css" => "text/css; charset=utf-8",
                _ => "application/octet-stream"
            };
        }

        public static bool FixedTimeEqualsAscii(string a, string b)
        {
            byte[] ba = Encoding.ASCII.GetBytes(a ?? "");
            byte[] bb = Encoding.ASCII.GetBytes(b ?? "");
            return ba.Length == bb.Length && CryptographicOperations.FixedTimeEquals(ba, bb);
        }
    }

    public static class WindowsDefault3DRegistrar
    {
        public const string ProgId = "Funkenwerk3D.Model";
        public const string AppRegisteredName = "Funkenwerk 3D";

        public static readonly string[] PrimaryDefaultExtensions =
        {
            ".glb", ".gltf", ".obj", ".ply", ".fbx"
        };

        public static readonly string[] SlicerExtensions =
        {
            ".stl", ".3mf"
        };

        public static readonly string[] AllSupportedExtensions =
        {
            ".glb", ".gltf", ".stl", ".obj", ".ply", ".3mf", ".fbx"
        };

        [DllImport("shell32.dll", CharSet = CharSet.Auto, SetLastError = true)]
        private static extern void SHChangeNotify(uint wEventId, uint uFlags, IntPtr dwItem1, IntPtr dwItem2);

        private const uint SHCNE_ASSOCCHANGED = 0x08000000;
        private const uint SHCNF_IDLIST = 0x0000;

        public static string GetExecutablePath()
        {
            string? processPath = Environment.ProcessPath;
            if (!string.IsNullOrWhiteSpace(processPath) &&
                File.Exists(processPath) &&
                !string.Equals(Path.GetFileName(processPath), "dotnet.exe", StringComparison.OrdinalIgnoreCase))
            {
                return Path.GetFullPath(processPath);
            }

            return Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "Funkenwerk3D.exe"));
        }

        public static bool RegisterAsWindows3DViewer(
            out string message,
            bool setDefaultForPrimary = true,
            bool setDefaultForSlicerFormats = false,
            string softwarePrefix = "Software",
            bool notifyShell = true)
        {
            try
            {
                string exePath = GetExecutablePath();
                string icoPath = Path.Combine(Path.GetDirectoryName(exePath) ?? AppContext.BaseDirectory, "funkenwerk_3d.ico");
                string iconSpec = File.Exists(icoPath) ? $"\"{icoPath}\",0" : $"\"{exePath}\",0";
                string openCommand = $"\"{exePath}\" \"%1\"";

                // 1. Register ProgID
                using (var progKey = Registry.CurrentUser.CreateSubKey($@"{softwarePrefix}\Classes\{ProgId}"))
                {
                    progKey.SetValue("", "3D-Modell (Funkenwerk 3D)");
                    progKey.SetValue("FriendlyTypeName", "3D-Modell (Funkenwerk 3D)");
                    progKey.SetValue("FriendlyAppName", AppRegisteredName);

                    using var iconKey = progKey.CreateSubKey("DefaultIcon");
                    iconKey.SetValue("", iconSpec);

                    using var cmdKey = progKey.CreateSubKey(@"shell\open\command");
                    cmdKey.SetValue("", openCommand);
                }

                // 2. Register Application Entry
                using (var appKey = Registry.CurrentUser.CreateSubKey($@"{softwarePrefix}\Classes\Applications\Funkenwerk3D.exe"))
                {
                    appKey.SetValue("FriendlyAppName", AppRegisteredName);
                    using (var iconKey = appKey.CreateSubKey("DefaultIcon"))
                    {
                        iconKey.SetValue("", iconSpec);
                    }
                    using (var cmdKey = appKey.CreateSubKey(@"shell\open\command"))
                    {
                        cmdKey.SetValue("", openCommand);
                    }
                    using (var typesKey = appKey.CreateSubKey("SupportedTypes"))
                    {
                        foreach (var ext in AllSupportedExtensions)
                            typesKey.SetValue(ext, "");
                    }
                }

                // 3. Register Capabilities for Windows 10/11 Default Apps UI
                string capPath = $@"{softwarePrefix}\Funkenwerk\Funkenwerk3D\Capabilities";
                using (var capKey = Registry.CurrentUser.CreateSubKey(capPath))
                {
                    capKey.SetValue("ApplicationName", AppRegisteredName);
                    capKey.SetValue("ApplicationDescription", "Funkenwerk 3D · Standard-Viewer für .glb, .gltf, .stl, .obj, .3mf, .ply & .fbx");
                    capKey.SetValue("ApplicationIcon", iconSpec);

                    using var assocKey = capKey.CreateSubKey("FileAssociations");
                    foreach (var ext in AllSupportedExtensions)
                        assocKey.SetValue(ext, ProgId);
                }

                // 4. Register in RegisteredApplications
                using (var regAppsKey = Registry.CurrentUser.CreateSubKey($@"{softwarePrefix}\RegisteredApplications"))
                {
                    regAppsKey.SetValue(AppRegisteredName, capPath);
                }

                // 5. Register per-extension Classes & OpenWithProgids
                foreach (var ext in AllSupportedExtensions)
                {
                    bool isPrimary = PrimaryDefaultExtensions.Contains(ext, StringComparer.OrdinalIgnoreCase);
                    bool shouldSetDefault = (isPrimary && setDefaultForPrimary) || (!isPrimary && setDefaultForSlicerFormats);

                    using var extKey = Registry.CurrentUser.CreateSubKey($@"{softwarePrefix}\Classes\{ext}");
                    if (shouldSetDefault)
                    {
                        extKey.SetValue("", ProgId);
                    }

                    using var openWithKey = extKey.CreateSubKey("OpenWithProgids");
                    openWithKey.SetValue(ProgId, Array.Empty<byte>(), RegistryValueKind.Binary);

                    // Clean up orphaned dead Windows 3D Viewer AppX stub if it no longer exists in HKCR
                    if (shouldSetDefault && string.Equals(softwarePrefix, "Software", StringComparison.OrdinalIgnoreCase))
                    {
                        TryClearOrphanedExplorerUserChoice(ext);
                    }
                }

                if (notifyShell)
                {
                    SHChangeNotify(SHCNE_ASSOCCHANGED, SHCNF_IDLIST, IntPtr.Zero, IntPtr.Zero);
                }

                message = "Funkenwerk 3D wurde für .glb, .gltf, .obj, .ply, .fbx (sowie Öffnen mit für .stl & .3mf) registriert.";
                return true;
            }
            catch (Exception ex)
            {
                message = $"Registrierung fehlgeschlagen: {ex.Message}";
                return false;
            }
        }

        private static void TryClearOrphanedExplorerUserChoice(string ext)
        {
            try
            {
                string fileExtPath = $@"Software\Microsoft\Windows\CurrentVersion\Explorer\FileExts\{ext}";
                using var ucKey = Registry.CurrentUser.OpenSubKey($@"{fileExtPath}\UserChoice", writable: false);
                string? currentProgId = ucKey?.GetValue("ProgId") as string;
                if (string.IsNullOrWhiteSpace(currentProgId))
                    return;

                // Only clear if the referenced AppX ProgId is dead / uninstalled in HKEY_CLASSES_ROOT
                using var hkcrCheck = Registry.ClassesRoot.OpenSubKey(currentProgId);
                if (hkcrCheck == null)
                {
                    using var parentKey = Registry.CurrentUser.OpenSubKey(fileExtPath, writable: true);
                    parentKey?.DeleteSubKeyTree("UserChoice", throwOnMissingSubKey: false);
                }
            }
            catch
            {
                // Standard OS protection applies when UserChoice is actively locked; user can confirm via ms-settings:defaultapps
            }
        }

        public static void OpenWindowsDefaultAppsSettings()
        {
            try
            {
                Process.Start(new ProcessStartInfo
                {
                    FileName = "ms-settings:defaultapps?registeredAppUser=Funkenwerk%203D",
                    UseShellExecute = true
                });
            }
            catch
            {
                try
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = "ms-settings:defaultapps",
                        UseShellExecute = true
                    });
                }
                catch { }
            }
        }
    }
}

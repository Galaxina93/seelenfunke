using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Runtime.InteropServices.ComTypes;
using System.Security.AccessControl;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading.Tasks;
using FluentFTP;
using Microsoft.Win32;

namespace FunkenwerkFtp
{
    public sealed class FtpConnectionProfile
    {
        public string Id { get; set; } = Guid.NewGuid().ToString();
        public string Name { get; set; } = "";
        public string Protocol { get; set; } = "SFTP"; // SFTP, FTPS, FTP
        public string Host { get; set; } = "";
        public int Port { get; set; } = 22;
        public string Username { get; set; } = "";
        public string EncryptedPassword { get; set; } = "";
        public string PrivateKeyPath { get; set; } = "";
        public string RemoteDirectory { get; set; } = "/";
        public string LocalDirectory { get; set; } = "";
        public string Source { get; set; } = "Manuell";

        [JsonIgnore]
        public string DisplayLabel => string.IsNullOrWhiteSpace(Username)
            ? $"{Name} ({Protocol}://{Host}:{Port})"
            : $"{Name} ({Username}@{Host}:{Port})";
    }

    public static class FunkenwerkFtpSecurity
    {
        private const string VAULT_PREFIX = "FWAES256GCM1:";
        private static readonly byte[] AppEntropy = Encoding.UTF8.GetBytes("Funkenwerk.Ftp.SecurityVault.v1!MeinSeelenfunke");
        private static readonly byte[] HandoffEntropy = Encoding.UTF8.GetBytes("Funkenwerk.FtpToSsh.Handoff.v1!MeinSeelenfunke");

        private static readonly HashSet<string> ReservedWindowsDeviceNames = new(StringComparer.OrdinalIgnoreCase)
        {
            "CON", "PRN", "AUX", "NUL",
            "CONIN$", "CONOUT$", "CLOCK$",
            "COM0", "COM1", "COM2", "COM3", "COM4", "COM5", "COM6", "COM7", "COM8", "COM9",
            "LPT0", "LPT1", "LPT2", "LPT3", "LPT4", "LPT5", "LPT6", "LPT7", "LPT8", "LPT9"
        };

        private static string? _isolatedVaultOverride;
        private static string? _isolatedSshVaultOverride;
        private static bool _isSelfTestMode;

        public static void EnableSelfTestIsolation(string isolatedFtpVaultDir, string isolatedSshVaultDir)
        {
            if (string.IsNullOrWhiteSpace(isolatedFtpVaultDir))
                throw new ArgumentException("Isoliertes Testverzeichnis fehlt.", nameof(isolatedFtpVaultDir));
            if (string.IsNullOrWhiteSpace(isolatedSshVaultDir))
                throw new ArgumentException("Isoliertes SSH-Testverzeichnis fehlt.", nameof(isolatedSshVaultDir));

            _isolatedVaultOverride = Path.GetFullPath(isolatedFtpVaultDir);
            _isolatedSshVaultOverride = Path.GetFullPath(isolatedSshVaultDir);
            _isSelfTestMode = true;

            Directory.CreateDirectory(_isolatedVaultOverride);
            LockDownDirectoryAcl(_isolatedVaultOverride);
            Directory.CreateDirectory(_isolatedSshVaultOverride);
            LockDownDirectoryAcl(_isolatedSshVaultOverride);
        }

        public static void ResetSelfTestIsolation()
        {
            _isolatedVaultOverride = null;
            _isolatedSshVaultOverride = null;
            _isSelfTestMode = false;
        }

        public static string VaultDir
        {
            get
            {
                string dir = !string.IsNullOrWhiteSpace(_isolatedVaultOverride)
                    ? _isolatedVaultOverride!
                    : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkFtp");

                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        private static string SshHandoffVaultDir
        {
            get
            {
                string dir = !string.IsNullOrWhiteSpace(_isolatedSshVaultOverride)
                    ? _isolatedSshVaultOverride!
                    : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkSsh");

                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        private static string VaultKeyFile => Path.Combine(VaultDir, "master.dpapi");
        private static string ProfilesFile => Path.Combine(VaultDir, "profiles_vault.json");
        private static string KnownHostsFile => Path.Combine(VaultDir, "known_hosts_sha256.json");
        private static string HandoffTicketFile => Path.Combine(SshHandoffVaultDir, "handoff_ticket.dpapi");

        // =====================================================================
        // STRICT CURRENT-USER NTFS ACL LOCKDOWN
        // =====================================================================
        private static SecurityIdentifier GetCurrentUserSid()
        {
            using var identity = WindowsIdentity.GetCurrent();
            return identity.User ?? throw new InvalidOperationException("Aktuelle Windows-Benutzer-SID konnte nicht ermittelt werden.");
        }

        public static void LockDownDirectoryAcl(string dirPath)
        {
            if (string.IsNullOrWhiteSpace(dirPath) || !Directory.Exists(dirPath)) return;

            var sid = GetCurrentUserSid();
            var dirInfo = new DirectoryInfo(dirPath);
            var security = dirInfo.GetAccessControl(AccessControlSections.Access);

            security.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
            var existingRules = security.GetAccessRules(includeExplicit: true, includeInherited: false, typeof(SecurityIdentifier));
            foreach (FileSystemAccessRule rule in existingRules)
            {
                security.RemoveAccessRuleSpecific(rule);
            }

            security.AddAccessRule(new FileSystemAccessRule(
                sid,
                FileSystemRights.FullControl,
                InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit,
                PropagationFlags.None,
                AccessControlType.Allow));

            dirInfo.SetAccessControl(security);
        }

        public static void LockDownFileAcl(string filePath)
        {
            if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath)) return;

            var sid = GetCurrentUserSid();
            var fileInfo = new FileInfo(filePath);
            var security = fileInfo.GetAccessControl(AccessControlSections.Access);

            security.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
            var existingRules = security.GetAccessRules(includeExplicit: true, includeInherited: false, typeof(SecurityIdentifier));
            foreach (FileSystemAccessRule rule in existingRules)
            {
                security.RemoveAccessRuleSpecific(rule);
            }

            security.AddAccessRule(new FileSystemAccessRule(
                sid,
                FileSystemRights.FullControl,
                InheritanceFlags.None,
                PropagationFlags.None,
                AccessControlType.Allow));

            fileInfo.SetAccessControl(security);
        }

        public static bool VerifyStrictCurrentUserAcl(string path, bool isDirectory)
        {
            var sid = GetCurrentUserSid();
            FileSystemSecurity security = isDirectory
                ? new DirectoryInfo(path).GetAccessControl(AccessControlSections.Access)
                : new FileInfo(path).GetAccessControl(AccessControlSections.Access);

            if (!security.AreAccessRulesProtected)
                return false;

            var rules = security.GetAccessRules(includeExplicit: true, includeInherited: true, typeof(SecurityIdentifier));
            if (rules.Count != 1)
                return false;

            if (rules[0] is not FileSystemAccessRule ace)
                return false;

            return ace.IdentityReference == sid &&
                   ace.AccessControlType == AccessControlType.Allow &&
                   (ace.FileSystemRights & FileSystemRights.FullControl) == FileSystemRights.FullControl;
        }

        // =====================================================================
        // SINGLE-USE NONCE-BOUND SSH HANDOFF TICKET WRITER
        // =====================================================================
        public static string WriteSshHandoffTicket(
            string name,
            string host,
            int port,
            string username,
            string password,
            string privateKeyPath,
            string remoteDirectory)
        {
            string cleanHost = SanitizeHost(host);
            if (string.IsNullOrWhiteSpace(cleanHost))
                throw new ArgumentException("Ungültiger Hostname für SSH-Handoff.", nameof(host));

            string cleanRemote = string.IsNullOrWhiteSpace(remoteDirectory) ? "/" : remoteDirectory.Trim();
            if (cleanRemote.IndexOfAny(new[] { '\r', '\n', '\0' }) >= 0)
                throw new ArgumentException("Steuerzeichen im Remote-Verzeichnis blockiert.", nameof(remoteDirectory));

            string cleanUser = (username ?? "").Trim();
            string cleanKey = ResolveOpenSshKeyPath(privateKeyPath);

            // Map Mittwald SFTP-only user (sftp-...) to the real interactive SSH container user
            if (cleanHost.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase) &&
                (string.IsNullOrWhiteSpace(cleanUser) || cleanUser.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase)))
            {
                cleanUser = cleanRemote.Contains("worker-stage", StringComparison.OrdinalIgnoreCase)
                    ? "kontakt@mein-seelenfunke.de@a-iurgq8"
                    : "kontakt@mein-seelenfunke.de@a-lc6tkm";
            }

            string sshVaultDir = SshHandoffVaultDir;
            LockDownDirectoryAcl(sshVaultDir);
            string handoffFile = HandoffTicketFile;
            string nonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));

            var payload = new Dictionary<string, string>
            {
                ["Name"] = name ?? "",
                ["Host"] = cleanHost,
                ["Port"] = (port <= 0 || port == 21 ? 22 : port).ToString(),
                ["Username"] = cleanUser,
                ["Password"] = password ?? "",
                ["PrivateKeyPath"] = cleanKey,
                ["RemoteDirectory"] = cleanRemote,
                ["Nonce"] = nonce,
                ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
            };

            byte[] plainBytes = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(payload));
            try
            {
                byte[] encrypted = ProtectedData.Protect(plainBytes, HandoffEntropy, DataProtectionScope.CurrentUser);
                File.WriteAllBytes(handoffFile, encrypted);
                LockDownFileAcl(handoffFile);
                return nonce;
            }
            finally
            {
                CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        public static void WipeAndDeleteSshHandoffTicket()
        {
            string handoffFile = HandoffTicketFile;
            if (!File.Exists(handoffFile)) return;

            try
            {
                using (var fs = new FileStream(handoffFile, FileMode.Open, FileAccess.ReadWrite, FileShare.None))
                {
                    long len = fs.Length;
                    if (len > 0)
                    {
                        byte[] zeros = new byte[(int)Math.Min(len, 65536)];
                        fs.Position = 0;
                        long remaining = len;
                        while (remaining > 0)
                        {
                            int chunk = (int)Math.Min(remaining, zeros.Length);
                            fs.Write(zeros, 0, chunk);
                            remaining -= chunk;
                        }
                        fs.Flush(flushToDisk: true);
                    }
                }
            }
            catch { }

            try { File.Delete(handoffFile); } catch { }
        }

        // =====================================================================
        // FAIL-CLOSED AES-256-GCM + DPAPI VAULT
        // =====================================================================
        private static byte[] GetOrCreateMasterKey()
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string keyFile = VaultKeyFile;

            if (File.Exists(keyFile))
            {
                byte[] wrapped;
                try
                {
                    wrapped = File.ReadAllBytes(keyFile);
                }
                catch (Exception ioEx)
                {
                    throw new CryptographicException("Master-Key konnte nicht gelesen werden (Fail-Closed).", ioEx);
                }

                byte[]? key = null;
                try
                {
                    key = ProtectedData.Unprotect(wrapped, AppEntropy, DataProtectionScope.CurrentUser);
                    if (key.Length != 32)
                    {
                        CryptographicOperations.ZeroMemory(key);
                        throw new CryptographicException("Master-Key hat eine ungültige Schlüssellänge (Fail-Closed).");
                    }
                    LockDownFileAcl(keyFile);
                    return key;
                }
                catch (CryptographicException)
                {
                    if (key != null) CryptographicOperations.ZeroMemory(key);
                    throw;
                }
                catch (Exception ex)
                {
                    if (key != null) CryptographicOperations.ZeroMemory(key);
                    throw new CryptographicException("Master-Key ist beschädigt oder nicht entschlüsselbar (Fail-Closed).", ex);
                }
            }

            byte[] newKey = RandomNumberGenerator.GetBytes(32);
            try
            {
                byte[] protectedKey = ProtectedData.Protect(newKey, AppEntropy, DataProtectionScope.CurrentUser);
                File.WriteAllBytes(keyFile, protectedKey);
                LockDownFileAcl(keyFile);
                return newKey;
            }
            catch
            {
                CryptographicOperations.ZeroMemory(newKey);
                throw;
            }
        }

        public static string EncryptSecret(string? plainText)
        {
            if (string.IsNullOrEmpty(plainText)) return "";
            if (plainText.StartsWith(VAULT_PREFIX, StringComparison.Ordinal)) return plainText;

            byte[] key = GetOrCreateMasterKey();
            byte[]? plainBytes = null;
            byte[]? combined = null;
            try
            {
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                plainBytes = Encoding.UTF8.GetBytes(plainText);
                byte[] cipherBytes = new byte[plainBytes.Length];
                byte[] tag = new byte[16];

                using var aesGcm = new AesGcm(key, 16);
                aesGcm.Encrypt(nonce, plainBytes, cipherBytes, tag, AppEntropy);

                combined = new byte[12 + 16 + cipherBytes.Length];
                Buffer.BlockCopy(nonce, 0, combined, 0, 12);
                Buffer.BlockCopy(tag, 0, combined, 12, 16);
                Buffer.BlockCopy(cipherBytes, 0, combined, 28, cipherBytes.Length);

                byte[] dpapiWrapped = ProtectedData.Protect(combined, AppEntropy, DataProtectionScope.CurrentUser);
                return VAULT_PREFIX + Convert.ToBase64String(dpapiWrapped);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(key);
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                if (combined != null) CryptographicOperations.ZeroMemory(combined);
            }
        }

        public static string DecryptSecret(string? cipherText)
        {
            if (string.IsNullOrEmpty(cipherText)) return "";
            if (!cipherText.StartsWith(VAULT_PREFIX, StringComparison.Ordinal))
                throw new CryptographicException("Ungültiges Geheimnis-Format im Tresor (Fail-Closed).");

            byte[] key = GetOrCreateMasterKey();
            byte[]? combined = null;
            byte[]? plainBytes = null;
            try
            {
                string b64 = cipherText.Substring(VAULT_PREFIX.Length);
                byte[] dpapiWrapped = Convert.FromBase64String(b64);
                combined = ProtectedData.Unprotect(dpapiWrapped, AppEntropy, DataProtectionScope.CurrentUser);
                if (combined.Length < 28)
                    throw new CryptographicException("Entschlüsseltes Tresor-Paket ist unvollständig (Fail-Closed).");

                byte[] nonce = new byte[12];
                byte[] tag = new byte[16];
                byte[] cipherBytes = new byte[combined.Length - 28];

                Buffer.BlockCopy(combined, 0, nonce, 0, 12);
                Buffer.BlockCopy(combined, 12, tag, 0, 16);
                Buffer.BlockCopy(combined, 28, cipherBytes, 0, cipherBytes.Length);

                plainBytes = new byte[cipherBytes.Length];
                using var aesGcm = new AesGcm(key, 16);
                aesGcm.Decrypt(nonce, cipherBytes, tag, plainBytes, AppEntropy);
                return Encoding.UTF8.GetString(plainBytes);
            }
            catch (CryptographicException)
            {
                throw;
            }
            catch (Exception ex)
            {
                throw new CryptographicException("Tresor-Geheimnis konnte nicht authentifiziert werden (Fail-Closed).", ex);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(key);
                if (combined != null) CryptographicOperations.ZeroMemory(combined);
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        /// <summary>
        /// Decodes WinSCP's legacy 0xA3 XOR obfuscated password from Registry so we can migrate it into AES-256-GCM + DPAPI.
        /// </summary>
        public static string DecodeWinScpPassword(string? user, string? host, string? hexPass)
        {
            if (string.IsNullOrWhiteSpace(hexPass) || hexPass.Length < 8 || hexPass.Length % 2 != 0)
                return "";

            try
            {
                var bytes = new List<byte>(hexPass.Length / 2);
                for (int i = 0; i < hexPass.Length; i += 2)
                {
                    int raw = Convert.ToInt32(hexPass.Substring(i, 2), 16);
                    byte dec = (byte)(~(raw ^ 0xA3) & 0xFF);
                    bytes.Add(dec);
                }

                int idx = 0;
                int length;
                if (bytes[idx] == 0xFF)
                {
                    idx += 2; // flag (0xFF) + version (0x00)
                    if (idx >= bytes.Count) return "";
                    length = bytes[idx++];
                }
                else
                {
                    length = bytes[idx++];
                }

                if (idx >= bytes.Count) return "";
                int padShift = bytes[idx++];
                idx += padShift;

                if (idx + length > bytes.Count)
                    length = Math.Max(0, bytes.Count - idx);

                string full = Encoding.UTF8.GetString(bytes.GetRange(idx, length).ToArray());
                string prefix = (user ?? "") + (host ?? "");
                if (!string.IsNullOrEmpty(prefix) && full.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
                {
                    return full.Substring(prefix.Length);
                }
                return full;
            }
            catch
            {
                return "";
            }
        }

        // =====================================================================
        // HOST, LOCAL PATH & REMOTE PATH VALIDATION
        // =====================================================================
        public static string SanitizeHost(string? rawHost)
        {
            if (string.IsNullOrWhiteSpace(rawHost)) return "";
            string decoded = Uri.UnescapeDataString(rawHost).Trim();
            foreach (char c in decoded)
            {
                if (char.IsControl(c) || c == ';' || c == '|' || c == '&' || c == '`' || c == '$' || c == '\'' || c == '"' || c == ' ')
                    throw new ArgumentException("Ungültige Zeichen im Hostnamen erkannt.");
            }
            return decoded;
        }

        public static bool IsPathStrictlyInsideDirectory(string baseDirectory, string candidatePath)
        {
            if (string.IsNullOrWhiteSpace(baseDirectory) || string.IsNullOrWhiteSpace(candidatePath))
                return false;

            string fullDir = Path.GetFullPath(baseDirectory);
            char sep = Path.DirectorySeparatorChar;
            string dirWithTrailingSep = fullDir.TrimEnd(sep, Path.AltDirectorySeparatorChar) + sep;
            string fullTarget = Path.GetFullPath(candidatePath);

            return fullTarget.StartsWith(dirWithTrailingSep, StringComparison.OrdinalIgnoreCase) &&
                   fullTarget.Length > dirWithTrailingSep.Length;
        }

        private static bool IsReservedWindowsDeviceFileName(string fileName)
        {
            string trimmed = fileName.Trim(' ', '.');
            if (string.IsNullOrEmpty(trimmed))
                return true;

            int firstDot = trimmed.IndexOf('.');
            string beforeFirstDot = (firstDot >= 0 ? trimmed.Substring(0, firstDot) : trimmed).Trim(' ', '.');
            if (ReservedWindowsDeviceNames.Contains(beforeFirstDot))
                return true;

            string withoutLastExt = Path.GetFileNameWithoutExtension(trimmed).Trim(' ', '.');
            return ReservedWindowsDeviceNames.Contains(withoutLastExt);
        }

        public static string ValidateSafeLocalPath(string localDirectory, string remoteEntryName)
        {
            if (string.IsNullOrWhiteSpace(localDirectory))
                throw new InvalidOperationException("Ungültiges lokales Zielverzeichnis.");

            if (string.IsNullOrWhiteSpace(remoteEntryName))
                throw new InvalidOperationException("Unsicherer Dateiname blockiert (leer).");

            foreach (char c in remoteEntryName)
            {
                if (char.IsControl(c) || c == '\0')
                    throw new InvalidOperationException("Steuerzeichen im Dateinamen blockiert.");
            }

            string trimmed = remoteEntryName.Trim();
            if (string.IsNullOrWhiteSpace(trimmed) ||
                trimmed.IndexOfAny(new[] { '/', '\\', ':', '*', '?', '"', '<', '>', '|' }) >= 0)
            {
                throw new InvalidOperationException("Unsicherer Dateiname blockiert (Path-Traversal-Schutz).");
            }

            string[] segments = trimmed.Split(new[] { '/', '\\' }, StringSplitOptions.None);
            if (segments.Length != 1 || segments[0] == "." || segments[0] == "..")
                throw new InvalidOperationException("Unsicherer Dateiname blockiert (Path-Traversal-Schutz).");

            // Reject filenames consisting solely of dots/spaces (which Win32 normalizes into '.' or '..')
            string strippedTrailing = trimmed.TrimEnd('.', ' ');
            if (string.IsNullOrEmpty(strippedTrailing))
                throw new InvalidOperationException("Unsicherer Dateiname blockiert (ungültiger Punktname).");

            if (IsReservedWindowsDeviceFileName(trimmed))
                throw new InvalidOperationException("Reservierter Windows-Gerätename blockiert.");

            string safeFileName = Path.GetFileName(trimmed);
            if (string.IsNullOrWhiteSpace(safeFileName) || !string.Equals(safeFileName, trimmed, StringComparison.Ordinal))
                throw new InvalidOperationException("Unsicherer Dateiname blockiert (Path-Traversal-Schutz).");

            string fullDir = Path.GetFullPath(localDirectory);
            string fullTarget = Path.GetFullPath(Path.Combine(fullDir, safeFileName));
            if (!IsPathStrictlyInsideDirectory(fullDir, fullTarget))
                throw new InvalidOperationException("Zielpfad liegt außerhalb des lokalen Verzeichnisses (Path-Traversal blockiert).");

            return fullTarget;
        }

        public static string ValidateSafeRemoteEntryName(string? name)
        {
            if (string.IsNullOrWhiteSpace(name))
                throw new InvalidOperationException("Name darf nicht leer sein.");

            foreach (char c in name)
            {
                if (char.IsControl(c) || c == '\0')
                    throw new InvalidOperationException("Steuerzeichen im Server-Dateinamen blockiert.");
            }

            string trimmed = name.Trim();
            if (string.IsNullOrEmpty(trimmed) ||
                trimmed == "." ||
                trimmed == ".." ||
                trimmed.IndexOfAny(new[] { '/', '\\' }) >= 0)
            {
                throw new InvalidOperationException("Ungültiger Server-Dateiname ('/', '\\', '.' und '..' sind nicht erlaubt).");
            }

            return trimmed;
        }

        public static string ValidateSafeRemoteTargetPath(string? remotePath)
        {
            if (string.IsNullOrWhiteSpace(remotePath))
                throw new InvalidOperationException("Server-Zielpfad darf nicht leer sein.");

            foreach (char c in remotePath)
            {
                if (char.IsControl(c) || c == '\0')
                    throw new InvalidOperationException("Steuerzeichen im Server-Zielpfad blockiert.");
            }

            string trimmed = remotePath.Trim();
            if (!trimmed.StartsWith("/", StringComparison.Ordinal) || trimmed.Contains('\\'))
                throw new InvalidOperationException("Absoluter Server-Zielpfad muss mit '/' beginnen und darf kein '\\' enthalten.");

            string[] segments = trimmed.Split('/', StringSplitOptions.RemoveEmptyEntries);
            if (segments.Length == 0)
                throw new InvalidOperationException("Stammverzeichnis '/' kann nicht umbenannt werden.");

            foreach (string seg in segments)
            {
                if (seg == "." || seg == "..")
                    throw new InvalidOperationException("Relative Pfadsegmente ('.' oder '..') im Zielpfad sind blockiert.");
            }

            return "/" + string.Join("/", segments);
        }

        // =====================================================================
        // FAIL-CLOSED SHA-256 HOST-KEY & FTPS CERTIFICATE PINNING
        // =====================================================================
        public static (bool IsTrusted, bool IsChanged, string FingerprintSha256) VerifyAndPinHostKey(
            string host,
            int port,
            string algorithm,
            byte[] hostKeyBytes,
            bool trustNewKeys = false,
            Func<string, bool>? confirmNewKeyCallback = null)
        {
            if (string.IsNullOrWhiteSpace(host))
                throw new ArgumentException("Hostname fehlt für Host-Key-Prüfung.", nameof(host));
            if (hostKeyBytes == null || hostKeyBytes.Length == 0)
                throw new ArgumentException("Leerer Serverschlüssel abgelehnt.", nameof(hostKeyBytes));

            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string pinsFile = KnownHostsFile;

            string keyId = $"{host.Trim().ToLowerInvariant()}:{port}:{algorithm.Trim()}";
            byte[] hash = SHA256.HashData(hostKeyBytes);
            string fp = "SHA256:" + Convert.ToBase64String(hash).TrimEnd('=');

            Dictionary<string, string> pins = new(StringComparer.OrdinalIgnoreCase);
            if (File.Exists(pinsFile))
            {
                Dictionary<string, string>? loaded;
                try
                {
                    string rawJson = File.ReadAllText(pinsFile);
                    loaded = JsonSerializer.Deserialize<Dictionary<string, string>>(rawJson);
                }
                catch (Exception ex)
                {
                    throw new InvalidDataException("Host-Key-Datenbank 'known_hosts_sha256.json' ist beschädigt (Fail-Closed).", ex);
                }

                if (loaded == null)
                    throw new InvalidDataException("Host-Key-Datenbank 'known_hosts_sha256.json' ist ungültig (Fail-Closed).");

                pins = new Dictionary<string, string>(loaded, StringComparer.OrdinalIgnoreCase);
            }

            if (pins.TryGetValue(keyId, out string? existingFp))
            {
                if (string.Equals(existingFp, fp, StringComparison.Ordinal))
                    return (true, false, fp);
                return (false, true, fp);
            }

            bool approved = trustNewKeys || (confirmNewKeyCallback != null && confirmNewKeyCallback(fp));
            if (approved)
            {
                pins[keyId] = fp;
                string serialized = JsonSerializer.Serialize(pins, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(pinsFile, serialized);
                LockDownFileAcl(pinsFile);
                return (true, false, fp);
            }

            return (false, false, fp);
        }

        // =====================================================================
        // FAIL-CLOSED PROFILE VAULT & CLEAN REGISTRY IMPORT
        // =====================================================================
        public static List<FtpConnectionProfile> LoadAndImportProfiles()
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string profFile = ProfilesFile;

            List<FtpConnectionProfile> profiles = new();
            if (File.Exists(profFile))
            {
                List<FtpConnectionProfile>? loaded;
                try
                {
                    string rawJson = File.ReadAllText(profFile);
                    loaded = JsonSerializer.Deserialize<List<FtpConnectionProfile>>(rawJson);
                }
                catch (Exception ex)
                {
                    BackupCorruptVaultFile(profFile);
                    throw new InvalidDataException("Profil-Tresor 'profiles_vault.json' ist beschädigt (Backup angelegt, Fail-Closed).", ex);
                }

                if (loaded == null)
                {
                    BackupCorruptVaultFile(profFile);
                    throw new InvalidDataException("Profil-Tresor 'profiles_vault.json' enthält kein gültiges Array (Backup angelegt, Fail-Closed).");
                }

                profiles = loaded;
                LockDownFileAcl(profFile);
            }

            bool changed = !_isSelfTestMode && ImportFromWinScpAndPuttyRegistry(profiles);
            if (changed || !File.Exists(profFile))
            {
                SaveProfiles(profiles);
            }
            return profiles;
        }

        private static void BackupCorruptVaultFile(string filePath)
        {
            try
            {
                if (!File.Exists(filePath)) return;
                string backupPath = $"{filePath}.corrupt_{DateTime.UtcNow.Ticks}.bak";
                File.Copy(filePath, backupPath, overwrite: false);
                LockDownFileAcl(backupPath);
            }
            catch { }
        }

        public static void SaveProfiles(List<FtpConnectionProfile> profiles)
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string profFile = ProfilesFile;

            string json = JsonSerializer.Serialize(profiles ?? new List<FtpConnectionProfile>(), new JsonSerializerOptions { WriteIndented = true });
            File.WriteAllText(profFile, json);
            LockDownFileAcl(profFile);
        }

        /// <summary>
        /// Resolves an explicitly configured OpenSSH key path (or sibling OpenSSH key next to a .ppk file).
        /// Returns empty string when rawKeyPath is empty so profiles without an SSH key never silently auto-select ~/.ssh/id_ed25519!
        /// </summary>
        public static string ResolveOpenSshKeyPath(string? rawKeyPath)
        {
            if (string.IsNullOrWhiteSpace(rawKeyPath))
                return "";

            string unescaped = Uri.UnescapeDataString(rawKeyPath).Trim();
            if (string.IsNullOrWhiteSpace(unescaped))
                return "";

            if (unescaped.EndsWith(".ppk", StringComparison.OrdinalIgnoreCase))
            {
                string candidate = unescaped.Substring(0, unescaped.Length - 4);
                if (File.Exists(candidate)) return candidate;
                return "";
            }

            return File.Exists(unescaped) ? unescaped : "";
        }

        public static bool ImportFromWinScpAndPuttyRegistry(List<FtpConnectionProfile> existing)
        {
            if (_isSelfTestMode) return false;

            bool addedAny = false;
            string defaultLocalDir = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);

            // 1. Import from WinSCP Registry (HKCU\Software\Martin Prikryl\WinSCP 2\Sessions)
            try
            {
                using var sessionsKey = Registry.CurrentUser.OpenSubKey(@"Software\Martin Prikryl\WinSCP 2\Sessions");
                if (sessionsKey != null)
                {
                    foreach (string subName in sessionsKey.GetSubKeyNames())
                    {
                        string sessionLabel = Uri.UnescapeDataString(subName).Trim();
                        if (string.Equals(sessionLabel, "Default Settings", StringComparison.OrdinalIgnoreCase))
                            continue;

                        using var sk = sessionsKey.OpenSubKey(subName);
                        if (sk == null) continue;

                        string rawHost = Convert.ToString(sk.GetValue("HostName")) ?? "";
                        string cleanHost;
                        try { cleanHost = SanitizeHost(rawHost); } catch { continue; }
                        if (string.IsNullOrWhiteSpace(cleanHost)) continue;

                        string rawUser = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("UserName")) ?? "").Trim();
                        int port = Convert.ToInt32(sk.GetValue("PortNumber") ?? 22);
                        if (port <= 0 || port > 65535) port = 22;

                        int fsProto = Convert.ToInt32(sk.GetValue("FSProtocol") ?? 0);
                        int ftps = Convert.ToInt32(sk.GetValue("Ftps") ?? 0);
                        string protocol = fsProto == 5 ? (ftps > 0 ? "FTPS" : "FTP") : "SFTP";
                        if (protocol != "SFTP" && port == 22) port = 21;

                        string rawPassHex = Convert.ToString(sk.GetValue("Password")) ?? "";
                        string decodedPass = DecodeWinScpPassword(rawUser, Uri.UnescapeDataString(rawHost), rawPassHex);

                        string remoteDir = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("RemoteDirectory")) ?? "/").Trim();
                        if (string.IsNullOrWhiteSpace(remoteDir)) remoteDir = "/";

                        string localDir = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("LocalDirectory")) ?? "").Trim();
                        if (string.IsNullOrWhiteSpace(localDir) || !Directory.Exists(localDir))
                            localDir = defaultLocalDir;

                        string rawKey = Convert.ToString(sk.GetValue("PublicKeyFile")) ?? "";
                        string keyPath = ResolveOpenSshKeyPath(rawKey);

                        var match = existing.FirstOrDefault(p =>
                            string.Equals(p.Name, sessionLabel, StringComparison.OrdinalIgnoreCase));

                        if (match == null)
                        {
                            existing.Add(new FtpConnectionProfile
                            {
                                Name = sessionLabel,
                                Protocol = protocol,
                                Host = cleanHost,
                                Port = port,
                                Username = rawUser,
                                EncryptedPassword = !string.IsNullOrEmpty(decodedPass) ? EncryptSecret(decodedPass) : "",
                                PrivateKeyPath = keyPath,
                                RemoteDirectory = remoteDir,
                                LocalDirectory = localDir,
                                Source = "WinSCP"
                            });
                            addedAny = true;
                        }
                        else if (string.IsNullOrEmpty(match.EncryptedPassword) &&
                                 !string.IsNullOrEmpty(decodedPass) &&
                                 string.Equals(match.Host, cleanHost, StringComparison.OrdinalIgnoreCase) &&
                                 string.Equals(match.Username, rawUser, StringComparison.OrdinalIgnoreCase))
                        {
                            match.EncryptedPassword = EncryptSecret(decodedPass);
                            addedAny = true;
                        }
                    }
                }
            }
            catch { }

            // 2. Import from PuTTY Registry (HKCU\Software\SimonTatham\PuTTY\Sessions)
            try
            {
                using var puttyKey = Registry.CurrentUser.OpenSubKey(@"Software\SimonTatham\PuTTY\Sessions");
                if (puttyKey != null)
                {
                    foreach (string subName in puttyKey.GetSubKeyNames())
                    {
                        string sessionLabel = Uri.UnescapeDataString(subName).Trim();
                        if (string.Equals(sessionLabel, "Default Settings", StringComparison.OrdinalIgnoreCase))
                            continue;

                        using var sk = puttyKey.OpenSubKey(subName);
                        if (sk == null) continue;

                        string rawHost = Convert.ToString(sk.GetValue("HostName")) ?? "";
                        string cleanHost;
                        try { cleanHost = SanitizeHost(rawHost); } catch { continue; }
                        if (string.IsNullOrWhiteSpace(cleanHost)) continue;

                        string rawUser = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("UserName")) ?? "").Trim();
                        if (string.IsNullOrWhiteSpace(rawUser) && sessionLabel.Contains('@') && sessionLabel.EndsWith(cleanHost, StringComparison.OrdinalIgnoreCase))
                        {
                            int lastAt = sessionLabel.LastIndexOf('@');
                            if (lastAt > 0) rawUser = sessionLabel.Substring(0, lastAt);
                        }

                        int port = Convert.ToInt32(sk.GetValue("PortNumber") ?? 22);
                        if (port <= 0 || port > 65535) port = 22;

                        string rawKey = Convert.ToString(sk.GetValue("PublicKeyFile")) ?? "";
                        string keyPath = ResolveOpenSshKeyPath(rawKey);

                        var match = existing.FirstOrDefault(p =>
                            string.Equals(p.Name, sessionLabel, StringComparison.OrdinalIgnoreCase));

                        if (match == null)
                        {
                            existing.Add(new FtpConnectionProfile
                            {
                                Name = sessionLabel,
                                Protocol = "SFTP",
                                Host = cleanHost,
                                Port = port,
                                Username = rawUser,
                                PrivateKeyPath = keyPath,
                                RemoteDirectory = "/",
                                LocalDirectory = defaultLocalDir,
                                Source = "PuTTY"
                            });
                            addedAny = true;
                        }
                    }
                }
            }
            catch { }

            // NOTE: Intentionally NO sibling-host cross-enrichment loop here so distinct accounts on a shared host never contaminate each other!
            return addedAny;
        }

        // =====================================================================
        // ISOLATED 100%-REAL AUTOMATED SELF-TEST SUITE (--self-test)
        // =====================================================================
        public static bool RunIsolatedSelfTestSuite(out List<string> logLines)
        {
            var logs = new List<string>();
            int passed = 0;
            int failed = 0;

            void Check(string name, Func<bool> assertion)
            {
                try
                {
                    if (assertion())
                    {
                        passed++;
                        logs.Add($"[PASS] {name}");
                    }
                    else
                    {
                        failed++;
                        logs.Add($"[FAIL] {name} (Assertion returned false)");
                    }
                }
                catch (Exception ex)
                {
                    failed++;
                    logs.Add($"[FAIL] {name} ({ex.GetType().Name}: {ex.Message})");
                }
            }

            string realLocalAppFtp = Path.GetFullPath(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkFtp"));
            string realLocalAppSsh = Path.GetFullPath(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkSsh"));

            string tempRoot = Path.Combine(Path.GetTempPath(), "FwFtpSelfTest_" + Guid.NewGuid().ToString("N"));
            string isoFtpVault = Path.Combine(tempRoot, "FtpVault");
            string isoSshVault = Path.Combine(tempRoot, "SshVault");

            try
            {
                EnableSelfTestIsolation(isoFtpVault, isoSshVault);

                // 1. Verify strict temp-vault isolation & zero HKCU profile requirement
                Check("01_IsolatedTempVault_ZeroLocalAppDataTouch", () =>
                {
                    var loaded = LoadAndImportProfiles();
                    return !VaultDir.StartsWith(realLocalAppFtp, StringComparison.OrdinalIgnoreCase) &&
                           !SshHandoffVaultDir.StartsWith(realLocalAppSsh, StringComparison.OrdinalIgnoreCase) &&
                           loaded.Count == 0;
                });

                // 2. AES-256-GCM + DPAPI roundtrip & distinct nonces
                Check("02_VaultAes256GcmDpapi_RoundtripAndNonceUniqueness", () =>
                {
                    const string secret = "S3cur3!ÄÖÜß#MeinSeelenfunke2026";
                    string enc1 = EncryptSecret(secret);
                    string enc2 = EncryptSecret(secret);
                    return enc1.StartsWith(VAULT_PREFIX, StringComparison.Ordinal) &&
                           enc2.StartsWith(VAULT_PREFIX, StringComparison.Ordinal) &&
                           !string.Equals(enc1, enc2, StringComparison.Ordinal) &&
                           DecryptSecret(enc1) == secret &&
                           DecryptSecret(enc2) == secret;
                });

                // 3. Tamper detection on outer DPAPI envelope (must throw CryptographicException, never return empty)
                Check("03_VaultOuterDpapiTamper_FailClosed", () =>
                {
                    byte[] raw = Convert.FromBase64String(EncryptSecret("TamperTestOuter").Substring(VAULT_PREFIX.Length));
                    raw[raw.Length / 2] ^= 0x5A;
                    string tampered = VAULT_PREFIX + Convert.ToBase64String(raw);
                    try
                    {
                        _ = DecryptSecret(tampered);
                        return false;
                    }
                    catch (CryptographicException)
                    {
                        return true;
                    }
                });

                // 4. Tamper detection on inner AES-256-GCM tag/ciphertext (must throw CryptographicException)
                Check("04_VaultInnerAesGcmTamper_FailClosed", () =>
                {
                    string valid = EncryptSecret("TamperTestInnerAesGcm");
                    byte[] wrapped = Convert.FromBase64String(valid.Substring(VAULT_PREFIX.Length));
                    byte[] combined = ProtectedData.Unprotect(wrapped, AppEntropy, DataProtectionScope.CurrentUser);
                    combined[15] ^= 0x01; // Flip 1 bit inside AES-GCM authentication tag
                    byte[] rewrapped = ProtectedData.Protect(combined, AppEntropy, DataProtectionScope.CurrentUser);
                    try
                    {
                        _ = DecryptSecret(VAULT_PREFIX + Convert.ToBase64String(rewrapped));
                        return false;
                    }
                    catch (CryptographicException)
                    {
                        return true;
                    }
                });

                // 5. Corrupt master.dpapi must fail closed and NEVER overwrite master.dpapi
                Check("05_CorruptMasterDpapi_FailClosed_NeverOverwritten", () =>
                {
                    string corruptVault = Path.Combine(tempRoot, "CorruptMasterVault");
                    EnableSelfTestIsolation(corruptVault, isoSshVault);
                    byte[] badBytes = new byte[] { 0xDE, 0xAD, 0xBE, 0xEF, 0x01, 0x02 };
                    File.WriteAllBytes(VaultKeyFile, badBytes);

                    bool threwOnEncrypt = false;
                    try { _ = EncryptSecret("ShouldFail"); }
                    catch (CryptographicException) { threwOnEncrypt = true; }

                    byte[] afterBytes = File.ReadAllBytes(VaultKeyFile);
                    EnableSelfTestIsolation(isoFtpVault, isoSshVault);
                    return threwOnEncrypt && badBytes.SequenceEqual(afterBytes);
                });

                // 6. Corrupt known_hosts_sha256.json and profiles_vault.json fail closed + backup
                Check("06_CorruptKnownHostsAndProfiles_FailClosedWithBackup", () =>
                {
                    string corruptJsonVault = Path.Combine(tempRoot, "CorruptJsonVault");
                    EnableSelfTestIsolation(corruptJsonVault, isoSshVault);
                    File.WriteAllText(KnownHostsFile, "{not-valid-json");
                    File.WriteAllText(ProfilesFile, "{broken-profiles-array");

                    bool hostKeyThrew = false;
                    try { _ = VerifyAndPinHostKey("example.test", 22, "ssh-ed25519", new byte[] { 1, 2, 3 }, trustNewKeys: true); }
                    catch (InvalidDataException) { hostKeyThrew = true; }

                    bool profilesThrew = false;
                    try { _ = LoadAndImportProfiles(); }
                    catch (InvalidDataException) { profilesThrew = true; }

                    bool originalPreserved = File.ReadAllText(ProfilesFile) == "{broken-profiles-array" &&
                                             File.ReadAllText(KnownHostsFile) == "{not-valid-json";
                    bool backupCreated = Directory.GetFiles(corruptJsonVault, "profiles_vault.json.corrupt_*.bak").Length == 1;

                    EnableSelfTestIsolation(isoFtpVault, isoSshVault);
                    return hostKeyThrew && profilesThrew && originalPreserved && backupCreated;
                });

                // 7. Interactive host-key callback & MITM detection
                Check("07_HostKeyPinning_CallbackApprovalAndMitmBlock", () =>
                {
                    var rejected = VerifyAndPinHostKey("pin.example.test", 22, "ssh-ed25519", new byte[] { 10, 20, 30 }, trustNewKeys: false, _ => false);
                    var accepted = VerifyAndPinHostKey("pin.example.test", 22, "ssh-ed25519", new byte[] { 10, 20, 30 }, trustNewKeys: false, _ => true);
                    var repeatOk = VerifyAndPinHostKey("pin.example.test", 22, "ssh-ed25519", new byte[] { 10, 20, 30 }, trustNewKeys: false, _ => false);
                    var mitm = VerifyAndPinHostKey("pin.example.test", 22, "ssh-ed25519", new byte[] { 99, 88, 77 }, trustNewKeys: true);
                    return !rejected.IsTrusted && !rejected.IsChanged &&
                           accepted.IsTrusted && !accepted.IsChanged &&
                           repeatOk.IsTrusted && !repeatOk.IsChanged &&
                           !mitm.IsTrusted && mitm.IsChanged;
                });

                // 8. Strict NTFS ACL verification across all vault directories and files
                Check("08_NtfsAclLockdown_StrictCurrentUserOnly", () =>
                {
                    SaveProfiles(new List<FtpConnectionProfile>
                    {
                        new() { Name = "TestProf", Host = "sftp.example.test", Port = 22, Username = "tester", EncryptedPassword = EncryptSecret("pw") }
                    });
                    string nonce = WriteSshHandoffTicket("TestProf", "sftp.example.test", 22, "tester", "pw", "", "/var/www");
                    string stageBatch = DragStagingManager.CreateIsolatedBatchDir();
                    string liveRoot = Path.Combine(VaultDir, "LiveEdit");
                    Directory.CreateDirectory(liveRoot);
                    LockDownDirectoryAcl(liveRoot);

                    bool aclOk = !string.IsNullOrEmpty(nonce) &&
                                 VerifyStrictCurrentUserAcl(VaultDir, isDirectory: true) &&
                                 VerifyStrictCurrentUserAcl(SshHandoffVaultDir, isDirectory: true) &&
                                 VerifyStrictCurrentUserAcl(DragStagingManager.RootStagingDir, isDirectory: true) &&
                                 VerifyStrictCurrentUserAcl(stageBatch, isDirectory: true) &&
                                 VerifyStrictCurrentUserAcl(liveRoot, isDirectory: true) &&
                                 VerifyStrictCurrentUserAcl(VaultKeyFile, isDirectory: false) &&
                                 VerifyStrictCurrentUserAcl(ProfilesFile, isDirectory: false) &&
                                 VerifyStrictCurrentUserAcl(KnownHostsFile, isDirectory: false) &&
                                 VerifyStrictCurrentUserAcl(HandoffTicketFile, isDirectory: false);

                    WipeAndDeleteSshHandoffTicket();
                    DragStagingManager.PurgeAllStagingDirs();
                    return aclOk && !File.Exists(HandoffTicketFile);
                });

                // 9. ValidateSafeLocalPath blocks sibling-prefix bypass and path traversal
                Check("09_ValidateSafeLocalPath_SiblingPrefixAndTraversalBlocked", () =>
                {
                    string baseStage = Path.Combine(tempRoot, "Stage");
                    string siblingStage = Path.Combine(tempRoot, "Stage2", "evil.txt");
                    Directory.CreateDirectory(baseStage);

                    if (IsPathStrictlyInsideDirectory(baseStage, siblingStage)) return false;
                    if (IsPathStrictlyInsideDirectory(baseStage, baseStage)) return false;

                    string[] malicious = { @"..\..\Windows\win.ini", "../etc/passwd", @"sub\file.txt", "sub/file.txt", ".", "..", "...", @"C:\evil.txt", "bad:ads.txt", "nul\0byte.txt" };
                    foreach (string bad in malicious)
                    {
                        bool blocked = false;
                        try { _ = ValidateSafeLocalPath(baseStage, bad); }
                        catch { blocked = true; }
                        if (!blocked) return false;
                    }
                    return true;
                });

                // 10. ValidateSafeLocalPath blocks Windows reserved devices & allows backup..2026.sql
                Check("10_ValidateSafeLocalPath_ReservedDevicesBlocked_DoubleDotAllowed", () =>
                {
                    string baseStage = Path.Combine(tempRoot, "Stage");
                    string[] reserved = { "CON", "CON.txt", "nul", "NUL.tar.gz", "PRN.log", "AUX", "COM1", "COM1.txt", "LPT9.dat" };
                    foreach (string dev in reserved)
                    {
                        bool blocked = false;
                        try { _ = ValidateSafeLocalPath(baseStage, dev); }
                        catch { blocked = true; }
                        if (!blocked) return false;
                    }

                    string okDoubleDot = ValidateSafeLocalPath(baseStage, "backup..2026.sql");
                    string okDotEnv = ValidateSafeLocalPath(baseStage, ".env");
                    return okDoubleDot.EndsWith("backup..2026.sql", StringComparison.Ordinal) &&
                           okDotEnv.EndsWith(".env", StringComparison.Ordinal);
                });

                // 11. Remote mkdir/newfile/rename path validation
                Check("11_ValidateSafeRemoteNames_MkdirAndRename", () =>
                {
                    string[] badNames = { "", " ", ".", "..", "a/b", @"a\b", "bad\0name", "bad\nname" };
                    foreach (string bn in badNames)
                    {
                        bool blocked = false;
                        try { _ = ValidateSafeRemoteEntryName(bn); }
                        catch { blocked = true; }
                        if (!blocked) return false;
                    }

                    bool traversalTargetBlocked = false;
                    try { _ = ValidateSafeRemoteTargetPath("/var/www/../etc/passwd"); }
                    catch { traversalTargetBlocked = true; }

                    return traversalTargetBlocked &&
                           ValidateSafeRemoteEntryName("backup..2026.sql") == "backup..2026.sql" &&
                           ValidateSafeRemoteTargetPath("/var/www/html/index.php") == "/var/www/html/index.php";
                });

                // 12. Remote-to-Local RCE fix in BuildSafeExternalEditorStartInfo
                Check("12_ExternalEditorRceGuard_DangerousExtensionsNeverShellExecuted", () =>
                {
                    string editDir = Path.Combine(tempRoot, "EditRceCheck");
                    Directory.CreateDirectory(editDir);
                    string[] exts = { ".bat", ".cmd", ".ps1", ".vbs", ".js", ".exe", ".reg", ".hta", ".php", ".env" };

                    foreach (string ext in exts)
                    {
                        string fakeFile = Path.Combine(editDir, "payload" + ext);
                        File.WriteAllText(fakeFile, "rem test");
                        var psi = MainWindow.BuildSafeExternalEditorStartInfo(fakeFile);

                        if (psi.UseShellExecute)
                            return false;
                        if (string.IsNullOrWhiteSpace(psi.FileName) || !psi.FileName.EndsWith(".exe", StringComparison.OrdinalIgnoreCase))
                            return false;
                        if (string.Equals(Path.GetFullPath(psi.FileName), Path.GetFullPath(fakeFile), StringComparison.OrdinalIgnoreCase))
                            return false;
                        if (psi.ArgumentList.Count != 1 || !string.Equals(psi.ArgumentList[0], Path.GetFullPath(fakeFile), StringComparison.OrdinalIgnoreCase))
                            return false;
                    }
                    return true;
                });

                // 13. Strict FTPS Explicit TLS + DataConnectionEncryption config
                Check("13_FtpsStrictExplicitTlsConfig", () =>
                {
                    var ftpsCfg = MainWindow.BuildFtpConfig("FTPS");
                    var ftpCfg = MainWindow.BuildFtpConfig("FTP");
                    return ftpsCfg.EncryptionMode == FtpEncryptionMode.Explicit &&
                           ftpsCfg.DataConnectionEncryption &&
                           !ftpsCfg.ValidateAnyCertificate &&
                           ftpsCfg.SslProtocols == (SslProtocols.Tls12 | SslProtocols.Tls13) &&
                           ftpCfg.EncryptionMode == FtpEncryptionMode.None &&
                           !ftpCfg.DataConnectionEncryption;
                });

                // 14. SFTP Symlink Guard on recursive delete (unlinks symlink, NEVER recurses into target!)
                Check("14_SftpSymlinkGuard_DeleteTreeUnlinksNeverRecurses", () =>
                {
                    var unlinkedFiles = new List<string>();
                    var deletedDirs = new List<string>();
                    bool recursedIntoSymlink = false;

                    MainWindow.ExecuteSafeRemoteDeleteTree(
                        "/var/www/release",
                        dir =>
                        {
                            if (dir == "/var/www/release/sym_to_shared")
                            {
                                recursedIntoSymlink = true;
                                return new[] { new MainWindow.RemoteNodeInfo("secret.sql", "/shared/secret.sql", false, false, 100) };
                            }
                            if (dir == "/var/www/release")
                            {
                                return new[]
                                {
                                    new MainWindow.RemoteNodeInfo("index.php", "/var/www/release/index.php", false, false, 50),
                                    // Simulate SSH.NET reporting BOTH IsSymbolicLink=true and IsDirectory=true for a symlink to a directory
                                    new MainWindow.RemoteNodeInfo("sym_to_shared", "/var/www/release/sym_to_shared", true, true, 0)
                                };
                            }
                            return Array.Empty<MainWindow.RemoteNodeInfo>();
                        },
                        f => unlinkedFiles.Add(f),
                        d => deletedDirs.Add(d));

                    return !recursedIntoSymlink &&
                           unlinkedFiles.Contains("/var/www/release/index.php") &&
                           unlinkedFiles.Contains("/var/www/release/sym_to_shared") &&
                           !unlinkedFiles.Contains("/shared/secret.sql") &&
                           deletedDirs.SequenceEqual(new[] { "/var/www/release" });
                });

                // 15. Recursive CHMOD skips symlinks, preserves +x traverse on dirs (644 -> 755), and validates octal
                Check("15_RecursiveChmod_DirTraversePreservedAndSymlinkSkipped", () =>
                {
                    var applied = new Dictionary<string, short>();
                    short mode644 = FtpTextBufferCodec.ParseOctalPermissions("644");
                    short mode755 = FtpTextBufferCodec.ParseOctalPermissions("755");

                    MainWindow.ExecuteSafeRemoteChmodTree(
                        "/var/www/app",
                        isDir: true,
                        isSymlink: false,
                        fileOctal: mode644,
                        recursive: true,
                        dir => new[]
                        {
                            new MainWindow.RemoteNodeInfo("config.php", "/var/www/app/config.php", false, false, 120),
                            new MainWindow.RemoteNodeInfo("storage_link", "/var/www/app/storage_link", true, true, 0)
                        },
                        (p, m) => applied[p] = m);

                    return applied.Count == 2 &&
                           applied["/var/www/app"] == mode755 &&
                           applied["/var/www/app/config.php"] == mode644 &&
                           !applied.ContainsKey("/var/www/app/storage_link") &&
                           FtpTextBufferCodec.IsValidOctalInput("644") &&
                           FtpTextBufferCodec.IsValidOctalInput("0755") &&
                           !FtpTextBufferCodec.IsValidOctalInput("999") &&
                           !FtpTextBufferCodec.IsValidOctalInput("77") &&
                           !FtpTextBufferCodec.IsValidOctalInput("abcd");
                });

                // 16. Drag-Staging skips symlinks & enforces 160 MB cumulative byte cap + 2000 file cap + stream overrun cap
                Check("16_DragStaging_SymlinkSkipAnd160MbAnd2000FileCaps", () =>
                {
                    string stageDir = Path.Combine(tempRoot, "StageTreeTest");
                    var budget = new DragStagingBudget(byteLimit: 1024, fileLimit: 3);

                    MainWindow.ExecuteSafeRemoteStageTree(
                        "/remote/folder",
                        isDir: true,
                        isSymlink: false,
                        declaredSize: 0,
                        safeLocalDest: stageDir,
                        budget: budget,
                        listDir: _ => new[]
                        {
                            new MainWindow.RemoteNodeInfo("ok.txt", "/remote/folder/ok.txt", false, false, 5),
                            new MainWindow.RemoteNodeInfo("evil_link", "/remote/folder/evil_link", true, true, 50)
                        },
                        downloadToStream: (_, s) => s.Write(Encoding.UTF8.GetBytes("hello")));

                    bool symlinkSkipped = File.Exists(Path.Combine(stageDir, "ok.txt")) &&
                                          !File.Exists(Path.Combine(stageDir, "evil_link")) &&
                                          !Directory.Exists(Path.Combine(stageDir, "evil_link"));

                    // Test stream overrun when server lies about size (declares 10 bytes, streams 2048 bytes against 1024 limit)
                    bool overrunBlocked = false;
                    try
                    {
                        MainWindow.ExecuteSafeRemoteStageTree(
                            "/remote/lied.bin",
                            isDir: false,
                            isSymlink: false,
                            declaredSize: 10,
                            safeLocalDest: Path.Combine(tempRoot, "lied.bin"),
                            budget: budget,
                            listDir: _ => Array.Empty<MainWindow.RemoteNodeInfo>(),
                            downloadToStream: (_, s) => s.Write(new byte[2048]));
                    }
                    catch (InvalidOperationException)
                    {
                        overrunBlocked = true;
                    }

                    // Test 2000 file cap & 160 MB default cap
                    var fullBudget = new DragStagingBudget();
                    for (int i = 0; i < DragStagingBudget.MaxFiles; i++)
                        fullBudget.ReserveFile(1);

                    bool fileCapBlocked = false;
                    try { fullBudget.ReserveFile(1); }
                    catch (InvalidOperationException) { fileCapBlocked = true; }

                    var byteBudget = new DragStagingBudget();
                    bool byteCapBlocked = false;
                    try { byteBudget.ReserveFile(DragStagingBudget.MaxBytes + 1); }
                    catch (InvalidOperationException) { byteCapBlocked = true; }

                    return symlinkSkipped && overrunBlocked && fileCapBlocked && byteCapBlocked;
                });

                // 17. DelayedRemoteFileDropDataObject lazy CF_HDROP & GetDataHere empty guard
                Check("17_DelayedRemoteFileDrop_ZeroPrematureStageAndGetDataHereGuard", () =>
                {
                    string sampleFile = Path.Combine(tempRoot, "drop_ok.txt");
                    File.WriteAllText(sampleFile, "DropOK");
                    int stageCount = 0;

                    var validDrop = new DelayedRemoteFileDropDataObject(
                        "FunkenwerkFtp.RemoteItems",
                        new[] { "item1" },
                        () => { stageCount++; return new[] { sampleFile }; });

                    var fmtHdrop = new FORMATETC
                    {
                        cfFormat = 15,
                        dwAspect = DVASPECT.DVASPECT_CONTENT,
                        lindex = -1,
                        tymed = TYMED.TYMED_HGLOBAL
                    };

                    var comValid = (System.Runtime.InteropServices.ComTypes.IDataObject)validDrop;
                    int q = comValid.QueryGetData(ref fmtHdrop);
                    var internalItems = validDrop.GetData("FunkenwerkFtp.RemoteItems") as string[];
                    if (q != 0 || stageCount != 0 || internalItems?.Length != 1) return false;

                    comValid.GetData(ref fmtHdrop, out var med);
                    bool gotHdrop = stageCount == 1 && med.unionmember != IntPtr.Zero;
                    if (med.unionmember != IntPtr.Zero) Marshal.FreeHGlobal(med.unionmember);

                    // Empty staging must throw DV_E_FORMATETC (0x80040064) in BOTH GetData and GetDataHere
                    var emptyDrop = new DelayedRemoteFileDropDataObject(
                        "FunkenwerkFtp.RemoteItems",
                        new[] { "item_fail" },
                        () => Array.Empty<string>());
                    var comEmpty = (System.Runtime.InteropServices.ComTypes.IDataObject)emptyDrop;

                    bool getDataBlocked = false;
                    try { comEmpty.GetData(ref fmtHdrop, out _); }
                    catch (COMException cex) when (cex.ErrorCode == unchecked((int)0x80040064)) { getDataBlocked = true; }

                    bool getDataHereBlocked = false;
                    var dummyMed = new STGMEDIUM();
                    try { comEmpty.GetDataHere(ref fmtHdrop, ref dummyMed); }
                    catch (COMException cex) when (cex.ErrorCode == unchecked((int)0x80040064)) { getDataHereBlocked = true; }

                    return gotHdrop && getDataBlocked && getDataHereBlocked;
                });

                // 18. Handoff ticket control-char injection rejection & empty SSH key resolution
                Check("18_SshHandoffControlCharGuardAndNoSilentDefaultKey", () =>
                {
                    bool newlineRejected = false;
                    try
                    {
                        _ = WriteSshHandoffTicket("T", "example.test", 22, "u", "p", "", "/var/www\nid");
                    }
                    catch (ArgumentException)
                    {
                        newlineRejected = true;
                    }

                    return newlineRejected &&
                           ResolveOpenSshKeyPath("") == "" &&
                           ResolveOpenSshKeyPath("   ") == "" &&
                           ResolveOpenSshKeyPath(null) == "";
                });

                // 19. FtpTextBufferCodec LF, CRLF, and UTF-8 BOM preservation
                Check("19_FtpTextBufferCodec_LfCrlfAndUtf8BomRoundtrip", () =>
                {
                    byte[] lfBytes = Encoding.UTF8.GetBytes("<?php\nreturn ['name' => 'Seelenfunke'];\n");
                    var decLf = FtpTextBufferCodec.Decode(lfBytes, defaultUseCrLf: false);
                    byte[] encLf = FtpTextBufferCodec.Encode(decLf.Text, decLf.Encoding, decLf.UseCrLf);

                    byte[] bomPrefix = Encoding.UTF8.GetPreamble();
                    byte[] crlfPayload = Encoding.UTF8.GetBytes("Zeile1\r\nZeile2\r\n");
                    byte[] crlfWithBom = bomPrefix.Concat(crlfPayload).ToArray();
                    var decBom = FtpTextBufferCodec.Decode(crlfWithBom, defaultUseCrLf: true);
                    byte[] encBom = FtpTextBufferCodec.Encode(decBom.Text, decBom.Encoding, decBom.UseCrLf);

                    return !decLf.UseCrLf && lfBytes.SequenceEqual(encLf) &&
                           decBom.UseCrLf && decBom.EncodingLabel == "UTF-8 BOM" && crlfWithBom.SequenceEqual(encBom) &&
                           FtpTextBufferCodec.IsLikelyTextFileName("deploy.vbs") &&
                           FtpTextBufferCodec.IsLikelyTextFileName("policy.reg") &&
                           FtpTextBufferCodec.IsLikelyTextFileName("tool.hta");
                });

                // 20. Headless STA MainWindow startup LiveEdit purge + Light/Dark theme consistency
                MainWindow? testWin = null;
                Check("20_HeadlessStaMainWindow_StartupLiveEditPurgeAndThemes", () =>
                {
                    string staleLiveDir = Path.Combine(VaultDir, "LiveEdit", "old_hash");
                    Directory.CreateDirectory(staleLiveDir);
                    string staleSecretFile = Path.Combine(staleLiveDir, ".env");
                    File.WriteAllText(staleSecretFile, "APP_KEY=stale");

                    testWin = new MainWindow();
                    bool purgedOnStartup = !File.Exists(staleSecretFile);

                    testWin.SetTheme(false);
                    bool lightOk = !testWin.IsDarkMode;
                    testWin.SetTheme(true);
                    bool darkOk = testWin.IsDarkMode;

                    return purgedOnStartup && lightOk && darkOk && testWin.Title.Contains("Funkenwerk FTP", StringComparison.Ordinal);
                });

                // 21. Headless STA FtpFileEditorWindow dirty flag (content + EOL) & Word-Wrap gutter hide
                Check("21_HeadlessStaEditorWindow_DirtyFlagAndWordWrapGutter", () =>
                {
                    if (testWin == null) return false;
                    var sampleDoc = FtpTextBufferCodec.Decode(Encoding.UTF8.GetBytes("<?php\necho 'clean';\n"), defaultUseCrLf: false);
                    var ed = new FtpFileEditorWindow(
                        testWin,
                        true,
                        true,
                        "index.php",
                        "/var/www/index.php",
                        sampleDoc,
                        _ => Task.CompletedTask);

                    if (ed.IsDirty || ed.UseCrLf || ed.IsWordWrapEnabled || !ed.IsLineGutterVisible)
                        return false;

                    // Toggling LF -> CRLF marks dirty; toggling CRLF -> LF restores clean!
                    ed.ToggleLineEndingMode();
                    bool dirtyAfterEolChange = ed.IsDirty && ed.UseCrLf;
                    ed.ToggleLineEndingMode();
                    bool cleanAfterEolRevert = !ed.IsDirty && !ed.UseCrLf;

                    // Editing text marks dirty; reverting exact text restores clean!
                    string orig = ed.EditorText;
                    ed.EditorText = orig + "// edit";
                    bool dirtyAfterEdit = ed.IsDirty;
                    ed.EditorText = orig;
                    bool cleanAfterTextRevert = !ed.IsDirty;

                    // Toggling Word-Wrap hides line gutter cleanly; disabling Word-Wrap restores line gutter!
                    ed.ToggleWordWrapMode();
                    bool wrapHidesGutter = ed.IsWordWrapEnabled && !ed.IsLineGutterVisible;
                    ed.ToggleWordWrapMode();
                    bool unwrapRestoresGutter = !ed.IsWordWrapEnabled && ed.IsLineGutterVisible;

                    ed.Close();
                    return dirtyAfterEolChange && cleanAfterEolRevert &&
                           dirtyAfterEdit && cleanAfterTextRevert &&
                           wrapHidesGutter && unwrapRestoresGutter;
                });

                logs.Add($"SUMMARY: {passed}/{passed + failed} checks passed.");
                logLines = logs;
                return failed == 0 && passed >= 20;
            }
            finally
            {
                ResetSelfTestIsolation();
                try
                {
                    if (Directory.Exists(tempRoot))
                        Directory.Delete(tempRoot, recursive: true);
                }
                catch { }
            }
        }
    }
}

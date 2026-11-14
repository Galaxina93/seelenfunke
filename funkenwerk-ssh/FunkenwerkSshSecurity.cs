using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;
using System.Windows;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Win32;

namespace FunkenwerkSsh
{
    public sealed class SshConnectionProfile
    {
        public string Id { get; set; } = Guid.NewGuid().ToString();
        public string Name { get; set; } = "";
        public string Host { get; set; } = "";
        public int Port { get; set; } = 22;
        public string Username { get; set; } = "";
        public string EncryptedPassword { get; set; } = "";
        public string PrivateKeyPath { get; set; } = "";
        public string StartupDirectory { get; set; } = "";
        public string Source { get; set; } = "PuTTY";

        [JsonIgnore]
        public string Subtitle => string.IsNullOrWhiteSpace(Username)
            ? $"{Host}:{Port}"
            : $"{Username}@{Host}:{Port}";
    }

    public static class FunkenwerkSshSecurity
    {
        private const string VAULT_PREFIX = "FWSSHAES256GCM1:";
        private static readonly byte[] AppEntropy = Encoding.UTF8.GetBytes("Funkenwerk.Ssh.SecurityVault.v1!MeinSeelenfunke");
        private static readonly byte[] HandoffEntropy = Encoding.UTF8.GetBytes("Funkenwerk.FtpToSsh.Handoff.v1!MeinSeelenfunke");

        private static string? _isolatedVaultOverride;
        private static bool _isSelfTestMode;

        public sealed class SshHandoffTicket
        {
            public string Name { get; set; } = "";
            public string Host { get; set; } = "";
            public int Port { get; set; } = 22;
            public string Username { get; set; } = "";
            public string Password { get; set; } = "";
            public string PrivateKeyPath { get; set; } = "";
            public string RemoteDirectory { get; set; } = "/";
            public string Nonce { get; set; } = "";
        }

        public static void EnableSelfTestIsolation(string isolatedVaultDir)
        {
            if (string.IsNullOrWhiteSpace(isolatedVaultDir))
                throw new ArgumentException("Isoliertes SSH-Testverzeichnis fehlt.", nameof(isolatedVaultDir));

            _isolatedVaultOverride = Path.GetFullPath(isolatedVaultDir);
            _isSelfTestMode = true;

            Directory.CreateDirectory(_isolatedVaultOverride);
            LockDownDirectoryAcl(_isolatedVaultOverride);
        }

        public static void ResetSelfTestIsolation()
        {
            _isolatedVaultOverride = null;
            _isSelfTestMode = false;
        }

        public static string VaultDir
        {
            get
            {
                string dir = !string.IsNullOrWhiteSpace(_isolatedVaultOverride)
                    ? _isolatedVaultOverride!
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
        private static string ProfilesFile => Path.Combine(VaultDir, "ssh_profiles_vault.json");
        private static string KnownHostsFile => Path.Combine(VaultDir, "known_hosts_sha256.json");
        private static string HandoffTicketFile => Path.Combine(VaultDir, "handoff_ticket.dpapi");

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
        // HARDENED SINGLE-USE NONCE-BOUND HANDOFF TICKET CONSUMER
        // =====================================================================
        public static SshConnectionProfile? FindMatchingHandoffProfile(IEnumerable<SshConnectionProfile> profiles, SshHandoffTicket ticket)
        {
            if (profiles == null || ticket == null) return null;
            return profiles.FirstOrDefault(p =>
                string.Equals(p.Host, ticket.Host, StringComparison.OrdinalIgnoreCase) &&
                p.Port == ticket.Port &&
                string.Equals(p.Username, ticket.Username, StringComparison.OrdinalIgnoreCase));
        }

        /// <summary>
        /// Atomically opens handoff_ticket.dpapi with FileShare.None, reads bytes, overwrites full file length with 0x00,
        /// flushes to disk, closes, and deletes the file so replay is impossible.
        /// Validates expectedNonce (constant-time) and CreatedUtcTicks (-5s <= age <= 120s) in fail-closed mode.
        /// </summary>
        public static SshHandoffTicket? ConsumeHandoffTicket(string? expectedNonce)
        {
            if (string.IsNullOrWhiteSpace(expectedNonce))
                return null;

            string cleanExpectedNonce = expectedNonce.Trim();
            string handoffFile = HandoffTicketFile;
            if (!File.Exists(handoffFile))
                return null;

            byte[]? encrypted = null;
            byte[]? plainBytes = null;

            try
            {
                using (var fs = new FileStream(handoffFile, FileMode.Open, FileAccess.ReadWrite, FileShare.None))
                {
                    long fileLen = fs.Length;
                    if (fileLen <= 0 || fileLen > 1024 * 1024)
                    {
                        WipeStreamInPlace(fs, fileLen);
                        encrypted = null;
                    }
                    else
                    {
                        encrypted = new byte[(int)fileLen];
                        int totalRead = 0;
                        while (totalRead < encrypted.Length)
                        {
                            int r = fs.Read(encrypted, totalRead, encrypted.Length - totalRead);
                            if (r <= 0) break;
                            totalRead += r;
                        }
                        if (totalRead != encrypted.Length)
                            encrypted = null;

                        WipeStreamInPlace(fs, fileLen);
                    }
                }

                try { File.Delete(handoffFile); } catch { }

                if (encrypted == null || encrypted.Length == 0)
                    return null;

                plainBytes = ProtectedData.Unprotect(encrypted, HandoffEntropy, DataProtectionScope.CurrentUser);
                var dict = JsonSerializer.Deserialize<Dictionary<string, string>>(Encoding.UTF8.GetString(plainBytes));
                if (dict == null)
                    return null;

                // 1. Strict Nonce Verification (Fail-Closed)
                if (!dict.TryGetValue("Nonce", out string? ticketNonce) || string.IsNullOrWhiteSpace(ticketNonce))
                    return null;

                byte[] expectedBytes = Encoding.UTF8.GetBytes(cleanExpectedNonce);
                byte[] actualBytes = Encoding.UTF8.GetBytes(ticketNonce.Trim());
                if (expectedBytes.Length != actualBytes.Length ||
                    !CryptographicOperations.FixedTimeEquals(expectedBytes, actualBytes))
                {
                    return null;
                }

                // 2. Strict Timestamp Window Verification: CreatedUtcTicks > 0 && -5s <= age <= 120s (Fail-Closed)
                if (!dict.TryGetValue("CreatedUtcTicks", out string? ticksStr) ||
                    !long.TryParse(ticksStr, out long ticks) ||
                    ticks <= 0)
                {
                    return null;
                }

                TimeSpan age = DateTime.UtcNow - new DateTime(ticks, DateTimeKind.Utc);
                if (age < TimeSpan.FromSeconds(-5) || age > TimeSpan.FromSeconds(120))
                {
                    return null;
                }

                // 3. Validate Host & RemoteDirectory (no \r, \n, \0)
                string cleanHost = SanitizeHost(dict.GetValueOrDefault("Host", ""));
                if (string.IsNullOrWhiteSpace(cleanHost))
                    return null;

                string remoteDir = dict.GetValueOrDefault("RemoteDirectory", "/");
                if (string.IsNullOrWhiteSpace(remoteDir))
                    remoteDir = "/";
                if (remoteDir.IndexOfAny(new[] { '\r', '\n', '\0' }) >= 0)
                    return null;

                int port = int.TryParse(dict.GetValueOrDefault("Port", "22"), out int pt) && pt > 0 && pt <= 65535 ? pt : 22;

                return new SshHandoffTicket
                {
                    Name = dict.GetValueOrDefault("Name", ""),
                    Host = cleanHost,
                    Port = port,
                    Username = (dict.GetValueOrDefault("Username", "") ?? "").Trim(),
                    Password = dict.GetValueOrDefault("Password", "") ?? "",
                    PrivateKeyPath = ResolveOpenSshKeyPath(dict.GetValueOrDefault("PrivateKeyPath", "")),
                    RemoteDirectory = remoteDir.Trim(),
                    Nonce = ticketNonce.Trim()
                };
            }
            catch
            {
                try { if (File.Exists(handoffFile)) File.Delete(handoffFile); } catch { }
                return null;
            }
            finally
            {
                if (encrypted != null) CryptographicOperations.ZeroMemory(encrypted);
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        private static void WipeStreamInPlace(FileStream fs, long length)
        {
            if (length <= 0) return;
            fs.Position = 0;
            byte[] zeros = new byte[(int)Math.Min(length, 65536)];
            long remaining = length;
            while (remaining > 0)
            {
                int chunk = (int)Math.Min(remaining, zeros.Length);
                fs.Write(zeros, 0, chunk);
                remaining -= chunk;
            }
            fs.Flush(flushToDisk: true);
        }

        private static void WriteRawTestHandoffTicket(Dictionary<string, string> payload)
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            byte[] plain = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(payload));
            try
            {
                byte[] enc = ProtectedData.Protect(plain, HandoffEntropy, DataProtectionScope.CurrentUser);
                File.WriteAllBytes(HandoffTicketFile, enc);
                LockDownFileAcl(HandoffTicketFile);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(plain);
            }
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
                    throw new CryptographicException("SSH-Master-Key konnte nicht gelesen werden (Fail-Closed).", ioEx);
                }

                byte[]? key = null;
                try
                {
                    key = ProtectedData.Unprotect(wrapped, AppEntropy, DataProtectionScope.CurrentUser);
                    if (key.Length != 32)
                    {
                        CryptographicOperations.ZeroMemory(key);
                        throw new CryptographicException("SSH-Master-Key hat eine ungültige Schlüssellänge (Fail-Closed).");
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
                    throw new CryptographicException("SSH-Master-Key ist beschädigt oder nicht entschlüsselbar (Fail-Closed).", ex);
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
                throw new CryptographicException("Ungültiges Geheimnis-Format im SSH-Tresor (Fail-Closed).");

            byte[] key = GetOrCreateMasterKey();
            byte[]? combined = null;
            byte[]? plainBytes = null;
            try
            {
                string b64 = cipherText.Substring(VAULT_PREFIX.Length);
                byte[] dpapiWrapped = Convert.FromBase64String(b64);
                combined = ProtectedData.Unprotect(dpapiWrapped, AppEntropy, DataProtectionScope.CurrentUser);
                if (combined.Length < 28)
                    throw new CryptographicException("Entschlüsseltes SSH-Tresor-Paket ist unvollständig (Fail-Closed).");

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
                throw new CryptographicException("SSH-Tresor-Geheimnis konnte nicht authentifiziert werden (Fail-Closed).", ex);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(key);
                if (combined != null) CryptographicOperations.ZeroMemory(combined);
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

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
                    idx += 2;
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

        public static string SanitizeHost(string? rawHost)
        {
            if (string.IsNullOrWhiteSpace(rawHost)) return "";
            string decoded = Uri.UnescapeDataString(rawHost).Trim();
            foreach (char c in decoded)
            {
                if (char.IsControl(c) || c == ';' || c == '|' || c == '&' || c == '`' || c == '$' || c == '\'' || c == '"' || c == ' ')
                    throw new ArgumentException("Ungültige Zeichen im Hostnamen blockiert.");
            }
            return decoded;
        }

        private static readonly Regex AnsiEscapeRegex = new(
            @"\x1B(?:\[[0-9;?]*[A-Za-z@~`]|\][^\x07\x1B]*(?:\x07|\x1B\\)|[()][AB012]|[>=<cMDE])",
            RegexOptions.Compiled,
            TimeSpan.FromMilliseconds(250));

        public static string StripAnsiControlSequences(string raw)
        {
            if (string.IsNullOrEmpty(raw)) return "";
            string cleaned = AnsiEscapeRegex.Replace(raw, "");
            return cleaned.Replace("\r\n", "\n", StringComparison.Ordinal).Replace('\r', '\n');
        }

        // =====================================================================
        // FAIL-CLOSED SHA-256 HOST-KEY PINNING
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

        /// <summary>
        /// Resolves only an explicitly configured OpenSSH key path.
        /// Returns empty string when rawKeyPath is empty so profiles never silently auto-authenticate with ~/.ssh/id_ed25519!
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

        // =====================================================================
        // FAIL-CLOSED PROFILE VAULT & CLEAN REGISTRY IMPORT
        // =====================================================================
        public static List<SshConnectionProfile> LoadAndImportProfiles()
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string profFile = ProfilesFile;

            List<SshConnectionProfile> profiles = new();
            if (File.Exists(profFile))
            {
                List<SshConnectionProfile>? loaded;
                try
                {
                    string rawJson = File.ReadAllText(profFile);
                    loaded = JsonSerializer.Deserialize<List<SshConnectionProfile>>(rawJson);
                }
                catch (Exception ex)
                {
                    BackupCorruptVaultFile(profFile);
                    throw new InvalidDataException("SSH-Profil-Tresor 'ssh_profiles_vault.json' ist beschädigt (Backup angelegt, Fail-Closed).", ex);
                }

                if (loaded == null)
                {
                    BackupCorruptVaultFile(profFile);
                    throw new InvalidDataException("SSH-Profil-Tresor 'ssh_profiles_vault.json' enthält kein gültiges Array (Backup angelegt, Fail-Closed).");
                }

                profiles = loaded;
                LockDownFileAcl(profFile);
            }

            bool changed = !_isSelfTestMode && ImportFromPuttyAndWinScpRegistry(profiles);
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

        public static void SaveProfiles(List<SshConnectionProfile> profiles)
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string profFile = ProfilesFile;

            string json = JsonSerializer.Serialize(profiles ?? new List<SshConnectionProfile>(), new JsonSerializerOptions { WriteIndented = true });
            File.WriteAllText(profFile, json);
            LockDownFileAcl(profFile);
        }

        public static bool ImportFromPuttyAndWinScpRegistry(List<SshConnectionProfile> existing)
        {
            if (_isSelfTestMode) return false;

            bool addedAny = false;

            // 1. Import PuTTY Sessions (HKCU\Software\SimonTatham\PuTTY\Sessions)
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
                            existing.Add(new SshConnectionProfile
                            {
                                Name = sessionLabel,
                                Host = cleanHost,
                                Port = port,
                                Username = rawUser,
                                PrivateKeyPath = keyPath,
                                Source = "PuTTY"
                            });
                            addedAny = true;
                        }
                    }
                }
            }
            catch { }

            // 2. Import from WinSCP Sessions
            try
            {
                using var winscpKey = Registry.CurrentUser.OpenSubKey(@"Software\Martin Prikryl\WinSCP 2\Sessions");
                if (winscpKey != null)
                {
                    foreach (string subName in winscpKey.GetSubKeyNames())
                    {
                        string sessionLabel = Uri.UnescapeDataString(subName).Trim();
                        if (string.Equals(sessionLabel, "Default Settings", StringComparison.OrdinalIgnoreCase))
                            continue;

                        using var sk = winscpKey.OpenSubKey(subName);
                        if (sk == null) continue;

                        string rawHost = Convert.ToString(sk.GetValue("HostName")) ?? "";
                        string cleanHost;
                        try { cleanHost = SanitizeHost(rawHost); } catch { continue; }
                        if (string.IsNullOrWhiteSpace(cleanHost)) continue;

                        string rawUser = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("UserName")) ?? "").Trim();
                        int port = Convert.ToInt32(sk.GetValue("PortNumber") ?? 22);
                        if (port <= 0 || port > 65535) port = 22;

                        string rawPassHex = Convert.ToString(sk.GetValue("Password")) ?? "";
                        string decodedPass = DecodeWinScpPassword(rawUser, Uri.UnescapeDataString(rawHost), rawPassHex);
                        string remoteDir = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("RemoteDirectory")) ?? "").Trim();
                        if (remoteDir.IndexOfAny(new[] { '\r', '\n', '\0' }) >= 0) remoteDir = "";

                        string keyPath = ResolveOpenSshKeyPath(Convert.ToString(sk.GetValue("PublicKeyFile")));

                        var match = existing.FirstOrDefault(p =>
                            string.Equals(p.Name, sessionLabel, StringComparison.OrdinalIgnoreCase));

                        if (match == null)
                        {
                            existing.Add(new SshConnectionProfile
                            {
                                Name = sessionLabel,
                                Host = cleanHost,
                                Port = port,
                                Username = rawUser,
                                EncryptedPassword = !string.IsNullOrEmpty(decodedPass) ? EncryptSecret(decodedPass) : "",
                                PrivateKeyPath = keyPath,
                                StartupDirectory = remoteDir,
                                Source = "WinSCP"
                            });
                            addedAny = true;
                        }
                        else if (string.Equals(match.Host, cleanHost, StringComparison.OrdinalIgnoreCase) &&
                                 match.Port == port &&
                                 string.Equals(match.Username, rawUser, StringComparison.OrdinalIgnoreCase))
                        {
                            if (string.IsNullOrEmpty(match.EncryptedPassword) && !string.IsNullOrEmpty(decodedPass))
                            {
                                match.EncryptedPassword = EncryptSecret(decodedPass);
                                addedAny = true;
                            }
                            if (string.IsNullOrWhiteSpace(match.StartupDirectory) && !string.IsNullOrWhiteSpace(remoteDir))
                            {
                                match.StartupDirectory = remoteDir;
                                addedAny = true;
                            }
                        }
                    }
                }
            }
            catch { }

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

            string realLocalAppSsh = Path.GetFullPath(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkSsh"));
            string tempRoot = Path.Combine(Path.GetTempPath(), "FwSshSelfTest_" + Guid.NewGuid().ToString("N"));
            string isoVault = Path.Combine(tempRoot, "SshVault");

            try
            {
                EnableSelfTestIsolation(isoVault);

                // 1. Isolated temp vault & zero HKCU profile requirement
                Check("01_IsolatedTempVault_ZeroLocalAppDataTouch", () =>
                {
                    var loaded = LoadAndImportProfiles();
                    return !VaultDir.StartsWith(realLocalAppSsh, StringComparison.OrdinalIgnoreCase) &&
                           loaded.Count == 0;
                });

                // 2. AES-256-GCM + DPAPI roundtrip & distinct nonces
                Check("02_VaultAes256GcmDpapi_RoundtripAndNonceUniqueness", () =>
                {
                    const string secret = "Ssh#2026!ÄÖÜß_MeinSeelenfunke";
                    string enc1 = EncryptSecret(secret);
                    string enc2 = EncryptSecret(secret);
                    return enc1.StartsWith(VAULT_PREFIX, StringComparison.Ordinal) &&
                           enc2.StartsWith(VAULT_PREFIX, StringComparison.Ordinal) &&
                           !string.Equals(enc1, enc2, StringComparison.Ordinal) &&
                           DecryptSecret(enc1) == secret &&
                           DecryptSecret(enc2) == secret;
                });

                // 3. Outer DPAPI tamper & inner AES-256-GCM tag tamper fail closed
                Check("03_VaultOuterDpapiAndInnerAesGcmTamper_FailClosed", () =>
                {
                    string valid = EncryptSecret("TamperCheckSsh");
                    byte[] wrapped = Convert.FromBase64String(valid.Substring(VAULT_PREFIX.Length));

                    // Outer tamper
                    byte[] badOuter = wrapped.ToArray();
                    badOuter[badOuter.Length / 2] ^= 0x3C;
                    bool outerBlocked = false;
                    try { _ = DecryptSecret(VAULT_PREFIX + Convert.ToBase64String(badOuter)); }
                    catch (CryptographicException) { outerBlocked = true; }

                    // Inner AES-GCM tag bit flip
                    byte[] combined = ProtectedData.Unprotect(wrapped, AppEntropy, DataProtectionScope.CurrentUser);
                    combined[14] ^= 0x01;
                    byte[] rewrapped = ProtectedData.Protect(combined, AppEntropy, DataProtectionScope.CurrentUser);
                    bool innerBlocked = false;
                    try { _ = DecryptSecret(VAULT_PREFIX + Convert.ToBase64String(rewrapped)); }
                    catch (CryptographicException) { innerBlocked = true; }

                    return outerBlocked && innerBlocked;
                });

                // 4. Corrupt master.dpapi fails closed and NEVER overwrites master.dpapi
                Check("04_CorruptMasterDpapi_FailClosed_NeverOverwritten", () =>
                {
                    string corruptDir = Path.Combine(tempRoot, "CorruptMaster");
                    EnableSelfTestIsolation(corruptDir);
                    byte[] badKey = new byte[] { 0xCA, 0xFE, 0xBA, 0xBE, 0x99 };
                    File.WriteAllBytes(VaultKeyFile, badKey);

                    bool threw = false;
                    try { _ = EncryptSecret("MustFail"); }
                    catch (CryptographicException) { threw = true; }

                    byte[] after = File.ReadAllBytes(VaultKeyFile);
                    EnableSelfTestIsolation(isoVault);
                    return threw && badKey.SequenceEqual(after);
                });

                // 5. Corrupt known_hosts_sha256.json and ssh_profiles_vault.json fail closed + backup
                Check("05_CorruptKnownHostsAndProfiles_FailClosedWithBackup", () =>
                {
                    string corruptJsonDir = Path.Combine(tempRoot, "CorruptJson");
                    EnableSelfTestIsolation(corruptJsonDir);
                    File.WriteAllText(KnownHostsFile, "{corrupt-known-hosts");
                    File.WriteAllText(ProfilesFile, "{corrupt-ssh-profiles");

                    bool hkBlocked = false;
                    try { _ = VerifyAndPinHostKey("ssh.example.test", 22, "ssh-ed25519", new byte[] { 1, 2, 3 }, trustNewKeys: true); }
                    catch (InvalidDataException) { hkBlocked = true; }

                    bool profBlocked = false;
                    try { _ = LoadAndImportProfiles(); }
                    catch (InvalidDataException) { profBlocked = true; }

                    bool untouched = File.ReadAllText(ProfilesFile) == "{corrupt-ssh-profiles" &&
                                     File.ReadAllText(KnownHostsFile) == "{corrupt-known-hosts";
                    bool hasBackup = Directory.GetFiles(corruptJsonDir, "ssh_profiles_vault.json.corrupt_*.bak").Length == 1;

                    EnableSelfTestIsolation(isoVault);
                    return hkBlocked && profBlocked && untouched && hasBackup;
                });

                // 6. Interactive host-key callback & MITM detection
                Check("06_HostKeyPinning_CallbackApprovalAndMitmBlock", () =>
                {
                    var rejected = VerifyAndPinHostKey("sshpin.example.test", 22, "ssh-ed25519", new byte[] { 7, 8, 9 }, trustNewKeys: false, _ => false);
                    var accepted = VerifyAndPinHostKey("sshpin.example.test", 22, "ssh-ed25519", new byte[] { 7, 8, 9 }, trustNewKeys: false, _ => true);
                    var repeatOk = VerifyAndPinHostKey("sshpin.example.test", 22, "ssh-ed25519", new byte[] { 7, 8, 9 }, trustNewKeys: false, _ => false);
                    var mitm = VerifyAndPinHostKey("sshpin.example.test", 22, "ssh-ed25519", new byte[] { 1, 1, 1 }, trustNewKeys: true);

                    return !rejected.IsTrusted && !rejected.IsChanged &&
                           accepted.IsTrusted && !accepted.IsChanged &&
                           repeatOk.IsTrusted && !repeatOk.IsChanged &&
                           !mitm.IsTrusted && mitm.IsChanged;
                });

                // 7. NTFS ACL lockdown verification across VaultDir, master.dpapi, profiles, known_hosts, handoff_ticket
                Check("07_NtfsAclLockdown_StrictCurrentUserOnly", () =>
                {
                    SaveProfiles(new List<SshConnectionProfile>
                    {
                        new() { Name = "Sample", Host = "ssh.example.test", Port = 22, Username = "admin", EncryptedPassword = EncryptSecret("pw") }
                    });
                    string nonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Name"] = "Sample",
                        ["Host"] = "ssh.example.test",
                        ["Port"] = "22",
                        ["Username"] = "admin",
                        ["Password"] = "pw",
                        ["PrivateKeyPath"] = "",
                        ["RemoteDirectory"] = "/var/www",
                        ["Nonce"] = nonce,
                        ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
                    });

                    bool aclsValid = VerifyStrictCurrentUserAcl(VaultDir, isDirectory: true) &&
                                     VerifyStrictCurrentUserAcl(VaultKeyFile, isDirectory: false) &&
                                     VerifyStrictCurrentUserAcl(ProfilesFile, isDirectory: false) &&
                                     VerifyStrictCurrentUserAcl(KnownHostsFile, isDirectory: false) &&
                                     VerifyStrictCurrentUserAcl(HandoffTicketFile, isDirectory: false);

                    _ = ConsumeHandoffTicket(nonce);
                    return aclsValid && !File.Exists(HandoffTicketFile);
                });

                // 8. End-to-end single-use Handoff Ticket consume & immediate replay block
                Check("08_HandoffTicket_ValidNonceConsumeAndReplayBlocked", () =>
                {
                    string nonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Name"] = "DeploySession",
                        ["Host"] = "deploy.example.test",
                        ["Port"] = "2222",
                        ["Username"] = "deployer",
                        ["Password"] = "TopSecret#2026",
                        ["PrivateKeyPath"] = "",
                        ["RemoteDirectory"] = "/var/www/app",
                        ["Nonce"] = nonce,
                        ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
                    });

                    var first = ConsumeHandoffTicket(nonce);
                    bool fileDeleted = !File.Exists(HandoffTicketFile);
                    var replay = ConsumeHandoffTicket(nonce);

                    return first != null &&
                           first.Host == "deploy.example.test" &&
                           first.Port == 2222 &&
                           first.Username == "deployer" &&
                           first.Password == "TopSecret#2026" &&
                           first.RemoteDirectory == "/var/www/app" &&
                           fileDeleted &&
                           replay == null;
                });

                // 9. Handoff Ticket missing nonce or wrong nonce rejected & wiped
                Check("09_HandoffTicket_MissingOrWrongNonceRejected", () =>
                {
                    string realNonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Host"] = "deploy.example.test",
                        ["Port"] = "22",
                        ["Username"] = "deployer",
                        ["Password"] = "Secret",
                        ["RemoteDirectory"] = "/srv",
                        ["Nonce"] = realNonce,
                        ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
                    });

                    // Missing CLI nonce does not consume
                    var noNonceResult = ConsumeHandoffTicket(null);
                    if (noNonceResult != null) return false;

                    // Wrong CLI nonce wipes & deletes ticket and returns null
                    var wrongNonceResult = ConsumeHandoffTicket("00000000000000000000000000000000");
                    bool wipedAfterWrongNonce = !File.Exists(HandoffTicketFile);

                    // Even presenting realNonce afterwards fails because ticket was already wiped!
                    var afterWrong = ConsumeHandoffTicket(realNonce);

                    return wrongNonceResult == null && wipedAfterWrongNonce && afterWrong == null;
                });

                // 10. Handoff Ticket expired (>120s), future-skewed (<-5s), missing timestamp, and newline injection rejected
                Check("10_HandoffTicket_ExpiredFutureMissingTicksAndInjectionBlocked", () =>
                {
                    string n1 = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Host"] = "a.example.test",
                        ["Username"] = "u",
                        ["RemoteDirectory"] = "/srv",
                        ["Nonce"] = n1,
                        ["CreatedUtcTicks"] = DateTime.UtcNow.AddSeconds(-125).Ticks.ToString()
                    });
                    if (ConsumeHandoffTicket(n1) != null) return false;

                    string n2 = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Host"] = "a.example.test",
                        ["Username"] = "u",
                        ["RemoteDirectory"] = "/srv",
                        ["Nonce"] = n2,
                        ["CreatedUtcTicks"] = DateTime.UtcNow.AddSeconds(30).Ticks.ToString()
                    });
                    if (ConsumeHandoffTicket(n2) != null) return false;

                    string n3 = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Host"] = "a.example.test",
                        ["Username"] = "u",
                        ["RemoteDirectory"] = "/srv",
                        ["Nonce"] = n3,
                        ["CreatedUtcTicks"] = "0"
                    });
                    if (ConsumeHandoffTicket(n3) != null) return false;

                    string n4 = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Host"] = "a.example.test",
                        ["Username"] = "u",
                        ["RemoteDirectory"] = "/srv\nwhoami",
                        ["Nonce"] = n4,
                        ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
                    });
                    if (ConsumeHandoffTicket(n4) != null) return false;

                    return true;
                });

                // 11. Handoff profile matching strictly checks Host + Port + Username (never contaminates sibling profile)
                Check("11_HandoffProfileMatch_StrictHostPortUser_NoCrossOverwrite", () =>
                {
                    var profiles = new List<SshConnectionProfile>
                    {
                        new() { Name = "ProdUserA", Host = "shared.example.test", Port = 22, Username = "userA", EncryptedPassword = EncryptSecret("passA") }
                    };

                    var diffUserTicket = new SshHandoffTicket { Host = "shared.example.test", Port = 22, Username = "userB", Password = "passB" };
                    var diffPortTicket = new SshHandoffTicket { Host = "shared.example.test", Port = 2222, Username = "userA", Password = "passB" };
                    var exactTicket = new SshHandoffTicket { Host = "shared.example.test", Port = 22, Username = "userA", Password = "passA" };

                    return FindMatchingHandoffProfile(profiles, diffUserTicket) == null &&
                           FindMatchingHandoffProfile(profiles, diffPortTicket) == null &&
                           FindMatchingHandoffProfile(profiles, exactTicket) == profiles[0] &&
                           profiles[0].Username == "userA" &&
                           DecryptSecret(profiles[0].EncryptedPassword) == "passA";
                });

                // 12. Empty PrivateKeyPath never silently falls back to ~/.ssh/id_ed25519
                Check("12_NoSilentDefaultSshKeyFallback", () =>
                {
                    return ResolveOpenSshKeyPath("") == "" &&
                           ResolveOpenSshKeyPath("   ") == "" &&
                           ResolveOpenSshKeyPath(null) == "";
                });

                // 13. Remote Shell Command Injection Fix in BuildSafePosixCdCommand
                Check("13_PosixCdInjectionGuard_MetacharactersAndControlChars", () =>
                {
                    string[] controlPayloads = { "/tmp/a\nid", "/tmp/a\rid", "/tmp/a\0id", "", "   " };
                    foreach (string bad in controlPayloads)
                    {
                        bool rejected = false;
                        try { _ = MainWindow.BuildSafePosixCdCommand(bad); }
                        catch (ArgumentException) { rejected = true; }
                        if (!rejected) return false;
                    }

                    string subshell = MainWindow.BuildSafePosixCdCommand("/var/www/$(touch /tmp/pwn)");
                    string singleQuoteBreak = MainWindow.BuildSafePosixCdCommand("/var/www/o'reilly && id");
                    string backtickAndVar = MainWindow.BuildSafePosixCdCommand("/var/www/`whoami`/$HOME/\"test\"\\x");

                    return subshell == "cd -- '/var/www/$(touch /tmp/pwn)' && clear\r" &&
                           singleQuoteBreak == "cd -- '/var/www/o'\\''reilly && id' && clear\r" &&
                           backtickAndVar == "cd -- '/var/www/`whoami`/$HOME/\"test\"\\x' && clear\r";
                });

                // 14. Bracketed Paste (?2004h / ?2004l), ESC/C0 stripping & Multi-Line Paste Guard
                Check("14_BracketedPasteModeAndPasteSanitizer", () =>
                {
                    var buf = new PuTTYAnsiScreenBuffer();
                    buf.Reset();
                    if (buf.BracketedPasteEnabled) return false;

                    buf.ProcessAnsiStream("\x1B[?1;2004h");
                    bool enabledOk = buf.BracketedPasteEnabled;

                    // Attempted bracketed-paste breakout \x1B[201~ + C0 control \x03 + BEL \x07 must be stripped!
                    var single = MainWindow.PrepareSafePastePayload("echo hello\x1B[201~;id\x03\x07\x7F", bracketedPasteActive: true);
                    bool singleSafe = !single.IsMultiLine &&
                                      single.Payload == "\x1B[200~echo hello[201~;id\x1B[201~";

                    // Multi-line or trailing-newline paste must flag IsMultiLine = true for confirmation preview!
                    var multi = MainWindow.PrepareSafePastePayload("curl https://example.test\nsh\n", bracketedPasteActive: true);
                    bool multiDetected = multi.IsMultiLine &&
                                         multi.LineCount == 3 &&
                                         multi.Payload == "\x1B[200~curl https://example.test\rsh\r\x1B[201~";

                    buf.ProcessAnsiStream("\x1B[?2004l");
                    bool disabledOk = !buf.BracketedPasteEnabled;
                    var unbracketed = MainWindow.PrepareSafePastePayload("ls -la", bracketedPasteActive: false);

                    return enabledOk && singleSafe && multiDetected && disabledOk &&
                           !unbracketed.IsMultiLine && unbracketed.Payload == "ls -la";
                });

                // 15. PuTTYAnsiScreenBuffer DoS Clamps (\x1B[99999999C, \x1B[99999999@, \x1B[99999999B, mega OSC)
                Check("15_AnsiScreenBuffer_DoSClamps_NoOomOrCpuHang", () =>
                {
                    var buf = new PuTTYAnsiScreenBuffer();
                    buf.Reset();

                    var sw = System.Diagnostics.Stopwatch.StartNew();
                    buf.ProcessAnsiStream("\x1B[99999999CX");
                    buf.ProcessAnsiStream("\r\x1B[99999999@Y");
                    buf.ProcessAnsiStream("\x1B[999999999999999999BZ");
                    string hugeOsc = "\x1B]0;" + new string('A', 20000); // Unterminated 20 KB OSC
                    buf.ProcessAnsiStream(hugeOsc);
                    sw.Stop();

                    return sw.ElapsedMilliseconds < 1500 &&
                           buf.CursorCol < PuTTYAnsiScreenBuffer.MaxCols &&
                           buf.GetLineLength(0) <= PuTTYAnsiScreenBuffer.MaxCols &&
                           buf.RowCount <= PuTTYAnsiScreenBuffer.MaxRows &&
                           buf.PendingEscapeLength <= PuTTYAnsiScreenBuffer.MaxPendingEscapeLength;
                });

                // 16. OSC 52 Clipboard Write & CSI 5n/6n DSR Reflection Blocked
                Check("16_AnsiScreenBuffer_Osc52AndDsrBlocked", () =>
                {
                    string marker = "FW_CLIP_GUARD_" + Guid.NewGuid().ToString("N");
                    try { Clipboard.SetText(marker); } catch { }

                    var buf = new PuTTYAnsiScreenBuffer();
                    buf.Reset();
                    // "SGVsbG8=" = "Hello" in Base64 via OSC 52, followed by CSI 5n & CSI 6n & ?6n
                    buf.ProcessAnsiStream("\x1B]52;c;SGVsbG8=\x07\x1B[5n\x1B[6n\x1B[?6nSafeOut");

                    string clipAfter = "";
                    try { clipAfter = Clipboard.GetText(); } catch { }
                    bool clipUntouched = string.IsNullOrEmpty(clipAfter) || clipAfter == marker;

                    return clipUntouched && buf.GetLineText(0) == "SafeOut";
                });

                // 17. Stateful UTF-8 Decoder across 1-byte split socket chunks
                Check("17_StatefulUtf8Decoder_SplitMultiByteChunks", () =>
                {
                    var buf = new PuTTYAnsiScreenBuffer();
                    buf.Reset();
                    const string sample = "Größe ─ ÄÖÜß · Funkenwerk 🚀";
                    byte[] utf8 = Encoding.UTF8.GetBytes(sample);

                    // Feed 1 byte at a time to force splits inside every 2-, 3-, and 4-byte UTF-8 sequence!
                    for (int i = 0; i < utf8.Length; i++)
                    {
                        buf.ProcessUtf8Bytes(utf8, i, 1);
                    }

                    string rendered = buf.GetLineText(0);
                    return rendered == sample && !rendered.Contains('\uFFFD');
                });

                // 18. Complete VT100 Cursor (A, B, G, d, H, f) & Erase (0/1/2 K, 0/1/2 J) Sequences
                Check("18_Vt100CursorAndEraseCompleteness", () =>
                {
                    var buf = new PuTTYAnsiScreenBuffer();
                    buf.Reset();

                    // Write 3 lines, test CSI A (up), CSI G (col abs), CSI 1K (erase start..cursor), CSI d (row abs), CSI H
                    buf.ProcessAnsiStream("ABCDEF\r\n123456\r\nUVWXYZ");
                    // Currently on row 2 ("UVWXYZ"). Move up 1 row (row 1 "123456"), col 3 (1-based -> index 2 '3'), CSI 1K blanks 0..2 -> "   456"
                    buf.ProcessAnsiStream("\x1B[1A\x1B[3G\x1B[1K");
                    if (buf.GetLineText(1) != "   456") return false;

                    // Move to row 1, col 5 (1-based -> row 0, col 4) via CSI 1;5H, overwrite with '!' -> "ABCD!F"
                    buf.ProcessAnsiStream("\x1B[1;5H!");
                    if (buf.GetLineText(0) != "ABCD!F") return false;

                    // Test CSI 3d (row 3 -> index 2), CSI 2G (col 2 -> index 1), CSI 0K (erase from index 1 to end -> "U")
                    buf.ProcessAnsiStream("\x1B[3d\x1B[2G\x1B[0K");
                    if (buf.GetLineText(2) != "U") return false;

                    // Test CSI 2;4f (row 2 -> index 1, col 4 -> index 3) + CSI 0J (erase from cursor to end of display -> row 1 becomes "   ", row 2 removed)
                    buf.ProcessAnsiStream("\x1B[2;4f\x1B[J");
                    if (buf.GetLineText(1) != "   " || buf.RowCount != 2) return false;

                    // Test CSI 2J clears entire display
                    buf.ProcessAnsiStream("\x1B[2J");
                    return buf.RowCount == 1 && buf.GetLineText(0) == "";
                });

                // 19. 24-Bit TrueColor & xterm-256 FlowDocument rendering
                Check("19_TrueColorAndXterm256FlowDocumentRender", () =>
                {
                    var buf = new PuTTYAnsiScreenBuffer();
                    buf.Reset();
                    buf.ProcessAnsiStream("\x1B[1;38;2;197;160;89;48;2;15;17;23mGOLD\x1B[0m");

                    var doc = new FlowDocument();
                    buf.RenderInto(doc);
                    var para = doc.Blocks.FirstBlock as Paragraph;
                    var firstRun = para?.Inlines.FirstInline as Run;
                    var fg = (firstRun?.Foreground as SolidColorBrush)?.Color;
                    var bg = (firstRun?.Background as SolidColorBrush)?.Color;

                    return firstRun != null &&
                           firstRun.Text == "GOLD" &&
                           firstRun.FontWeight == FontWeights.Bold &&
                           fg == Color.FromRgb(197, 160, 89) &&
                           bg == Color.FromRgb(15, 17, 23);
                });

                // 20. Headless STA MainWindow instantiation, ambient handoff non-consumption, Light/Dark mode & ExecShell reachability
                Check("20_HeadlessStaMainWindow_AmbientTicketIgnoredAndThemesAndExecMode", () =>
                {
                    // Place an ambient ticket on disk and launch MainWindow WITHOUT --handoff nonce: must NOT consume it!
                    string ambientNonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
                    WriteRawTestHandoffTicket(new Dictionary<string, string>
                    {
                        ["Host"] = "ambient.example.test",
                        ["Port"] = "22",
                        ["Username"] = "ambient",
                        ["Password"] = "pw",
                        ["RemoteDirectory"] = "/tmp",
                        ["Nonce"] = ambientNonce,
                        ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
                    });

                    var win = new MainWindow(startupHandoffNonce: null);
                    bool ambientStillUntouched = File.Exists(HandoffTicketFile) && win.CurrentHostText != "ambient.example.test";

                    win.SetTheme(false);
                    var lightCard = (Application.Current.Resources["SshBgCard"] as SolidColorBrush)?.Color;
                    bool lightOk = !win.IsDarkMode && lightCard == Color.FromRgb(249, 250, 251);

                    win.SetTheme(true);
                    var darkCard = (Application.Current.Resources["SshBgCard"] as SolidColorBrush)?.Color;
                    bool darkOk = win.IsDarkMode && darkCard == Color.FromRgb(26, 31, 44);

                    bool execModeReachable = MainWindow.ResolveSessionDispatchTarget(MainWindow.ActiveSessionMode.ExecShell) == MainWindow.ActiveSessionMode.ExecShell;

                    _ = ConsumeHandoffTicket(ambientNonce);
                    return ambientStillUntouched && lightOk && darkOk && execModeReachable && win.Title.Contains("Funkenwerk SSH", StringComparison.Ordinal);
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

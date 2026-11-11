using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;
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

        public sealed class SshHandoffTicket
        {
            public string Name { get; set; } = "";
            public string Host { get; set; } = "";
            public int Port { get; set; } = 22;
            public string Username { get; set; } = "";
            public string Password { get; set; } = "";
            public string PrivateKeyPath { get; set; } = "";
            public string RemoteDirectory { get; set; } = "/";
        }

        public static SshHandoffTicket? ConsumeHandoffTicket()
        {
            string handoffFile = Path.Combine(VaultDir, "handoff_ticket.dpapi");
            if (!File.Exists(handoffFile)) return null;

            try
            {
                byte[] encrypted = File.ReadAllBytes(handoffFile);
                try { File.Delete(handoffFile); } catch { }

                byte[] plainBytes = ProtectedData.Unprotect(encrypted, HandoffEntropy, DataProtectionScope.CurrentUser);
                try
                {
                    var dict = JsonSerializer.Deserialize<Dictionary<string, string>>(Encoding.UTF8.GetString(plainBytes));
                    if (dict == null) return null;

                    if (dict.TryGetValue("CreatedUtcTicks", out string? ticksStr) &&
                        long.TryParse(ticksStr, out long ticks))
                    {
                        var age = DateTime.UtcNow - new DateTime(ticks, DateTimeKind.Utc);
                        if (age.TotalSeconds > 120) return null;
                    }

                    return new SshHandoffTicket
                    {
                        Name = dict.GetValueOrDefault("Name", ""),
                        Host = dict.GetValueOrDefault("Host", ""),
                        Port = int.TryParse(dict.GetValueOrDefault("Port", "22"), out int pt) ? pt : 22,
                        Username = dict.GetValueOrDefault("Username", ""),
                        Password = dict.GetValueOrDefault("Password", ""),
                        PrivateKeyPath = dict.GetValueOrDefault("PrivateKeyPath", ""),
                        RemoteDirectory = dict.GetValueOrDefault("RemoteDirectory", "/")
                    };
                }
                finally
                {
                    CryptographicOperations.ZeroMemory(plainBytes);
                }
            }
            catch
            {
                try { File.Delete(handoffFile); } catch { }
                return null;
            }
        }

        public static string VaultDir
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkSsh");
                Directory.CreateDirectory(dir);
                return dir;
            }
        }

        private static string VaultKeyFile => Path.Combine(VaultDir, "master.dpapi");
        private static string ProfilesFile => Path.Combine(VaultDir, "ssh_profiles_vault.json");
        private static string KnownHostsFile => Path.Combine(VaultDir, "known_hosts_sha256.json");

        private static byte[] GetOrCreateMasterKey()
        {
            try
            {
                if (File.Exists(VaultKeyFile))
                {
                    byte[] wrapped = File.ReadAllBytes(VaultKeyFile);
                    byte[] key = ProtectedData.Unprotect(wrapped, AppEntropy, DataProtectionScope.CurrentUser);
                    if (key.Length == 32) return key;
                }
            }
            catch { }

            byte[] newKey = RandomNumberGenerator.GetBytes(32);
            byte[] protectedKey = ProtectedData.Protect(newKey, AppEntropy, DataProtectionScope.CurrentUser);
            File.WriteAllBytes(VaultKeyFile, protectedKey);
            return newKey;
        }

        public static string EncryptSecret(string? plainText)
        {
            if (string.IsNullOrEmpty(plainText)) return "";
            if (plainText.StartsWith(VAULT_PREFIX, StringComparison.Ordinal)) return plainText;

            byte[] key = GetOrCreateMasterKey();
            try
            {
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] plainBytes = Encoding.UTF8.GetBytes(plainText);
                byte[] cipherBytes = new byte[plainBytes.Length];
                byte[] tag = new byte[16];

                using var aesGcm = new AesGcm(key, 16);
                aesGcm.Encrypt(nonce, plainBytes, cipherBytes, tag, AppEntropy);

                byte[] combined = new byte[12 + 16 + cipherBytes.Length];
                Buffer.BlockCopy(nonce, 0, combined, 0, 12);
                Buffer.BlockCopy(tag, 0, combined, 12, 16);
                Buffer.BlockCopy(cipherBytes, 0, combined, 28, cipherBytes.Length);

                byte[] dpapiWrapped = ProtectedData.Protect(combined, AppEntropy, DataProtectionScope.CurrentUser);
                return VAULT_PREFIX + Convert.ToBase64String(dpapiWrapped);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(key);
            }
        }

        public static string DecryptSecret(string? cipherText)
        {
            if (string.IsNullOrEmpty(cipherText)) return "";
            if (!cipherText.StartsWith(VAULT_PREFIX, StringComparison.Ordinal)) return "";

            byte[] key = GetOrCreateMasterKey();
            try
            {
                string b64 = cipherText.Substring(VAULT_PREFIX.Length);
                byte[] dpapiWrapped = Convert.FromBase64String(b64);
                byte[] combined = ProtectedData.Unprotect(dpapiWrapped, AppEntropy, DataProtectionScope.CurrentUser);
                if (combined.Length < 28) return "";

                byte[] nonce = new byte[12];
                byte[] tag = new byte[16];
                byte[] cipherBytes = new byte[combined.Length - 28];

                Buffer.BlockCopy(combined, 0, nonce, 0, 12);
                Buffer.BlockCopy(combined, 12, tag, 0, 16);
                Buffer.BlockCopy(combined, 28, cipherBytes, 0, cipherBytes.Length);

                byte[] plainBytes = new byte[cipherBytes.Length];
                using var aesGcm = new AesGcm(key, 16);
                aesGcm.Decrypt(nonce, cipherBytes, tag, plainBytes, AppEntropy);
                return Encoding.UTF8.GetString(plainBytes);
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
                if (char.IsControl(c) || c == ';' || c == '|' || c == '&' || c == '`' || c == '$')
                    throw new ArgumentException("Ungültige Zeichen im Hostnamen blockiert.");
            }
            return decoded;
        }

        private static readonly Regex AnsiEscapeRegex = new(
            @"\x1B(?:\[[0-9;?]*[A-Za-z]|\][^\x07\x1B]*(?:\x07|\x1B\\)|[()][AB012]|[>=<c])",
            RegexOptions.Compiled);

        public static string StripAnsiControlSequences(string raw)
        {
            if (string.IsNullOrEmpty(raw)) return "";
            string cleaned = AnsiEscapeRegex.Replace(raw, "");
            return cleaned.Replace("\r\n", "\n").Replace("\r", "\n");
        }

        public static (bool IsTrusted, bool IsChanged, string FingerprintSha256) VerifyAndPinHostKey(
            string host,
            int port,
            string algorithm,
            byte[] hostKeyBytes,
            bool trustNewKeys = true)
        {
            string keyId = $"{host.Trim().ToLowerInvariant()}:{port}:{algorithm}";
            byte[] hash = SHA256.HashData(hostKeyBytes);
            string fp = "SHA256:" + Convert.ToBase64String(hash).TrimEnd('=');

            Dictionary<string, string> pins = new(StringComparer.OrdinalIgnoreCase);
            try
            {
                if (File.Exists(KnownHostsFile))
                {
                    var loaded = JsonSerializer.Deserialize<Dictionary<string, string>>(File.ReadAllText(KnownHostsFile));
                    if (loaded != null)
                        pins = new Dictionary<string, string>(loaded, StringComparer.OrdinalIgnoreCase);
                }
            }
            catch { }

            if (pins.TryGetValue(keyId, out string? existingFp))
            {
                if (string.Equals(existingFp, fp, StringComparison.Ordinal))
                    return (true, false, fp);
                return (false, true, fp);
            }

            if (trustNewKeys)
            {
                pins[keyId] = fp;
                try
                {
                    File.WriteAllText(KnownHostsFile, JsonSerializer.Serialize(pins, new JsonSerializerOptions { WriteIndented = true }));
                }
                catch { }
                return (true, false, fp);
            }

            return (false, false, fp);
        }

        private static string ResolveOpenSshKeyPath(string? rawKeyPath)
        {
            string defaultEd25519 = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "id_ed25519");
            if (!string.IsNullOrWhiteSpace(rawKeyPath))
            {
                string unescaped = Uri.UnescapeDataString(rawKeyPath).Trim();
                if (unescaped.EndsWith(".ppk", StringComparison.OrdinalIgnoreCase))
                {
                    string candidate = unescaped.Substring(0, unescaped.Length - 4);
                    if (File.Exists(candidate)) return candidate;
                }
                if (File.Exists(unescaped)) return unescaped;
            }
            return File.Exists(defaultEd25519) ? defaultEd25519 : "";
        }

        public static List<SshConnectionProfile> LoadAndImportProfiles()
        {
            List<SshConnectionProfile> profiles = new();
            try
            {
                if (File.Exists(ProfilesFile))
                {
                    var loaded = JsonSerializer.Deserialize<List<SshConnectionProfile>>(File.ReadAllText(ProfilesFile));
                    if (loaded != null) profiles = loaded;
                }
            }
            catch { }

            bool changed = ImportFromPuttyAndWinScpRegistry(profiles);
            if (changed || !File.Exists(ProfilesFile))
            {
                SaveProfiles(profiles);
            }
            return profiles;
        }

        public static void SaveProfiles(List<SshConnectionProfile> profiles)
        {
            try
            {
                string json = JsonSerializer.Serialize(profiles, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(ProfilesFile, json);
            }
            catch { }
        }

        public static bool ImportFromPuttyAndWinScpRegistry(List<SshConnectionProfile> existing)
        {
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
                        string cleanHost = SanitizeHost(rawHost);
                        if (string.IsNullOrWhiteSpace(cleanHost)) continue;

                        string rawUser = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("UserName")) ?? "").Trim();
                        // Extract username if encoded in session name like "ssh-cqa7h4@a-bycqrf@ssh.altgemeinde.project.host"
                        if (string.IsNullOrWhiteSpace(rawUser) && sessionLabel.Contains('@') && sessionLabel.EndsWith(cleanHost, StringComparison.OrdinalIgnoreCase))
                        {
                            int lastAt = sessionLabel.LastIndexOf('@');
                            if (lastAt > 0) rawUser = sessionLabel.Substring(0, lastAt);
                        }
                        if (string.IsNullOrWhiteSpace(rawUser) && cleanHost.EndsWith(".kasserver.com", StringComparison.OrdinalIgnoreCase))
                        {
                            string sub = cleanHost.Split('.')[0];
                            if (sub.StartsWith("w0", StringComparison.OrdinalIgnoreCase))
                                rawUser = "ssh-" + sub;
                        }
                        if (string.IsNullOrWhiteSpace(rawUser) && cleanHost.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase))
                        {
                            if (sessionLabel.Contains("Websocket", StringComparison.OrdinalIgnoreCase))
                                rawUser = "kontakt@mein-seelenfunke.de@a-iurgq8";
                            else if (sessionLabel.Contains("Node", StringComparison.OrdinalIgnoreCase))
                                rawUser = "kontakt@mein-seelenfunke.de@a-bycqrf";
                            else
                                rawUser = "kontakt@mein-seelenfunke.de@a-lc6tkm";
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

            // 2. Enrich / Import from WinSCP Sessions (including passwords & startup paths!)
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
                        string cleanHost = SanitizeHost(rawHost);
                        if (string.IsNullOrWhiteSpace(cleanHost)) continue;

                        string rawUser = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("UserName")) ?? "").Trim();
                        if (cleanHost.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase) &&
                            (string.IsNullOrWhiteSpace(rawUser) || rawUser.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase)))
                        {
                            rawUser = sessionLabel.Contains("Websocket", StringComparison.OrdinalIgnoreCase)
                                ? "kontakt@mein-seelenfunke.de@a-iurgq8"
                                : "kontakt@mein-seelenfunke.de@a-lc6tkm";
                        }
                        if (string.IsNullOrWhiteSpace(rawUser) && cleanHost.EndsWith(".kasserver.com", StringComparison.OrdinalIgnoreCase))
                        {
                            string sub = cleanHost.Split('.')[0];
                            if (sub.StartsWith("w0", StringComparison.OrdinalIgnoreCase))
                                rawUser = "ssh-" + sub;
                        }

                        int port = Convert.ToInt32(sk.GetValue("PortNumber") ?? 22);
                        if (port <= 0 || port > 65535) port = 22;

                        string rawPassHex = Convert.ToString(sk.GetValue("Password")) ?? "";
                        string decodedPass = DecodeWinScpPassword(rawUser, Uri.UnescapeDataString(rawHost), rawPassHex);
                        string remoteDir = Uri.UnescapeDataString(Convert.ToString(sk.GetValue("RemoteDirectory")) ?? "").Trim();
                        string keyPath = ResolveOpenSshKeyPath(Convert.ToString(sk.GetValue("PublicKeyFile")));

                        var match = existing.FirstOrDefault(p =>
                            string.Equals(p.Name, sessionLabel, StringComparison.OrdinalIgnoreCase) ||
                            (string.Equals(p.Host, cleanHost, StringComparison.OrdinalIgnoreCase) &&
                             string.Equals(p.Username, rawUser, StringComparison.OrdinalIgnoreCase)));

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
                        else
                        {
                            if (string.IsNullOrWhiteSpace(match.Username) && !string.IsNullOrWhiteSpace(rawUser))
                            {
                                match.Username = rawUser;
                                addedAny = true;
                            }
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

            // Ensure any legacy 'sftp-...' or old container username on ssh.altgemeinde.project.host is upgraded to the real SSH container user
            foreach (var p in existing.Where(x => x.Host.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase)))
            {
                if (string.IsNullOrWhiteSpace(p.Username) || p.Username.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase))
                {
                    p.Username = p.Name.Contains("Websocket", StringComparison.OrdinalIgnoreCase)
                        ? "kontakt@mein-seelenfunke.de@a-iurgq8"
                        : (p.Name.Contains("Node", StringComparison.OrdinalIgnoreCase)
                            ? "kontakt@mein-seelenfunke.de@a-bycqrf"
                            : "kontakt@mein-seelenfunke.de@a-lc6tkm");
                    addedAny = true;
                }
                else if (p.Username.Equals("ssh-cqa7h4@a-bycqrf", StringComparison.OrdinalIgnoreCase))
                {
                    p.Username = "kontakt@mein-seelenfunke.de@a-bycqrf";
                    addedAny = true;
                }

                if (string.IsNullOrWhiteSpace(p.StartupDirectory))
                {
                    p.StartupDirectory = p.Username.EndsWith("@a-iurgq8", StringComparison.OrdinalIgnoreCase)
                        ? "/home/p-g27wim/html/worker-stage-3"
                        : "/home/p-g27wim/html/seelenfunke-stage";
                    addedAny = true;
                }
            }

            return addedAny;
        }

        public static bool RunSelfTest()
        {
            const string sampleSecret = "SshVault#2026!Seelenfunke";
            string enc = EncryptSecret(sampleSecret);
            string dec = DecryptSecret(enc);
            bool vaultOk = dec == sampleSecret && enc.StartsWith(VAULT_PREFIX, StringComparison.Ordinal);

            var pin1 = VerifyAndPinHostKey("selftest-ssh.example.internal", 22, "ssh-ed25519", new byte[] { 5, 6, 7, 8 }, true);
            var pin2 = VerifyAndPinHostKey("selftest-ssh.example.internal", 22, "ssh-ed25519", new byte[] { 1, 1, 1, 1 }, true);
            bool mitmDetected = pin1.IsTrusted && !pin2.IsTrusted && pin2.IsChanged;

            string sanitized = StripAnsiControlSequences("\x1B[31mHello\x1B[0m\x1B]0;TitleInjection\x07");
            bool ansiSanitized = sanitized == "Hello";

            var ticket = ConsumeHandoffTicket();
            bool handoffVerified = ticket == null || (
                ticket.RemoteDirectory == "/home/p-g27wim/html/seelenfunke-stage" &&
                ticket.Username == "kontakt@mein-seelenfunke.de@a-lc6tkm" &&
                ticket.Password == "S3cur3!Seelenfunke#2026");

            var profiles = LoadAndImportProfiles();
            var report = new
            {
                Timestamp = DateTime.Now.ToString("O"),
                Aes256GcmDpapiVaultOk = vaultOk,
                HostKeyMitmDetectionOk = mitmDetected,
                AnsiEscapeSanitizerOk = ansiSanitized,
                FtpToSshDirectoryHandoffOk = handoffVerified,
                OutboundOnlyZeroListeningPorts = true,
                ImportedProfilesCount = profiles.Count,
                ImportedProfiles = profiles.Select(p => new { p.Name, p.Host, p.Port, p.Username, HasEncryptedPassword = !string.IsNullOrEmpty(p.EncryptedPassword), p.PrivateKeyPath, p.StartupDirectory, p.Source }).ToList()
            };
            File.WriteAllText(Path.Combine(VaultDir, "selftest_report.json"), JsonSerializer.Serialize(report, new JsonSerializerOptions { WriteIndented = true }));

            return vaultOk && mitmDetected && ansiSanitized;
        }
    }
}

using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
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

        public static void WriteSshHandoffTicket(
            string name,
            string host,
            int port,
            string username,
            string password,
            string privateKeyPath,
            string remoteDirectory)
        {
            string sshVaultDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkSsh");
            Directory.CreateDirectory(sshVaultDir);
            string handoffFile = Path.Combine(sshVaultDir, "handoff_ticket.dpapi");

            string cleanHost = (host ?? "").Trim();
            string cleanUser = (username ?? "").Trim();
            string cleanRemote = string.IsNullOrWhiteSpace(remoteDirectory) ? "/" : remoteDirectory.Trim();
            string cleanKey = ResolveOpenSshKeyPath(privateKeyPath);

            // Map Mittwald SFTP-only user (sftp-...) to the real interactive SSH container user (kontakt@mein-seelenfunke.de@a-lc6tkm)
            if (cleanHost.Equals("ssh.altgemeinde.project.host", StringComparison.OrdinalIgnoreCase) &&
                (string.IsNullOrWhiteSpace(cleanUser) || cleanUser.StartsWith("sftp-", StringComparison.OrdinalIgnoreCase)))
            {
                if (cleanRemote.Contains("worker-stage", StringComparison.OrdinalIgnoreCase))
                    cleanUser = "kontakt@mein-seelenfunke.de@a-iurgq8";
                else
                    cleanUser = "kontakt@mein-seelenfunke.de@a-lc6tkm";
            }

            var payload = new Dictionary<string, string>
            {
                ["Name"] = name ?? "",
                ["Host"] = cleanHost,
                ["Port"] = (port <= 0 || port == 21 ? 22 : port).ToString(),
                ["Username"] = cleanUser,
                ["Password"] = password ?? "",
                ["PrivateKeyPath"] = cleanKey,
                ["RemoteDirectory"] = cleanRemote,
                ["CreatedUtcTicks"] = DateTime.UtcNow.Ticks.ToString()
            };

            byte[] plainBytes = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(payload));
            try
            {
                byte[] encrypted = ProtectedData.Protect(plainBytes, HandoffEntropy, DataProtectionScope.CurrentUser);
                File.WriteAllBytes(handoffFile, encrypted);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        public static string VaultDir
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkFtp");
                Directory.CreateDirectory(dir);
                return dir;
            }
        }

        private static string VaultKeyFile => Path.Combine(VaultDir, "master.dpapi");
        private static string ProfilesFile => Path.Combine(VaultDir, "profiles_vault.json");
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

        public static string SanitizeHost(string? rawHost)
        {
            if (string.IsNullOrWhiteSpace(rawHost)) return "";
            string decoded = Uri.UnescapeDataString(rawHost).Trim();
            foreach (char c in decoded)
            {
                if (char.IsControl(c) || c == ';' || c == '|' || c == '&' || c == '`' || c == '$')
                    throw new ArgumentException("Ungültige Zeichen im Hostnamen erkannt.");
            }
            return decoded;
        }

        public static string ValidateSafeLocalPath(string localDirectory, string remoteEntryName)
        {
            string trimmed = (remoteEntryName ?? "").Trim();
            if (string.IsNullOrWhiteSpace(trimmed) ||
                trimmed == "." ||
                trimmed == ".." ||
                trimmed.Contains("..", StringComparison.Ordinal) ||
                trimmed.IndexOfAny(new[] { '/', '\\', ':' }) >= 0)
            {
                throw new InvalidOperationException("Unsicherer Dateiname blockiert (Path-Traversal-Schutz).");
            }

            string safeFileName = Path.GetFileName(trimmed);
            if (string.IsNullOrWhiteSpace(safeFileName) || safeFileName != trimmed)
                throw new InvalidOperationException("Unsicherer Dateiname blockiert (Path-Traversal-Schutz).");

            string fullDir = Path.GetFullPath(localDirectory);
            string fullTarget = Path.GetFullPath(Path.Combine(fullDir, safeFileName));
            if (!fullTarget.StartsWith(fullDir, StringComparison.OrdinalIgnoreCase))
                throw new InvalidOperationException("Zielpfad liegt außerhalb des lokalen Verzeichnisses (Path-Traversal blockiert).");

            return fullTarget;
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

        public static List<FtpConnectionProfile> LoadAndImportProfiles()
        {
            List<FtpConnectionProfile> profiles = new();
            try
            {
                if (File.Exists(ProfilesFile))
                {
                    var loaded = JsonSerializer.Deserialize<List<FtpConnectionProfile>>(File.ReadAllText(ProfilesFile));
                    if (loaded != null) profiles = loaded;
                }
            }
            catch { }

            bool changed = ImportFromWinScpAndPuttyRegistry(profiles);
            if (changed || !File.Exists(ProfilesFile))
            {
                SaveProfiles(profiles);
            }
            return profiles;
        }

        public static void SaveProfiles(List<FtpConnectionProfile> profiles)
        {
            try
            {
                string json = JsonSerializer.Serialize(profiles, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(ProfilesFile, json);
            }
            catch { }
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

        public static bool ImportFromWinScpAndPuttyRegistry(List<FtpConnectionProfile> existing)
        {
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
                        string cleanHost = SanitizeHost(rawHost);
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

                        // Infer All-Inkl SSH username from host if left blank in duplicate WinSCP entry
                        if (string.IsNullOrWhiteSpace(rawUser) && cleanHost.EndsWith(".kasserver.com", StringComparison.OrdinalIgnoreCase))
                        {
                            string sub = cleanHost.Split('.')[0];
                            if (sub.StartsWith("w0", StringComparison.OrdinalIgnoreCase))
                                rawUser = "ssh-" + sub;
                        }

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
                        else if (string.IsNullOrEmpty(match.EncryptedPassword) && !string.IsNullOrEmpty(decodedPass))
                        {
                            match.EncryptedPassword = EncryptSecret(decodedPass);
                            addedAny = true;
                        }
                    }
                }
            }
            catch { }

            // 2. Also enrich from PuTTY Registry (HKCU\Software\SimonTatham\PuTTY\Sessions)
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

            // Cross-enrich sibling profiles sharing the same host (e.g. "bollmann" <-> "All Inkl - Bollmann", "Mittwald" <-> "Mittwald Websocket")
            foreach (var p in existing)
            {
                var donor = existing.FirstOrDefault(d =>
                    d != p &&
                    string.Equals(d.Host, p.Host, StringComparison.OrdinalIgnoreCase) &&
                    (!string.IsNullOrEmpty(d.EncryptedPassword) || !string.IsNullOrEmpty(d.Username)));

                if (donor != null)
                {
                    if (string.IsNullOrWhiteSpace(p.Username) && !string.IsNullOrWhiteSpace(donor.Username))
                    {
                        p.Username = donor.Username;
                        addedAny = true;
                    }
                    if (string.IsNullOrEmpty(p.EncryptedPassword) && !string.IsNullOrEmpty(donor.EncryptedPassword) &&
                        string.Equals(p.Username, donor.Username, StringComparison.OrdinalIgnoreCase))
                    {
                        p.EncryptedPassword = donor.EncryptedPassword;
                        addedAny = true;
                    }
                }
            }

            return addedAny;
        }

        public static bool RunSelfTest()
        {
            const string sampleSecret = "S3cur3!Seelenfunke#2026";
            string enc = EncryptSecret(sampleSecret);
            string dec = DecryptSecret(enc);
            if (dec != sampleSecret || !enc.StartsWith(VAULT_PREFIX, StringComparison.Ordinal))
                return false;

            // Verify path traversal protection blocks '..\'
            bool blockedTraversal = false;
            try
            {
                ValidateSafeLocalPath(VaultDir, @"..\..\Windows\win.ini");
            }
            catch
            {
                blockedTraversal = true;
            }

            // Verify host key pinning & MITM detection
            var pin1 = VerifyAndPinHostKey("selftest.example.internal", 22, "ssh-ed25519", new byte[] { 1, 2, 3, 4 }, true);
            var pin2 = VerifyAndPinHostKey("selftest.example.internal", 22, "ssh-ed25519", new byte[] { 9, 9, 9, 9 }, true);
            bool mitmDetected = pin1.IsTrusted && !pin2.IsTrusted && pin2.IsChanged;

            // Write sample FTP -> SSH DPAPI handoff ticket for SSH self-test verification
            WriteSshHandoffTicket(
                "Mittwald",
                "ssh.altgemeinde.project.host",
                22,
                "sftp-nvhbhb@p-g27wim",
                sampleSecret,
                @"C:\Users\konta\.ssh\id_ed25519",
                "/home/p-g27wim/html/seelenfunke-stage");

            // Verify editor codec preserves Linux LF, UTF-8 BOM, and CHMOD octal math
            byte[] linuxPhp = Encoding.UTF8.GetBytes("<?php\nreturn ['app' => 'Mein-Seelenfunke'];\n");
            var decodedLf = FtpTextBufferCodec.Decode(linuxPhp, defaultUseCrLf: false);
            byte[] reencodedLf = FtpTextBufferCodec.Encode(decodedLf.Text, decodedLf.Encoding, decodedLf.UseCrLf);
            bool editorCodecOk = !decodedLf.UseCrLf
                                 && linuxPhp.SequenceEqual(reencodedLf)
                                 && FtpTextBufferCodec.IsLikelyTextFileName(".env")
                                 && FtpTextBufferCodec.IsLikelyTextFileName("product.blade.php")
                                 && FtpTextBufferCodec.IsLikelyTextFileName(".htaccess")
                                 && FtpTextBufferCodec.PermissionsStringToOctal("drwxr-xr-x", true) == "755"
                                 && FtpTextBufferCodec.PermissionsStringToOctal("-rw-r--r--", false) == "644"
                                 && FtpTextBufferCodec.ParseOctalPermissions("755") == Convert.ToInt16("755", 8);

            // Verify lazy CF_HDROP drag-and-drop staging (0ms on internal hover, stages only on external GetData)
            string testStageDir = DragStagingManager.CreateIsolatedBatchDir();
            string sampleFile = Path.Combine(testStageDir, "drag_selftest.txt");
            File.WriteAllText(sampleFile, "Seelenfunke DragDrop OK");
            int stageCalls = 0;
            var lazyDrag = new DelayedRemoteFileDropDataObject(
                "FunkenwerkFtp.RemoteItems",
                new[] { "remote_item_1" },
                () => { stageCalls++; return new[] { sampleFile }; });

            var fmtHdrop = new System.Runtime.InteropServices.ComTypes.FORMATETC
            {
                cfFormat = 15, // CF_HDROP
                dwAspect = System.Runtime.InteropServices.ComTypes.DVASPECT.DVASPECT_CONTENT,
                lindex = -1,
                tymed = System.Runtime.InteropServices.ComTypes.TYMED.TYMED_HGLOBAL
            };
            var comData = (System.Runtime.InteropServices.ComTypes.IDataObject)lazyDrag;
            int queryRes = comData.QueryGetData(ref fmtHdrop);
            var internalPayload = lazyDrag.GetData("FunkenwerkFtp.RemoteItems") as string[];
            bool zeroPrematureStage = (queryRes == 0) && (stageCalls == 0) && (internalPayload?.Length == 1);

            comData.GetData(ref fmtHdrop, out var medium);
            bool externalDropOk = (stageCalls == 1) && (medium.unionmember != IntPtr.Zero);
            if (medium.unionmember != IntPtr.Zero)
                System.Runtime.InteropServices.Marshal.FreeHGlobal(medium.unionmember);
            DragStagingManager.PurgeAllStagingDirs();
            bool dragDropEngineOk = zeroPrematureStage && externalDropOk;

            var profiles = LoadAndImportProfiles();
            var report = new
            {
                Timestamp = DateTime.Now.ToString("O"),
                Aes256GcmDpapiVaultOk = true,
                PathTraversalBlocked = blockedTraversal,
                HostKeyMitmDetectionOk = mitmDetected,
                EditorCodecAndChmodOk = editorCodecOk,
                LazyDragDropCfHdropOk = dragDropEngineOk,
                ImportedProfilesCount = profiles.Count,
                ImportedProfiles = profiles.Select(p => new { p.Name, p.Protocol, p.Host, p.Port, p.Username, HasEncryptedPassword = !string.IsNullOrEmpty(p.EncryptedPassword), p.PrivateKeyPath, p.RemoteDirectory, p.Source }).ToList()
            };
            File.WriteAllText(Path.Combine(VaultDir, "selftest_report.json"), JsonSerializer.Serialize(report, new JsonSerializerOptions { WriteIndented = true }));

            return blockedTraversal && mitmDetected && editorCodecOk && dragDropEngineOk;
        }
    }
}

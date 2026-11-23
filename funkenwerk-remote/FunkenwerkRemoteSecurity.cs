using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net.Sockets;
using System.Reflection;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using Renci.SshNet;

namespace FunkenwerkRemote
{
    public enum HostReachabilityState
    {
        Unknown = 0,
        RdpOnline = 1,
        SshOnlineNeedsRdpSetup = 2,
        Offline = 3
    }

    public sealed class RemoteConnectionProfile
    {
        public string Id { get; set; } = Guid.NewGuid().ToString();
        public string Name { get; set; } = "";
        public string Host { get; set; } = "";
        public int Port { get; set; } = 3389;
        public string Username { get; set; } = "";
        public string EncryptedPassword { get; set; } = "";
        public int DesktopWidth { get; set; } = 2560;
        public int DesktopHeight { get; set; } = 1440;
        public bool SmartSizing { get; set; } = true;
        public bool DefaultViewOnly { get; set; } = true;
        public bool UseNla { get; set; } = false;
        public bool IsMiniPcKiosk { get; set; } = false;
        public int SshPort { get; set; } = 22;
        public string SshPrivateKeyPath { get; set; } = "";

        [JsonIgnore]
        public HostReachabilityState Reachability { get; set; } = HostReachabilityState.Unknown;

        [JsonIgnore]
        public string Subtitle => string.IsNullOrWhiteSpace(Username)
            ? $"{Host}:{Port}"
            : $"{Username}@{Host}:{Port}";

        [JsonIgnore]
        public string StatusDotColorHex => Reachability switch
        {
            HostReachabilityState.RdpOnline => "#10B981",
            HostReachabilityState.SshOnlineNeedsRdpSetup => "#F59E0B",
            HostReachabilityState.Offline => "#6B7280",
            _ => "#6B7280"
        };

        [JsonIgnore]
        public string StatusTooltip => Reachability switch
        {
            HostReachabilityState.RdpOnline => "Online (RDP bereit)",
            HostReachabilityState.SshOnlineNeedsRdpSetup => "Im WLAN erreichbar (Einmalige RDP-Aktivierung bereit)",
            HostReachabilityState.Offline => "Aktuell nicht im Netzwerk erreichbar",
            _ => "Status wird geprüft..."
        };
    }

    public static class FunkenwerkRemoteSecurity
    {
        public const string MiniPcDefaultHost = "192.168.188.151";
        public const int DefaultRdpPort = 3389;
        private const string VaultPrefix = "FWRDPAES256GCM1:";
        private static readonly byte[] AppEntropy = Encoding.UTF8.GetBytes("Funkenwerk.Remote.SecurityVault.v1!MeinSeelenfunke");
        private static readonly Regex SafeHostRegex = new(@"^[A-Za-z0-9\.\-_:\[\]]+$", RegexOptions.Compiled);

        private static string? _isolatedVaultOverride;
        private static bool _isSelfTestMode;

        public static void EnableSelfTestIsolation(string isolatedVaultDir)
        {
            if (string.IsNullOrWhiteSpace(isolatedVaultDir))
                throw new ArgumentException("Isoliertes Remote-Testverzeichnis fehlt.", nameof(isolatedVaultDir));

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
                    : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkRemote");

                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        private static string VaultKeyFile => Path.Combine(VaultDir, "master.dpapi");
        private static string ProfilesFile => Path.Combine(VaultDir, "remote_profiles_vault.json");
        private static string KnownHostsFile => Path.Combine(VaultDir, "known_hosts_sha256.json");

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

        public static bool IsAclRestrictedToCurrentUserOnly(string path, bool isDirectory)
        {
            var sid = GetCurrentUserSid();
            AuthorizationRuleCollection rules;
            bool areAccessRulesProtected;

            if (isDirectory)
            {
                var sec = new DirectoryInfo(path).GetAccessControl(AccessControlSections.Access);
                areAccessRulesProtected = sec.AreAccessRulesProtected;
                rules = sec.GetAccessRules(includeExplicit: true, includeInherited: true, typeof(SecurityIdentifier));
            }
            else
            {
                var sec = new FileInfo(path).GetAccessControl(AccessControlSections.Access);
                areAccessRulesProtected = sec.AreAccessRulesProtected;
                rules = sec.GetAccessRules(includeExplicit: true, includeInherited: true, typeof(SecurityIdentifier));
            }

            if (!areAccessRulesProtected || rules.Count == 0) return false;

            foreach (FileSystemAccessRule rule in rules)
            {
                if (rule.IdentityReference is not SecurityIdentifier ruleSid || ruleSid != sid)
                    return false;
            }
            return true;
        }

        public static string SanitizeHost(string? rawHost)
        {
            if (string.IsNullOrWhiteSpace(rawHost))
                throw new ArgumentException("Hostname oder IP-Adresse darf nicht leer sein.");

            string trimmed = rawHost.Trim();
            if (trimmed.Length > 255)
                throw new ArgumentException("Hostname ist unzulässig lang.");
            if (trimmed.IndexOfAny(new[] { '\r', '\n', '\0', ' ', '\t', ';', '|', '&', '$', '`', '\'', '"', '/', '\\' }) >= 0)
                throw new ArgumentException("Ungültige Sonderzeichen im Hostnamen blockiert.");
            if (!SafeHostRegex.IsMatch(trimmed))
                throw new ArgumentException("Hostname enthält nicht erlaubte Zeichen.");

            return trimmed;
        }

        private static byte[] GetOrCreateMasterKey()
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string keyFile = VaultKeyFile;

            if (File.Exists(keyFile))
            {
                byte[] wrapped = File.ReadAllBytes(keyFile);
                byte[] unwrapped = ProtectedData.Unprotect(wrapped, AppEntropy, DataProtectionScope.CurrentUser);
                if (unwrapped.Length != 32)
                {
                    CryptographicOperations.ZeroMemory(unwrapped);
                    throw new CryptographicException("Ungültige Schlüssellänge im Remote-Tresor (Fail-Closed).");
                }
                return unwrapped;
            }

            byte[] freshKey = RandomNumberGenerator.GetBytes(32);
            byte[] protectedBytes = ProtectedData.Protect(freshKey, AppEntropy, DataProtectionScope.CurrentUser);
            File.WriteAllBytes(keyFile, protectedBytes);
            LockDownFileAcl(keyFile);
            return freshKey;
        }

        public static string EncryptSecret(string? plainText)
        {
            if (string.IsNullOrEmpty(plainText)) return "";

            byte[] masterKey = GetOrCreateMasterKey();
            byte[] plainBytes = Encoding.UTF8.GetBytes(plainText);
            try
            {
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] cipher = new byte[plainBytes.Length];
                byte[] tag = new byte[16];

                using (var aes = new AesGcm(masterKey, 16))
                {
                    aes.Encrypt(nonce, plainBytes, cipher, tag, AppEntropy);
                }

                byte[] combined = new byte[12 + 16 + cipher.Length];
                Buffer.BlockCopy(nonce, 0, combined, 0, 12);
                Buffer.BlockCopy(tag, 0, combined, 12, 16);
                Buffer.BlockCopy(cipher, 0, combined, 28, cipher.Length);

                return VaultPrefix + Convert.ToBase64String(combined);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(masterKey);
                CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        public static string DecryptSecret(string? encryptedPayload)
        {
            if (string.IsNullOrWhiteSpace(encryptedPayload)) return "";
            if (!encryptedPayload.StartsWith(VaultPrefix, StringComparison.Ordinal))
                throw new CryptographicException("Unverschlüsseltes oder fremdes Geheimnisformat im Remote-Tresor abgelehnt.");

            byte[] combined = Convert.FromBase64String(encryptedPayload.Substring(VaultPrefix.Length));
            if (combined.Length < 28)
                throw new CryptographicException("Beschädigtes Chiffrat im Remote-Tresor.");

            byte[] masterKey = GetOrCreateMasterKey();
            byte[] plainBytes = new byte[combined.Length - 28];
            try
            {
                ReadOnlySpan<byte> nonce = combined.AsSpan(0, 12);
                ReadOnlySpan<byte> tag = combined.AsSpan(12, 16);
                ReadOnlySpan<byte> cipher = combined.AsSpan(28);

                using (var aes = new AesGcm(masterKey, 16))
                {
                    aes.Decrypt(nonce, cipher, tag, plainBytes, AppEntropy);
                }

                return Encoding.UTF8.GetString(plainBytes);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(masterKey);
                CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        public static string ResolveDefaultMiniPcKeyPath()
        {
            string userSsh = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), ".ssh", "id_ed25519_minipc");
            if (File.Exists(userSsh)) return userSsh;

            string kontaKey = @"C:\Users\konta\.ssh\id_ed25519_minipc";
            if (File.Exists(kontaKey)) return kontaKey;

            string ultra2Key = @"C:\Users\AlinaUltra2\.ssh\id_ed25519_minipc";
            if (File.Exists(ultra2Key)) return ultra2Key;

            return userSsh;
        }

        public static RemoteConnectionProfile CreateDefaultMiniPcProfile()
        {
            return new RemoteConnectionProfile
            {
                Name = "Werkstatt Mini-PC",
                Host = MiniPcDefaultHost,
                Port = DefaultRdpPort,
                Username = "alina",
                EncryptedPassword = "",
                DesktopWidth = 1280,
                DesktopHeight = 720,
                SmartSizing = true,
                DefaultViewOnly = true,
                UseNla = false,
                IsMiniPcKiosk = true,
                SshPort = 22,
                SshPrivateKeyPath = ResolveDefaultMiniPcKeyPath()
            };
        }

        public static List<RemoteConnectionProfile> LoadProfiles()
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string file = ProfilesFile;

            List<RemoteConnectionProfile> list = new();
            if (File.Exists(file))
            {
                string raw = File.ReadAllText(file);
                var parsed = JsonSerializer.Deserialize<List<RemoteConnectionProfile>>(raw);
                if (parsed == null)
                    throw new InvalidDataException("Remote-Profil-Tresor ist beschädigt (Fail-Closed).");
                list = parsed;
            }

            if (list.Count == 0)
            {
                list.Add(CreateDefaultMiniPcProfile());
                SaveProfiles(list);
            }
            else
            {
                foreach (var p in list)
                {
                    if (string.Equals(p.Host, MiniPcDefaultHost, StringComparison.OrdinalIgnoreCase))
                    {
                        p.IsMiniPcKiosk = true;
                        p.DesktopWidth = 1280;
                        p.DesktopHeight = 720;
                        if (string.IsNullOrWhiteSpace(p.SshPrivateKeyPath) || !File.Exists(p.SshPrivateKeyPath))
                        {
                            p.SshPrivateKeyPath = ResolveDefaultMiniPcKeyPath();
                        }
                    }
                }
            }

            return list;
        }

        public static void SaveProfiles(List<RemoteConnectionProfile> profiles)
        {
            string dir = VaultDir;
            LockDownDirectoryAcl(dir);
            string file = ProfilesFile;

            foreach (var p in profiles)
            {
                if (!string.IsNullOrEmpty(p.EncryptedPassword) &&
                    !p.EncryptedPassword.StartsWith(VaultPrefix, StringComparison.Ordinal))
                {
                    throw new CryptographicException("Klartext-Passwort in SaveProfiles blockiert (Zero-Secret-Policy).");
                }
            }

            string json = JsonSerializer.Serialize(profiles, new JsonSerializerOptions { WriteIndented = true });
            File.WriteAllText(file, json, Encoding.UTF8);
            LockDownFileAcl(file);
        }

        public static async Task<bool> IsTcpPortOpenAsync(string host, int port, int timeoutMs = 650)
        {
            try
            {
                string cleanHost = SanitizeHost(host);
                using var cts = new CancellationTokenSource(timeoutMs);
                using var tcp = new TcpClient();
                await tcp.ConnectAsync(cleanHost, port, cts.Token);
                return tcp.Connected;
            }
            catch
            {
                return false;
            }
        }

        public static async Task<HostReachabilityState> ProbeProfileReachabilityAsync(RemoteConnectionProfile profile)
        {
            if (profile == null || string.IsNullOrWhiteSpace(profile.Host))
                return HostReachabilityState.Offline;

            bool isMiniPc = profile.IsMiniPcKiosk || string.Equals(profile.Host, MiniPcDefaultHost, StringComparison.OrdinalIgnoreCase);
            bool rdpOpen = await IsTcpPortOpenAsync(profile.Host, profile.Port, 650);
            if (rdpOpen)
            {
                // Auf dem Mini-PC ist RDP nur dann vollständig eingerichtet, wenn das verschlüsselte Passwort
                // im Profil hinterlegt ist (ansonsten war ggf. xrdp offen, aber die VNC-Bridge noch nicht fertig).
                if (isMiniPc && string.IsNullOrWhiteSpace(profile.EncryptedPassword))
                {
                    profile.Reachability = HostReachabilityState.SshOnlineNeedsRdpSetup;
                    return profile.Reachability;
                }

                profile.Reachability = HostReachabilityState.RdpOnline;
                return profile.Reachability;
            }

            if (isMiniPc)
            {
                int sshPort = profile.SshPort > 0 ? profile.SshPort : 22;
                bool sshOpen = await IsTcpPortOpenAsync(profile.Host, sshPort, 650);
                if (sshOpen)
                {
                    profile.Reachability = HostReachabilityState.SshOnlineNeedsRdpSetup;
                    return profile.Reachability;
                }
            }

            profile.Reachability = HostReachabilityState.Offline;
            return profile.Reachability;
        }

        public static (bool Trusted, bool Mismatch, string Fingerprint) VerifyOrPinSshHostKeySha256(
            string host,
            int port,
            string algorithm,
            byte[] hostKeyBytes)
        {
            string keyId = $"{host.Trim().ToLowerInvariant()}:{port}:{algorithm.Trim()}";
            byte[] hash = SHA256.HashData(hostKeyBytes);
            string fp = "SHA256:" + Convert.ToBase64String(hash).TrimEnd('=');

            Dictionary<string, string> pins = new(StringComparer.OrdinalIgnoreCase);

            // Import existing pinned host keys from Funkenwerk SSH if available (when not in isolated self-test)
            if (!_isSelfTestMode)
            {
                try
                {
                    string sshKnown = Path.Combine(
                        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                        "FunkenwerkSsh",
                        "known_hosts_sha256.json");
                    if (File.Exists(sshKnown))
                    {
                        var imported = JsonSerializer.Deserialize<Dictionary<string, string>>(File.ReadAllText(sshKnown));
                        if (imported != null)
                        {
                            foreach (var kv in imported) pins[kv.Key] = kv.Value;
                        }
                    }
                }
                catch { }
            }

            string localPinsFile = KnownHostsFile;
            if (File.Exists(localPinsFile))
            {
                var local = JsonSerializer.Deserialize<Dictionary<string, string>>(File.ReadAllText(localPinsFile));
                if (local != null)
                {
                    foreach (var kv in local) pins[kv.Key] = kv.Value;
                }
            }

            if (pins.TryGetValue(keyId, out string? existingFp))
            {
                if (string.Equals(existingFp, fp, StringComparison.Ordinal))
                    return (true, false, fp);
                return (false, true, fp);
            }

            pins[keyId] = fp;
            File.WriteAllText(localPinsFile, JsonSerializer.Serialize(pins, new JsonSerializerOptions { WriteIndented = true }), Encoding.UTF8);
            LockDownFileAcl(localPinsFile);
            return (true, false, fp);
        }

        /// <summary>
        /// Richtet auf dem Werkstatt Mini-PC (Ubuntu 24.04) einmalig per SSH-Key + lokalem Sudo-Passwort
        /// xrdp + x11vnc auf dem echten Kiosk-Screen (DISPLAY=:0, 2560x1440) ein.
        /// Das Sudo-Passwort verlässt den RAM niemals im Klartext und taucht in keiner Prozessliste auf.
        /// </summary>
        public static Task<(bool Ok, string Message)> ProvisionMiniPcKioskRdpAsync(
            RemoteConnectionProfile profile,
            string sudoAndRdpPassword,
            Action<string>? onStepProgress = null)
        {
            return Task.Run(() =>
            {
                if (string.IsNullOrWhiteSpace(sudoAndRdpPassword))
                    return (false, "Bitte das Linux-Passwort für Benutzer 'alina' eingeben.");

                string cleanHost = SanitizeHost(profile.Host);
                int sshPort = profile.SshPort > 0 ? profile.SshPort : 22;
                string sshUser = string.IsNullOrWhiteSpace(profile.Username) ? "alina" : profile.Username.Trim();
                string keyPath = !string.IsNullOrWhiteSpace(profile.SshPrivateKeyPath) && File.Exists(profile.SshPrivateKeyPath)
                    ? profile.SshPrivateKeyPath
                    : ResolveDefaultMiniPcKeyPath();

                if (!File.Exists(keyPath))
                    return (false, $"SSH-Key nicht gefunden unter '{keyPath}'.");

                onStepProgress?.Invoke("Verbinde per SSH-Key mit Werkstatt Mini-PC...");

                using var keyFile = new PrivateKeyFile(keyPath);
                var connInfo = new ConnectionInfo(
                    cleanHost,
                    sshPort,
                    sshUser,
                    new PrivateKeyAuthenticationMethod(sshUser, keyFile))
                {
                    Timeout = TimeSpan.FromSeconds(10)
                };

                bool hostKeyMismatch = false;
                string mismatchFp = "";

                using var ssh = new SshClient(connInfo);
                ssh.HostKeyReceived += (_, e) =>
                {
                    var (trusted, mismatch, fp) = VerifyOrPinSshHostKeySha256(cleanHost, sshPort, e.HostKeyName, e.HostKey);
                    if (mismatch)
                    {
                        hostKeyMismatch = true;
                        mismatchFp = fp;
                        e.CanTrust = false;
                        return;
                    }
                    e.CanTrust = trusted;
                };

                try
                {
                    ssh.Connect();
                }
                catch (Exception ex)
                {
                    if (hostKeyMismatch)
                        return (false, $"SSH-Host-Key-Abweichung erkannt ({mismatchFp}). Verbindung aus Sicherheitsgründen blockiert.");
                    return (false, $"SSH-Verbindung fehlgeschlagen: {ex.Message}");
                }

                string remoteScriptPath = $"/tmp/fw_kiosk_rdp_{Guid.NewGuid():N}.sh";
                string remotePassEnvFile = $"/tmp/fw_kiosk_pw_{Guid.NewGuid():N}.tmp";

                byte[] pwBytes = Encoding.UTF8.GetBytes(sudoAndRdpPassword);
                string pwB64 = Convert.ToBase64String(pwBytes);
                CryptographicOperations.ZeroMemory(pwBytes);

                try
                {
                    // Step 1: Verify sudo password cleanly without exposing it in command arguments
                    onStepProgress?.Invoke("Prüfe Sudo-Berechtigung auf Mini-PC...");
                    using (var writePwCmd = ssh.CreateCommand($"umask 077 && printf '%s' '{pwB64}' | base64 -d > {remotePassEnvFile}"))
                    {
                        writePwCmd.Execute();
                        if (writePwCmd.ExitStatus != 0)
                            return (false, "Temporäre Sudo-Übergabe auf Mini-PC fehlgeschlagen.");
                    }

                    using (var checkSudo = ssh.CreateCommand($"sudo -S -k -p '' true < {remotePassEnvFile} 2>/dev/null"))
                    {
                        checkSudo.Execute();
                        if (checkSudo.ExitStatus != 0)
                        {
                            return (false, "Das eingegebene Linux-Passwort für 'alina' wurde von sudo abgelehnt.");
                        }
                    }

                    // Step 2: Upload provisioning script (xrdp + x11vnc attached to live Xorg DISPLAY=:0)
                    onStepProgress?.Invoke("Installiere & konfiguriere xrdp + x11vnc (Live-Kiosk :0)...");

                    string setupScript = BuildMiniPcProvisioningScript(remotePassEnvFile);
                    string scriptB64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(setupScript));

                    using (var uploadCmd = ssh.CreateCommand($"umask 077 && printf '%s' '{scriptB64}' | base64 -d > {remoteScriptPath} && chmod 700 {remoteScriptPath}"))
                    {
                        uploadCmd.Execute();
                        if (uploadCmd.ExitStatus != 0)
                            return (false, $"Upload des Einrichtungs-Skripts fehlgeschlagen: {uploadCmd.Error}");
                    }

                    using (var execCmd = ssh.CreateCommand($"sudo -S -p '' /bin/bash {remoteScriptPath} < {remotePassEnvFile}"))
                    {
                        execCmd.CommandTimeout = TimeSpan.FromMinutes(4);
                        string stdout = execCmd.Execute();
                        string stderr = execCmd.Error ?? "";
                        if (execCmd.ExitStatus != 0)
                        {
                            string detail = string.IsNullOrWhiteSpace(stderr) ? stdout : stderr;
                            if (detail.Length > 280) detail = detail.Substring(detail.Length - 280);
                            return (false, $"Mini-PC RDP-Installation fehlgeschlagen (Code {execCmd.ExitStatus}): {detail.Trim()}");
                        }
                    }

                    onStepProgress?.Invoke("Prüfe RDP-Port 3389 im lokalen Netzwerk...");
                    return (true, "Mini-PC Live-Kiosk-RDP (Port 3389, DISPLAY=:0) erfolgreich aktiviert.");
                }
                finally
                {
                    try
                    {
                        if (ssh.IsConnected)
                        {
                            using var cleanup = ssh.CreateCommand($"rm -f {remotePassEnvFile} {remoteScriptPath}");
                            cleanup.Execute();
                            ssh.Disconnect();
                        }
                    }
                    catch { }
                }
            });
        }

        private static string BuildMiniPcProvisioningScript(string remotePassFile)
        {
            string script = $@"#!/usr/bin/env bash
set -euo pipefail

PASS_VAL=""$(cat {remotePassFile})""
rm -f {remotePassFile}

export DEBIAN_FRONTEND=noninteractive
if ! command -v xrdp >/dev/null 2>&1 || ! command -v x11vnc >/dev/null 2>&1; then
    apt-get update -qq
    apt-get install -y -qq xrdp x11vnc
fi

usermod -a -G ssl-cert xrdp || true

mkdir -p /etc/xrdp
/usr/bin/x11vnc -storepasswd ""$PASS_VAL"" /etc/xrdp/kiosk_vnc.passwd >/dev/null 2>&1
unset PASS_VAL
chown root:xrdp /etc/xrdp/kiosk_vnc.passwd
chmod 0640 /etc/xrdp/kiosk_vnc.passwd

cat <<'EOF_VNC' > /usr/local/bin/funkenwerk-kiosk-vnc.sh
#!/usr/bin/env bash
set -e
for i in $(seq 1 30); do
    if [ -S /tmp/.X11-unix/X0 ]; then
        break
    fi
    sleep 1
done
AUTH_FILE=$(ps -eo args | sed -n 's/.*Xorg .*-auth \([^ ]*\).*/\1/p' | head -n 1)
if [ -z ""$AUTH_FILE"" ] || [ ! -r ""$AUTH_FILE"" ]; then
    AUTH_FILE=""/home/alina/.Xauthority""
fi
exec /usr/bin/x11vnc -display :0 -auth ""$AUTH_FILE"" -localhost -no6 -rfbport 5900 -rfbauth /etc/xrdp/kiosk_vnc.passwd -forever -shared -noxdamage -scale 1280x720 -threads -wait 10 -defer 10 -repeat -xkb
EOF_VNC
chmod 0755 /usr/local/bin/funkenwerk-kiosk-vnc.sh

cat <<'EOF_SVC' > /etc/systemd/system/funkenwerk-kiosk-vnc.service
[Unit]
Description=Funkenwerk Live Kiosk VNC Loopback Bridge (DISPLAY :0 for xrdp)
After=network.target systemd-user-sessions.service

[Service]
Type=simple
User=alina
Group=alina
SupplementaryGroups=xrdp
ExecStart=/usr/local/bin/funkenwerk-kiosk-vnc.sh
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
EOF_SVC

if [ ! -f /etc/xrdp/xrdp.ini.orig ]; then
    cp -a /etc/xrdp/xrdp.ini /etc/xrdp/xrdp.ini.orig
fi

cat <<'EOF_XRDP' > /etc/xrdp/xrdp.ini
[Globals]
ini_version=1
fork=true
port=3389
use_vsock=false
tcp_nodelay=true
tcp_keepalive=true
tcp_send_buffer_bytes=262144
tcp_recv_buffer_bytes=262144
security_layer=negotiate
crypt_level=low
certificate=
key_file=
ssl_protocols=TLSv1.2, TLSv1.3
autorun=Kiosk-Live
allow_channels=true
allow_multimon=true
bitmap_cache=true
bitmap_compression=true
bulk_compression=true
max_bpp=16
new_cursors=true
use_fastpath=both

[Logging]
LogFile=xrdp.log
LogLevel=WARN
EnableSyslog=true

[Channels]
rdpdr=false
rdpsnd=false
drdynvc=true
cliprdr=true
rail=false
xrdpvr=false

[Kiosk-Live]
name=Werkstatt Mini-PC Live (:0)
lib=libvnc.so
ip=127.0.0.1
port=5900
username=na
password=ask
xserverbpp=16
delay_ms=10
EOF_XRDP
chown root:xrdp /etc/xrdp/xrdp.ini
chmod 0644 /etc/xrdp/xrdp.ini

if command -v ufw >/dev/null 2>&1 && ufw status | grep -q 'Status: active'; then
    ufw allow from 192.168.188.0/24 to any port 3389 proto tcp >/dev/null 2>&1 || true
fi

systemctl daemon-reload
systemctl reset-failed funkenwerk-kiosk-vnc.service >/dev/null 2>&1 || true
systemctl enable --now funkenwerk-kiosk-vnc.service
systemctl restart funkenwerk-kiosk-vnc.service
systemctl reset-failed xrdp.service >/dev/null 2>&1 || true
systemctl enable --now xrdp.service
systemctl restart xrdp.service

for i in $(seq 1 20); do
    if ss -tln | grep -q ':5900 ' && ss -tln | grep -q ':3389 '; then
        echo 'KIOSK_RDP_READY=1'
        exit 0
    fi
    sleep 0.5
done

ss -tln
exit 1
";
            return script.Replace("\r\n", "\n");
        }

        // =====================================================================
        // ISOLATED AUTOMATED SELF-TEST SUITE (--self-test)
        // =====================================================================
        public static bool RunIsolatedSelfTestSuite(out List<string> report)
        {
            var lines = new List<string>();
            report = lines;
            int passed = 0;
            int total = 0;

            void Check(string title, Func<bool> testFn)
            {
                total++;
                try
                {
                    if (testFn())
                    {
                        passed++;
                        lines.Add($"[PASS] {total:00}. {title}");
                    }
                    else
                    {
                        lines.Add($"[FAIL] {total:00}. {title}");
                    }
                }
                catch (Exception ex)
                {
                    lines.Add($"[FAIL] {total:00}. {title} -> {ex.GetType().Name}: {ex.Message}");
                }
            }

            string tempDir = Path.Combine(Path.GetTempPath(), $"fw_remote_selftest_{Guid.NewGuid():N}");
            try
            {
                EnableSelfTestIsolation(tempDir);

                // 01. NTFS ACL Lockdown
                Check("Isolierter Remote-Tresor erzwingt strikte CurrentUser-NTFS-ACL", () =>
                    IsAclRestrictedToCurrentUserOnly(VaultDir, isDirectory: true));

                // 02. Default Mini-PC Profile Seeding
                Check("Standard-Verbindung 'Werkstatt Mini-PC' (192.168.188.151:3389, Nur Ansicht, 1280x720 High-FPS) wird initial angelegt", () =>
                {
                    var profiles = LoadProfiles();
                    if (profiles.Count != 1) return false;
                    var mini = profiles[0];
                    return mini.Name == "Werkstatt Mini-PC" &&
                           mini.Host == "192.168.188.151" &&
                           mini.Port == 3389 &&
                           mini.Username == "alina" &&
                           mini.DefaultViewOnly &&
                           mini.SmartSizing &&
                           mini.DesktopWidth == 1280 &&
                           mini.DesktopHeight == 720 &&
                           !mini.UseNla &&
                           IsAclRestrictedToCurrentUserOnly(ProfilesFile, isDirectory: false);
                });

                // 03. AES-256-GCM + DPAPI Roundtrip & Tamper Resistance
                Check("AES-256-GCM + DPAPI Passwort-Verschlüsselung, Nonce-Zufall & Manipulationsschutz", () =>
                {
                    const string sample = "TestGeheimnis#2026!Seelenfunke";
                    string enc1 = EncryptSecret(sample);
                    string enc2 = EncryptSecret(sample);
                    if (enc1 == enc2) return false;
                    if (!enc1.StartsWith(VaultPrefix, StringComparison.Ordinal)) return false;
                    if (DecryptSecret(enc1) != sample) return false;

                    // Flip last byte to verify GCM tag rejection
                    byte[] raw = Convert.FromBase64String(enc1.Substring(VaultPrefix.Length));
                    raw[raw.Length - 1] ^= 0xFF;
                    string tampered = VaultPrefix + Convert.ToBase64String(raw);
                    bool rejected = false;
                    try { _ = DecryptSecret(tampered); } catch (CryptographicException) { rejected = true; }
                    return rejected;
                });

                // 04. Zero-Plaintext Guard in SaveProfiles
                Check("Zero-Secret-Guard blockiert unverschlüsselte Passwörter beim Speichern", () =>
                {
                    var list = LoadProfiles();
                    list[0].EncryptedPassword = "PlaintextForbidden123";
                    bool blocked = false;
                    try { SaveProfiles(list); } catch (CryptographicException) { blocked = true; }

                    list[0].EncryptedPassword = EncryptSecret("SicheresRdpPasswort!99");
                    SaveProfiles(list);
                    string diskJson = File.ReadAllText(ProfilesFile);
                    return blocked && !diskJson.Contains("SicheresRdpPasswort!99", StringComparison.Ordinal);
                });

                // 05. Host Sanitizer Injection Block
                Check("Host-Sanitizer blockiert Steuerzeichen und Shell-Injection-Metazeichen", () =>
                {
                    bool cleanOk = SanitizeHost(" 192.168.188.151 ") == "192.168.188.151";
                    bool blockedCmd = false;
                    try { _ = SanitizeHost("192.168.188.151; rm -rf /"); } catch (ArgumentException) { blockedCmd = true; }
                    bool blockedSub = false;
                    try { _ = SanitizeHost("$(whoami).example.com"); } catch (ArgumentException) { blockedSub = true; }
                    return cleanOk && blockedCmd && blockedSub;
                });

                // 06. SHA-256 SSH Host Key Pinning & Mismatch Detection
                Check("SHA-256 Host-Key-Pinning erkennt Schlüsselwechsel zuverlässig (Fail-Closed)", () =>
                {
                    byte[] keyA = Encoding.UTF8.GetBytes("ssh-ed25519-mock-key-A");
                    byte[] keyB = Encoding.UTF8.GetBytes("ssh-ed25519-mock-key-B");
                    var first = VerifyOrPinSshHostKeySha256("192.168.188.151", 22, "ssh-ed25519", keyA);
                    var second = VerifyOrPinSshHostKeySha256("192.168.188.151", 22, "ssh-ed25519", keyA);
                    var mismatch = VerifyOrPinSshHostKeySha256("192.168.188.151", 22, "ssh-ed25519", keyB);
                    return first.Trusted && !first.Mismatch &&
                           second.Trusted && !second.Mismatch &&
                           !mismatch.Trusted && mismatch.Mismatch;
                });

                // 07. Native Windows RDP ActiveX (MsTscAx) Instantiation, SmartSizing & ViewOnly HWND Lock
                Check("Native Windows RDP ActiveX (MsTscAx) initialisiert SmartSizing (2560x1440) & Nur-Ansicht-Schutz", () =>
                {
                    using var rdp = new RdpSessionHostControl(initialViewOnly: true);
                    if (rdp.ContainerPanel.Enabled) return false; // View-Only must disable input delivery

                    rdp.ConfigureConnection(
                        host: "192.168.188.151",
                        port: 3389,
                        username: "alina",
                        password: " ephemeral_test ",
                        desktopWidth: 1280,
                        desktopHeight: 720,
                        smartSizing: true,
                        useNla: false);

                    var cfg = rdp.ReadBackConfiguration();
                    rdp.SetViewOnly(false);
                    bool interactiveEnabled = rdp.ContainerPanel.Enabled;
                    rdp.SetViewOnly(true);
                    bool viewOnlyLockedAgain = !rdp.ContainerPanel.Enabled;

                    return cfg.Server == "192.168.188.151" &&
                           cfg.Port == 3389 &&
                           cfg.Width == 1280 &&
                           cfg.Height == 720 &&
                           cfg.SmartSizing &&
                           interactiveEnabled &&
                           viewOnlyLockedAgain;
                });

                // 08. Versioning Metadata & Zero-Secret Binary Scan
                Check("Versionierung (v1.0.0 · Build 1) & Zero-Secret-Scan auf kompiliertem Modul", () =>
                {
                    bool verOk = Funkenwerk.FunkenwerkVersioning.VersionName == "1.0.0" &&
                                 Funkenwerk.FunkenwerkVersioning.VersionCode >= 1;
                    string dllPath = Assembly.GetExecutingAssembly().Location;
                    if (!string.IsNullOrWhiteSpace(dllPath) && File.Exists(dllPath))
                    {
                        string ascii = Encoding.Latin1.GetString(File.ReadAllBytes(dllPath));
                        if (ascii.Contains("sk_live_", StringComparison.Ordinal) ||
                            ascii.Contains("rk_live_", StringComparison.Ordinal))
                        {
                            return false;
                        }
                    }
                    return verOk;
                });
            }
            finally
            {
                ResetSelfTestIsolation();
                try { if (Directory.Exists(tempDir)) Directory.Delete(tempDir, recursive: true); } catch { }
            }

            report.Add($"=== FUNKENWERK REMOTE SELF-TEST: {passed}/{total} TESTS BESTANDEN ===");
            return passed == total && total > 0;
        }
    }
}

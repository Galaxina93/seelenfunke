using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows.Media.Imaging;

namespace Funkenwerk
{
    public class SmartCameraConfig
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string Name { get; set; } = "";
        public string Url { get; set; } = "";
        public string ServiceUrl { get; set; } = "";
        public string Description { get; set; } = "";
        public bool MotionRecord { get; set; } = false;
        public int MotionSensitivity { get; set; } = 50;
        public int Position { get; set; } = 0;
        public bool InvertPan { get; set; } = true;
        public bool InvertTilt { get; set; } = false;

        // Runtime state (not persisted)
        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsOnline { get; set; } = false;
        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsHostReachable { get; set; } = false;
        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsConnecting { get; set; } = true;
        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsMotionDetected { get; set; } = false;
        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsRecordingManual { get; set; } = false;
        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsRecordingMotion { get; set; } = false;
        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsAudioActive { get; set; } = false;
        public string DeviceModel { get; set; } = "IP-Kamera (RTSP/ONVIF)";
        public string Firmware { get; set; } = "";
        public bool HasPtz { get; set; } = true;
        [System.Text.Json.Serialization.JsonIgnore]
        public string StatusText => IsRecordingManual ? "● REC (MANUELL)"
            : (IsRecordingMotion ? "● REC (BEWEGUNG)"
            : (IsOnline ? "LIVE · ONLINE"
            : (IsHostReachable ? "IM WLAN · STREAM PRÜFEN"
            : (IsConnecting ? "VERBINDE..." : "OFFLINE"))));

        [System.Text.Json.Serialization.JsonIgnore]
        public string IpAddress
        {
            get
            {
                try
                {
                    var m = Regex.Match(Url ?? "", @"@([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)");
                    if (m.Success) return m.Groups[1].Value;
                    if (Uri.TryCreate(Url, UriKind.Absolute, out var u)) return u.Host;
                }
                catch { }
                return "";
            }
        }
    }

    public class SmartPlugConfig
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string Name { get; set; } = "";
        public string IpAddress { get; set; } = "";
        public string MacAddress { get; set; } = "";
        public string Model { get; set; } = "Tapo P100";
        public string Firmware { get; set; } = "1.4.6 Build 260617";
        public string Room { get; set; } = "Smart Home";
        public bool IsOn { get; set; } = false;
        public bool IsOnline { get; set; } = false;
        public string StatusNote { get; set; } = "Prüfe lokalen Status...";
    }

    public class SmartRecordingItem
    {
        public string Filename { get; set; } = "";
        public string FullPath { get; set; } = "";
        public string CameraName { get; set; } = "";
        public string DateDisplay { get; set; } = "";
        public DateTime CreatedAt { get; set; }
        public double SizeMb { get; set; }
        public bool IsMotion { get; set; }
        public bool IsSnapshot { get; set; }
    }

    public class LabelPrinterConfig
    {
        public string Name { get; set; } = "MUNBYN ITPP941AP";
        public string IpAddress { get; set; } = "192.168.188.149";
        public string Hostname { get; set; } = "ITPP941AP.fritz.box";
        public string MacAddress { get; set; } = "F0:23:AE:7D:51:35";
        public string SerialNumber { get; set; } = "941AP261000423";
        public string Firmware { get; set; } = "1.2.4";
        public int IppPort { get; set; } = 631;
        public int RawPort { get; set; } = 9100;
        public bool AutoPrintShopJobs { get; set; } = true;
        public string PreferredProtocol { get; set; } = "IPP"; // "IPP" (:631 image/jpeg) or "TSPL" (:9100 raw bitmap)

        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsOnline { get; set; } = false;
        [System.Text.Json.Serialization.JsonIgnore]
        public string StatusNote { get; set; } = "Prüfe WLAN-Status (192.168.188.149)...";
        [System.Text.Json.Serialization.JsonIgnore]
        public string LastJobSummary { get; set; } = "Wartet auf Shop-Druckaufträge";
        [System.Text.Json.Serialization.JsonIgnore]
        public int PrintedSessionCount { get; set; } = 0;
    }

    public class FunkenwerkPrintAddressDto
    {
        [System.Text.Json.Serialization.JsonPropertyName("name")]
        public string Name { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("company")]
        public string Company { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("street")]
        public string Street { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("postal_code")]
        public string PostalCode { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("city")]
        public string City { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("country")]
        public string Country { get; set; } = "DE";
    }

    public class FunkenwerkPrintItemDto
    {
        [System.Text.Json.Serialization.JsonPropertyName("qty")]
        public int Qty { get; set; } = 1;
        [System.Text.Json.Serialization.JsonPropertyName("name")]
        public string Name { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("sku")]
        public string Sku { get; set; } = "";
    }

    public class FunkenwerkPrintJobDto
    {
        [System.Text.Json.Serialization.JsonPropertyName("id")]
        public string Id { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("type")]
        public string Type { get; set; } = "order_label";
        [System.Text.Json.Serialization.JsonPropertyName("status")]
        public string Status { get; set; } = "pending";
        [System.Text.Json.Serialization.JsonPropertyName("printer_ip")]
        public string PrinterIp { get; set; } = "192.168.188.149";
        [System.Text.Json.Serialization.JsonPropertyName("order_id")]
        public string? OrderId { get; set; }
        [System.Text.Json.Serialization.JsonPropertyName("order_number")]
        public string OrderNumber { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("shipment_id")]
        public string? ShipmentId { get; set; }
        [System.Text.Json.Serialization.JsonPropertyName("tracking_number")]
        public string TrackingNumber { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("package_index")]
        public int PackageIndex { get; set; } = 1;
        [System.Text.Json.Serialization.JsonPropertyName("package_total")]
        public int PackageTotal { get; set; } = 1;
        [System.Text.Json.Serialization.JsonPropertyName("weight_kg")]
        public double WeightKg { get; set; } = 0.5;
        [System.Text.Json.Serialization.JsonPropertyName("is_express")]
        public bool IsExpress { get; set; }
        [System.Text.Json.Serialization.JsonPropertyName("sender")]
        public FunkenwerkPrintAddressDto Sender { get; set; } = new();
        [System.Text.Json.Serialization.JsonPropertyName("recipient")]
        public FunkenwerkPrintAddressDto Recipient { get; set; } = new();
        [System.Text.Json.Serialization.JsonPropertyName("items")]
        public List<FunkenwerkPrintItemDto> Items { get; set; } = new();
        [System.Text.Json.Serialization.JsonPropertyName("has_pdf")]
        public bool HasPdf { get; set; }
        [System.Text.Json.Serialization.JsonPropertyName("pdf_base64")]
        public string? PdfBase64 { get; set; }
        [System.Text.Json.Serialization.JsonPropertyName("created_at")]
        public string CreatedAt { get; set; } = "";
        [System.Text.Json.Serialization.JsonPropertyName("printed_at")]
        public string? PrintedAt { get; set; }
        [System.Text.Json.Serialization.JsonPropertyName("error_message")]
        public string? ErrorMessage { get; set; }
    }

    public class SmartHomePersistedData
    {
        public int GridLayoutCount { get; set; } = 4; // 1, 4, 6, 8
        public string SelectedCameraName { get; set; } = "Terasse";
        public string TapoLocalEmail { get; set; } = "";
        public string TapoLocalPassword { get; set; } = "";
        public List<SmartCameraConfig> Cameras { get; set; } = new();
        public List<SmartPlugConfig> Plugs { get; set; } = new();
        public LabelPrinterConfig LabelPrinter { get; set; } = new();
    }

    // =========================================================================
    // 0. DUAL-LAYER CRYPTO VAULT (AES-256-GCM AEAD + PBKDF2-SHA512 + WIN DPAPI)
    //    & NETWORK / TLS / CLI / ATTACHMENT SECURITY GUARDS
    // =========================================================================
    public static class FunkenwerkSecurityVault
    {
        private const string VaultV2Prefix = "FWVAULT2:";
        private const string LegacyDpapiPrefix = "DPAPI:";
        private static readonly byte[] _entropy = Encoding.UTF8.GetBytes("Funkenwerk_Seelenfunke_HardwareVault_v1");
        private static readonly byte[] _aeadAssociatedData = Encoding.UTF8.GetBytes("Funkenwerk-Vault-v2-AEAD-AES256GCM");
        private static string _encryptedSessionRtspPass = "";

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct DATA_BLOB
        {
            public int cbData;
            public IntPtr pbData;
        }

        [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Auto)]
        private static extern bool CryptProtectData(
            ref DATA_BLOB pPlainText,
            string? szDescription,
            ref DATA_BLOB pEntropy,
            IntPtr pReserved,
            IntPtr pPrompt,
            int dwFlags,
            out DATA_BLOB pCipherText);

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

        private const int CRYPTPROTECT_UI_FORBIDDEN = 0x1;

        private static byte[] DeriveMachineUserKey(byte[] salt)
        {
            string identityMaterial = $"{Environment.MachineName}|{Environment.UserName}|Funkenwerk_AES256GCM_KeyDerivation_v2";
            byte[] passwordBytes = Encoding.UTF8.GetBytes(identityMaterial);
            try
            {
                return Rfc2898DeriveBytes.Pbkdf2(
                    passwordBytes,
                    salt,
                    iterations: 100_000,
                    HashAlgorithmName.SHA512,
                    outputLength: 32);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(passwordBytes);
            }
        }

        private static byte[]? DpapiProtectBytes(byte[] plainBytes)
        {
            IntPtr plainPtr = IntPtr.Zero;
            IntPtr entropyPtr = IntPtr.Zero;
            DATA_BLOB outBlob = default;
            try
            {
                plainPtr = Marshal.AllocHGlobal(plainBytes.Length);
                Marshal.Copy(plainBytes, 0, plainPtr, plainBytes.Length);

                entropyPtr = Marshal.AllocHGlobal(_entropy.Length);
                Marshal.Copy(_entropy, 0, entropyPtr, _entropy.Length);

                var inBlob = new DATA_BLOB { cbData = plainBytes.Length, pbData = plainPtr };
                var entBlob = new DATA_BLOB { cbData = _entropy.Length, pbData = entropyPtr };

                if (CryptProtectData(ref inBlob, "FunkenwerkVaultV2", ref entBlob, IntPtr.Zero, IntPtr.Zero, CRYPTPROTECT_UI_FORBIDDEN, out outBlob)
                    && outBlob.pbData != IntPtr.Zero && outBlob.cbData > 0)
                {
                    byte[] cipher = new byte[outBlob.cbData];
                    Marshal.Copy(outBlob.pbData, cipher, 0, outBlob.cbData);
                    return cipher;
                }
            }
            catch { }
            finally
            {
                if (plainPtr != IntPtr.Zero) Marshal.FreeHGlobal(plainPtr);
                if (entropyPtr != IntPtr.Zero) Marshal.FreeHGlobal(entropyPtr);
                if (outBlob.pbData != IntPtr.Zero) LocalFree(outBlob.pbData);
            }
            return null;
        }

        private static byte[]? DpapiUnprotectBytes(byte[] cipherBytes)
        {
            IntPtr cipherPtr = IntPtr.Zero;
            IntPtr entropyPtr = IntPtr.Zero;
            DATA_BLOB outBlob = default;
            try
            {
                cipherPtr = Marshal.AllocHGlobal(cipherBytes.Length);
                Marshal.Copy(cipherBytes, 0, cipherPtr, cipherBytes.Length);

                entropyPtr = Marshal.AllocHGlobal(_entropy.Length);
                Marshal.Copy(_entropy, 0, entropyPtr, _entropy.Length);

                var inBlob = new DATA_BLOB { cbData = cipherBytes.Length, pbData = cipherPtr };
                var entBlob = new DATA_BLOB { cbData = _entropy.Length, pbData = entropyPtr };

                if (CryptUnprotectData(ref inBlob, IntPtr.Zero, ref entBlob, IntPtr.Zero, IntPtr.Zero, CRYPTPROTECT_UI_FORBIDDEN, out outBlob)
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

        /// <summary>
        /// Encrypts a secret using Dual-Layer Authenticated Encryption:
        /// Layer 1: AES-256-GCM (96-bit CSPRNG nonce, 128-bit authentication tag, AAD, PBKDF2-HMAC-SHA512 100,000 iterations).
        /// Layer 2: Windows DPAPI (CryptProtectData bound to current Windows user & machine).
        /// Zeroes all intermediate plaintext and key material in RAM immediately after encryption.
        /// </summary>
        public static string ProtectSecret(string? plainText)
        {
            if (string.IsNullOrEmpty(plainText)) return "";
            if (plainText.StartsWith(VaultV2Prefix, StringComparison.Ordinal)) return plainText;
            if (plainText.StartsWith(LegacyDpapiPrefix, StringComparison.Ordinal))
            {
                plainText = UnprotectSecret(plainText);
                if (string.IsNullOrEmpty(plainText)) return "";
            }

            byte[]? plainBytes = null;
            byte[]? key = null;
            try
            {
                plainBytes = Encoding.UTF8.GetBytes(plainText);
                byte[] salt = RandomNumberGenerator.GetBytes(16);
                byte[] nonce = RandomNumberGenerator.GetBytes(12); // 96-bit GCM nonce
                byte[] tag = new byte[16];                         // 128-bit GCM auth tag
                byte[] cipher = new byte[plainBytes.Length];

                key = DeriveMachineUserKey(salt);
                using (var aesGcm = new AesGcm(key, 16))
                {
                    aesGcm.Encrypt(nonce, plainBytes, cipher, tag, _aeadAssociatedData);
                }

                // Envelope layout: [version=2 (1B)] [salt (16B)] [nonce (12B)] [tag (16B)] [ciphertext (NB)]
                byte[] envelope = new byte[1 + 16 + 12 + 16 + cipher.Length];
                envelope[0] = 0x02;
                Buffer.BlockCopy(salt, 0, envelope, 1, 16);
                Buffer.BlockCopy(nonce, 0, envelope, 17, 12);
                Buffer.BlockCopy(tag, 0, envelope, 29, 16);
                Buffer.BlockCopy(cipher, 0, envelope, 45, cipher.Length);

                byte[]? dpapiWrapped = DpapiProtectBytes(envelope);
                CryptographicOperations.ZeroMemory(envelope);
                if (dpapiWrapped != null && dpapiWrapped.Length > 0)
                {
                    return VaultV2Prefix + Convert.ToBase64String(dpapiWrapped);
                }
            }
            catch { }
            finally
            {
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                if (key != null) CryptographicOperations.ZeroMemory(key);
            }
            return "";
        }

        /// <summary>
        /// Decrypts both modern FWVAULT2: (DPAPI + AES-256-GCM AEAD) and legacy DPAPI: secrets.
        /// </summary>
        public static string UnprotectSecret(string? storedText)
        {
            if (string.IsNullOrEmpty(storedText)) return "";

            if (storedText.StartsWith(VaultV2Prefix, StringComparison.Ordinal))
            {
                byte[]? envelope = null;
                byte[]? key = null;
                byte[]? plainBytes = null;
                try
                {
                    byte[] dpapiCipher = Convert.FromBase64String(storedText.Substring(VaultV2Prefix.Length));
                    envelope = DpapiUnprotectBytes(dpapiCipher);
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

                    key = DeriveMachineUserKey(salt);
                    using (var aesGcm = new AesGcm(key, 16))
                    {
                        aesGcm.Decrypt(nonce, cipher, tag, plainBytes, _aeadAssociatedData);
                    }
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
                }
            }

            if (storedText.StartsWith(LegacyDpapiPrefix, StringComparison.Ordinal))
            {
                byte[]? plainBytes = null;
                try
                {
                    byte[] cipherBytes = Convert.FromBase64String(storedText.Substring(LegacyDpapiPrefix.Length));
                    plainBytes = DpapiUnprotectBytes(cipherBytes);
                    if (plainBytes != null)
                        return Encoding.UTF8.GetString(plainBytes);
                }
                catch { }
                finally
                {
                    if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                }
                return "";
            }

            return storedText;
        }

        public static void SetSessionRtspPassword(string? plainPassword)
        {
            if (string.IsNullOrWhiteSpace(plainPassword)) return;
            _encryptedSessionRtspPass = ProtectSecret(plainPassword);
        }

        public static string GetSessionRtspPassword()
        {
            if (string.IsNullOrEmpty(_encryptedSessionRtspPass)) return "";
            return UnprotectSecret(_encryptedSessionRtspPass);
        }

        public static string MaskSecretForDisplay(string? secret)
        {
            if (string.IsNullOrEmpty(secret)) return "";
            if (secret.Length <= 8) return new string('•', secret.Length);
            return secret.Substring(0, 3) + new string('•', Math.Min(24, secret.Length - 6)) + secret.Substring(secret.Length - 3);
        }

        public static string SanitizeCliArgument(string? rawArg)
        {
            if (string.IsNullOrWhiteSpace(rawArg)) return "";
            var sb = new StringBuilder(rawArg.Length);
            foreach (char c in rawArg)
            {
                if (c is '"' or '\r' or '\n' or '\0' or '`' or '$' or '|' or '&' or ';')
                    continue;
                sb.Append(c);
            }
            return sb.ToString().Trim();
        }

        public static HttpClient CreateSecureHttpClient(TimeSpan timeout)
        {
            var handler = new SocketsHttpHandler
            {
                PooledConnectionLifetime = TimeSpan.FromMinutes(10),
                PooledConnectionIdleTimeout = TimeSpan.FromMinutes(4),
                MaxConnectionsPerServer = 8,
                ConnectTimeout = TimeSpan.FromSeconds(8),
                AllowAutoRedirect = false, // Never leak Bearer tokens across HTTP redirects
                UseCookies = false,
                MaxResponseHeadersLength = 64, // 64 KB cap against header bomb attacks
                SslOptions = new System.Net.Security.SslClientAuthenticationOptions
                {
                    EnabledSslProtocols = System.Security.Authentication.SslProtocols.Tls12 | System.Security.Authentication.SslProtocols.Tls13,
                    CertificateRevocationCheckMode = System.Security.Cryptography.X509Certificates.X509RevocationMode.NoCheck
                }
            };

            var client = new HttpClient(handler)
            {
                Timeout = timeout,
                MaxResponseContentBufferSize = 64L * 1024L * 1024L // 64 MB response cap
            };
            client.DefaultRequestHeaders.UserAgent.ParseAdd("Funkenwerk-SecurityClient/4.0");
            return client;
        }

        public static (bool AllPassed, string DetailedReport) RunSecuritySelfAudit(string settingsJsonPath, string smartHomeJsonPath)
        {
            var sb = new StringBuilder();
            bool allOk = true;
            void Check(string name, bool ok, string detail)
            {
                if (!ok) allOk = false;
                sb.AppendLine($"[{(ok ? "PASS" : "FAIL")}] {name}: {detail}");
            }

            // 1. Test FWVAULT2 AES-256-GCM + PBKDF2-SHA512 + DPAPI Roundtrip & Tamper Detection
            string probeSecret = "Funkenwerk_QuantumSafe_AuditProbe_" + Guid.NewGuid().ToString("N");
            string encrypted = ProtectSecret(probeSecret);
            bool hasV2Prefix = encrypted.StartsWith(VaultV2Prefix, StringComparison.Ordinal);
            string decrypted = UnprotectSecret(encrypted);
            bool roundtripOk = string.Equals(probeSecret, decrypted, StringComparison.Ordinal);
            Check("AES-256-GCM + PBKDF2-SHA512 (100k) + Windows DPAPI Vault", hasV2Prefix && roundtripOk,
                $"Prefix='{VaultV2Prefix}', CipherLen={encrypted.Length} chars, AEAD Roundtrip={roundtripOk}");

            // 2. Test Tamper Resistance (Bit-flip in ciphertext must be rejected by GCM tag / DPAPI)
            string tampered = encrypted.Substring(0, encrypted.Length - 6) + "XXXXXX";
            string tamperedResult = UnprotectSecret(tampered);
            Check("AEAD Authenticity & Tamper Detection", string.IsNullOrEmpty(tamperedResult),
                "Manipulated ciphertext rejected with zero plaintext leakage");

            // 3. Verify settings.json on disk contains ZERO cleartext tokens
            if (File.Exists(settingsJsonPath))
            {
                string rawSettings = File.ReadAllText(settingsJsonPath);
                bool noPlainSanctum = !Regex.IsMatch(rawSettings, @"""ApiToken[A-Za-z]*""\s*:\s*""(?!FWVAULT2:|DPAPI:|"")[^""]+""");
                bool usesVault = rawSettings.Contains(VaultV2Prefix, StringComparison.Ordinal) || rawSettings.Contains(LegacyDpapiPrefix, StringComparison.Ordinal);
                Check("settings.json At-Rest Token Encryption", noPlainSanctum && usesVault,
                    $"NoCleartextBearerTokens={noPlainSanctum}, EncryptedVaultFormat={usesVault}");
            }

            // 4. Verify smarthome.json on disk contains ZERO cleartext RTSP passwords or Tapo passwords
            if (File.Exists(smartHomeJsonPath))
            {
                string rawSmart = File.ReadAllText(smartHomeJsonPath);
                bool noPlainRtsp = !rawSmart.Contains("rtsp://", StringComparison.OrdinalIgnoreCase)
                                && !Regex.IsMatch(rawSmart, @"""(?:TapoLocalEmail|TapoLocalPassword|Url)""\s*:\s*""(?!FWVAULT2:|DPAPI:|"")[^""]+""");
                Check("smarthome.json At-Rest RTSP & KLAP Credential Encryption", noPlainRtsp,
                    $"ZeroCleartextRtspOrPasswordsOnDisk={noPlainRtsp}");
            }

            // 5. Verify Network SSRF & HTTPS Enforcement Guards
            string enforcedHttps = NormalizeSecureApiUrl("http://stage.mein-seelenfunke.de/api");
            bool httpsEnforced = enforcedHttps.StartsWith("https://", StringComparison.OrdinalIgnoreCase);
            bool externalIpBlockedForLan = !IsSafePrivateLanHost("8.8.8.8") && !IsSafePrivateLanHost("1.1.1.1") && !IsSafeRtspUrl("rtsp://8.8.8.8:554/stream1");
            bool privateLanAllowed = IsSafePrivateLanHost("192.168.188.105") && IsSafePrivateLanHost("127.0.0.1");
            Check("Network TLS Enforcement & RFC1918 SSRF Firewall", httpsEnforced && externalIpBlockedForLan && privateLanAllowed,
                $"ExternalHttpUpgradedToHttps={httpsEnforced}, WanIpBlockedFromLanEngines={externalIpBlockedForLan}");

            // 6. Verify CLI Injection & Dangerous Attachment Guards
            string sanitized = SanitizeCliArgument("HD Pro Webcam\" & calc.exe | \r\n");
            bool cliSafe = !sanitized.Contains('"') && !sanitized.Contains('&') && !sanitized.Contains('|') && !sanitized.Contains('\n');
            bool attachSafe = IsDangerousAttachmentFile("invoice.pdf.exe") && IsDangerousAttachmentFile("script.ps1") && !IsDangerousAttachmentFile("angebot.pdf");
            Check("Subprocess CLI Sanitizer & Malware Attachment Shield", cliSafe && attachSafe,
                $"CliMetaCharsStripped={cliSafe}, ExecutableAttachmentBlocked={attachSafe}");

            // 7. Verify Print-Bridge PDF Magic-Bytes, Port Whitelist & IPP Header Sanitizer
            byte[] validPdfProbe = Encoding.ASCII.GetBytes("%PDF-1.7\n" + new string('A', 80));
            byte[] fakePdfProbe = Encoding.ASCII.GetBytes("MZ\x90\x00" + new string('A', 80));
            bool pdfGuardOk = IsValidPdfPayload(validPdfProbe) && !IsValidPdfPayload(fakePdfProbe);
            bool portGuardOk = IsSafePrinterPort(631) && IsSafePrinterPort(9100) && !IsSafePrinterPort(22) && !IsSafePrinterPort(445) && !IsSafePrinterPort(3389);
            string ippClean = SanitizeIppString("Seelenfunke-#ORD-1\r\n\0Injected");
            bool ippSafe = !ippClean.Contains('\r') && !ippClean.Contains('\n') && !ippClean.Contains('\0');
            Check("Print-Bridge PDF Magic-Byte, Port Whitelist & IPP Shield", pdfGuardOk && portGuardOk && ippSafe,
                $"PdfMagicVerified={pdfGuardOk}, DangerousPortsBlocked={portGuardOk}, IppHeaderSanitized={ippSafe}");

            return (allOk, sb.ToString());
        }

        public static bool IsValidPdfPayload(byte[]? pdfBytes)
        {
            if (pdfBytes == null || pdfBytes.Length < 64 || pdfBytes.Length > 10 * 1024 * 1024)
                return false;
            // Verify strict "%PDF-" magic header (0x25, 0x50, 0x44, 0x46, 0x2D)
            return pdfBytes[0] == 0x25 &&
                   pdfBytes[1] == 0x50 &&
                   pdfBytes[2] == 0x44 &&
                   pdfBytes[3] == 0x46 &&
                   pdfBytes[4] == 0x2D;
        }

        public static bool IsSafePrinterPort(int port)
        {
            // Strictly allow AirPrint/IPP (631) or RAW Thermal JetDirect ports (9100..9109)
            return port == 631 || (port >= 9100 && port <= 9109);
        }

        public static string SanitizeIppString(string? raw, int maxLen = 64)
        {
            if (string.IsNullOrWhiteSpace(raw)) return "Seelenfunke-Label";
            var sb = new StringBuilder(Math.Min(raw.Length, maxLen));
            foreach (char c in raw)
            {
                if (c < 0x20 || c > 0x7E) continue;
                sb.Append(c);
                if (sb.Length >= maxLen) break;
            }
            return sb.Length > 0 ? sb.ToString() : "Seelenfunke-Label";
        }

        public static void SecureZeroDeleteFile(string? filePath)
        {
            if (string.IsNullOrWhiteSpace(filePath)) return;
            try
            {
                if (File.Exists(filePath))
                {
                    var fi = new FileInfo(filePath);
                    long len = Math.Min(fi.Length, 16L * 1024L * 1024L);
                    if (len > 0)
                    {
                        byte[] zeros = new byte[(int)Math.Min(len, 65536)];
                        using var fs = new FileStream(filePath, FileMode.Open, FileAccess.Write, FileShare.None);
                        long remaining = len;
                        while (remaining > 0)
                        {
                            int toWrite = (int)Math.Min(remaining, zeros.Length);
                            fs.Write(zeros, 0, toWrite);
                            remaining -= toWrite;
                        }
                        fs.Flush(true);
                    }
                    File.Delete(filePath);
                }
            }
            catch
            {
                try { if (File.Exists(filePath)) File.Delete(filePath); } catch { }
            }
        }

        public static bool IsSafePrivateLanHost(string? hostOrIp)
        {
            if (string.IsNullOrWhiteSpace(hostOrIp)) return false;
            string clean = hostOrIp.Trim();
            if (string.Equals(clean, "localhost", StringComparison.OrdinalIgnoreCase)) return true;
            if (!IPAddress.TryParse(clean, out var ip)) return false;
            if (IPAddress.IsLoopback(ip)) return true;

            byte[] b = ip.GetAddressBytes();
            if (b.Length != 4) return false;
            // RFC 1918 Private IPv4 Ranges: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
            if (b[0] == 10) return true;
            if (b[0] == 172 && b[1] >= 16 && b[1] <= 31) return true;
            if (b[0] == 192 && b[1] == 168) return true;
            return false;
        }

        public static bool IsSafeRtspUrl(string? url)
        {
            if (string.IsNullOrWhiteSpace(url)) return false;
            string trimmed = url.Trim();
            if (trimmed.StartsWith("-", StringComparison.Ordinal) || trimmed.IndexOfAny(new[] { '\r', '\n', '\0', '"' }) >= 0)
                return false;
            if (!trimmed.StartsWith("rtsp://", StringComparison.OrdinalIgnoreCase) &&
                !trimmed.StartsWith("rtsps://", StringComparison.OrdinalIgnoreCase))
                return false;

            var (host, _, _) = OnvifPtzClient.ParseRtspCredentials(trimmed);
            return IsSafePrivateLanHost(host);
        }

        private static readonly HashSet<string> _blockedExecutableExtensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".exe", ".dll", ".bat", ".cmd", ".ps1", ".psm1", ".vbs", ".vbe",
            ".js", ".jse", ".wsf", ".wsh", ".msc", ".msi", ".msp", ".com",
            ".scr", ".hta", ".cpl", ".jar", ".reg", ".lnk", ".pif", ".inf",
            ".sct", ".shb", ".sys", ".chm", ".iso", ".vhd", ".vhdx"
        };

        public static bool IsDangerousAttachmentFile(string? filePathOrName)
        {
            if (string.IsNullOrWhiteSpace(filePathOrName)) return true;
            string clean = filePathOrName.Trim().TrimEnd('.', ' ');
            string ext = Path.GetExtension(clean);
            return !string.IsNullOrEmpty(ext) && _blockedExecutableExtensions.Contains(ext);
        }

        public static string NormalizeSecureApiUrl(string? rawUrl)
        {
            string trimmed = (rawUrl ?? "").Trim().TrimEnd('/');
            if (string.IsNullOrEmpty(trimmed) || trimmed.IndexOfAny(new[] { '\r', '\n', '\0', ' ' }) >= 0)
                return "http://127.0.0.1/api";
            if (!Uri.TryCreate(trimmed, UriKind.Absolute, out var uri))
                return "http://127.0.0.1/api";
            if (!string.IsNullOrEmpty(uri.UserInfo))
                return "http://127.0.0.1/api"; // Reject embedded credentials in API URLs
            if (!string.Equals(uri.Scheme, "http", StringComparison.OrdinalIgnoreCase) &&
                !string.Equals(uri.Scheme, "https", StringComparison.OrdinalIgnoreCase))
                return "http://127.0.0.1/api";

            bool isLocal = string.Equals(uri.Host, "127.0.0.1", StringComparison.OrdinalIgnoreCase)
                        || string.Equals(uri.Host, "localhost", StringComparison.OrdinalIgnoreCase)
                        || IsSafePrivateLanHost(uri.Host);

            // Force HTTPS for all external internet servers (e.g. stage.mein-seelenfunke.de / mein-seelenfunke.de)
            // so Sanctum Bearer tokens are NEVER transmitted in cleartext over the internet.
            if (!isLocal && string.Equals(uri.Scheme, "http", StringComparison.OrdinalIgnoreCase))
            {
                var builder = new UriBuilder(uri) { Scheme = "https", Port = uri.IsDefaultPort ? -1 : uri.Port };
                return builder.Uri.ToString().TrimEnd('/');
            }

            return trimmed;
        }
    }

    // =========================================================================
    // 1. PURE C# ONVIF PORT 2020 PTZ & DEVICE CLIENT (WS-Security, Low-Latency)
    // =========================================================================
    public static class OnvifPtzClient
    {
        private static readonly HttpClient _http = new HttpClient(new SocketsHttpHandler
        {
            PooledConnectionLifetime = TimeSpan.FromMinutes(10),
            PooledConnectionIdleTimeout = TimeSpan.FromMinutes(5),
            MaxConnectionsPerServer = 6,
            ConnectTimeout = TimeSpan.FromMilliseconds(900),
            AllowAutoRedirect = false,
            UseCookies = false
        })
        {
            Timeout = TimeSpan.FromMilliseconds(1400)
        };

        private static readonly ConcurrentDictionary<string, SemaphoreSlim> _hostLocks = new(StringComparer.OrdinalIgnoreCase);
        private static readonly ConcurrentDictionary<string, DateTime> _lastPtzActivity = new(StringComparer.OrdinalIgnoreCase);

        private static SemaphoreSlim GetHostLock(string host)
            => _hostLocks.GetOrAdd(host ?? "", _ => new SemaphoreSlim(1, 1));

        public static void MarkPtzActivity(string host)
        {
            if (!string.IsNullOrWhiteSpace(host))
                _lastPtzActivity[host] = DateTime.UtcNow;
        }

        public static DateTime GetLastPtzActivity(string host)
        {
            if (!string.IsNullOrWhiteSpace(host) && _lastPtzActivity.TryGetValue(host, out var dt))
                return dt;
            return DateTime.MinValue;
        }

        public static (string Host, string User, string Password) ParseRtspCredentials(string rtspUrl)
        {
            if (string.IsNullOrWhiteSpace(rtspUrl)) return ("", "", "");
            try
            {
                var m = Regex.Match(rtspUrl, @"^rtsp://([^:]+):([^@]+)@([^:/]+)", RegexOptions.IgnoreCase);
                if (m.Success)
                {
                    string user = Uri.UnescapeDataString(m.Groups[1].Value);
                    string pass = Uri.UnescapeDataString(m.Groups[2].Value);
                    string host = m.Groups[3].Value;
                    FunkenwerkSecurityVault.SetSessionRtspPassword(pass);
                    return (host, user, pass);
                }
                if (Uri.TryCreate(rtspUrl, UriKind.Absolute, out var u))
                {
                    string user = "";
                    string pass = "";
                    if (!string.IsNullOrEmpty(u.UserInfo))
                    {
                        var parts = u.UserInfo.Split(':', 2);
                        user = Uri.UnescapeDataString(parts[0]);
                        if (parts.Length > 1) pass = Uri.UnescapeDataString(parts[1]);
                        FunkenwerkSecurityVault.SetSessionRtspPassword(pass);
                    }
                    if (string.IsNullOrEmpty(pass))
                    {
                        pass = FunkenwerkSecurityVault.GetSessionRtspPassword();
                    }
                    return (u.Host, user, pass);
                }
            }
            catch { }
            return ("", "", "");
        }

        private static string BuildWsSecurityHeader(string username, string password)
        {
            byte[] nonceBytes = RandomNumberGenerator.GetBytes(16);
            string nonceB64 = Convert.ToBase64String(nonceBytes);
            string created = DateTime.UtcNow.ToString("yyyy-MM-ddTHH:mm:ss.000Z", CultureInfo.InvariantCulture);

            byte[] createdBytes = Encoding.ASCII.GetBytes(created);
            byte[] passBytes = Encoding.UTF8.GetBytes(password ?? "");
            byte[] combined = new byte[nonceBytes.Length + createdBytes.Length + passBytes.Length];
            Buffer.BlockCopy(nonceBytes, 0, combined, 0, nonceBytes.Length);
            Buffer.BlockCopy(createdBytes, 0, combined, nonceBytes.Length, createdBytes.Length);
            Buffer.BlockCopy(passBytes, 0, combined, nonceBytes.Length + createdBytes.Length, passBytes.Length);

            byte[] sha1 = SHA1.HashData(combined);
            string digestB64 = Convert.ToBase64String(sha1);

            return $@"<s:Header>
    <Security s:mustUnderstand=""1"" xmlns=""http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"">
      <UsernameToken>
        <Username>{SecurityElementEscape(username)}</Username>
        <Password Type=""http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest"">{digestB64}</Password>
        <Nonce EncodingType=""http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary"">{nonceB64}</Nonce>
        <Created xmlns=""http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"">{created}</Created>
      </UsernameToken>
    </Security>
  </s:Header>";
        }

        private static string SecurityElementEscape(string s)
            => (s ?? "").Replace("&", "&amp;").Replace("<", "&lt;").Replace(">", "&gt;").Replace("\"", "&quot;");

        private static async Task<string?> SendSoapAsync(string rtspUrl, string servicePath, string bodyXml, string? customServiceUrl = null)
        {
            var (host, user, pass) = ParseRtspCredentials(rtspUrl);
            if (string.IsNullOrEmpty(host) || !FunkenwerkSecurityVault.IsSafePrivateLanHost(host)) return null;

            string envelope = $@"<?xml version=""1.0"" encoding=""utf-8""?>
<s:Envelope xmlns:s=""http://www.w3.org/2003/05/soap-envelope""
            xmlns:tds=""http://www.onvif.org/ver10/device/wsdl""
            xmlns:trt=""http://www.onvif.org/ver10/media/wsdl""
            xmlns:tptz=""http://www.onvif.org/ver20/ptz/wsdl""
            xmlns:tt=""http://www.onvif.org/ver10/schema"">
  {BuildWsSecurityHeader(user, pass)}
  <s:Body>
    {bodyXml}
  </s:Body>
</s:Envelope>";

            var endpoints = new List<string> { $"http://{host}:2020{servicePath}" };
            if (!string.IsNullOrWhiteSpace(customServiceUrl) &&
                Uri.TryCreate(customServiceUrl, UriKind.Absolute, out var cu) &&
                !cu.IsDefaultPort && cu.Port != 2020 && cu.Port != 80)
            {
                string candidate = $"{cu.Scheme}://{cu.Authority}{servicePath}";
                if (!endpoints.Contains(candidate, StringComparer.OrdinalIgnoreCase))
                    endpoints.Add(candidate);
            }

            foreach (var ep in endpoints)
            {
                try
                {
                    using var req = new HttpRequestMessage(HttpMethod.Post, ep)
                    {
                        Content = new StringContent(envelope, Encoding.UTF8, "application/soap+xml")
                    };
                    req.Headers.ExpectContinue = false;
                    using var resp = await _http.SendAsync(req, HttpCompletionOption.ResponseHeadersRead);
                    string xml = await resp.Content.ReadAsStringAsync();
                    if (!string.IsNullOrWhiteSpace(xml) && xml.Contains("Envelope", StringComparison.OrdinalIgnoreCase))
                        return xml;
                }
                catch { }
            }
            return null;
        }

        public static async Task<(string Model, string Firmware, bool HasPtz)> QueryDeviceInfoAsync(string rtspUrl, string? serviceUrl = null)
        {
            string? xml = await SendSoapAsync(rtspUrl, "/onvif/device_service", "<tds:GetDeviceInformation/>", serviceUrl);
            if (string.IsNullOrEmpty(xml)) return ("IP-Kamera", "", true);

            string model = "IP-Kamera";
            string fw = "";
            var mModel = Regex.Match(xml, @"<[^>]*Model>([^<]+)<");
            if (mModel.Success) model = mModel.Groups[1].Value.Trim();
            var mFw = Regex.Match(xml, @"<[^>]*FirmwareVersion>([^<]+)<");
            if (mFw.Success) fw = mFw.Groups[1].Value.Trim();

            return (model, fw, true);
        }

        public static async Task<bool> RelativeMoveAsync(string rtspUrl, double panDelta, double tiltDelta, string? serviceUrl = null)
        {
            var (host, _, _) = ParseRtspCredentials(rtspUrl);
            MarkPtzActivity(host);

            string px = panDelta.ToString("0.###", CultureInfo.InvariantCulture);
            string ty = tiltDelta.ToString("0.###", CultureInfo.InvariantCulture);

            string body = $@"<tptz:RelativeMove>
      <tptz:ProfileToken>profile_1</tptz:ProfileToken>
      <tptz:Translation>
        <tt:PanTilt x=""{px}"" y=""{ty}""/>
      </tptz:Translation>
    </tptz:RelativeMove>";

            string? res = await SendSoapAsync(rtspUrl, "/onvif/ptz_service", body, serviceUrl);
            return res != null && res.Contains("RelativeMoveResponse", StringComparison.OrdinalIgnoreCase);
        }

        public static async Task<bool> ContinuousMoveAsync(string rtspUrl, double panX, double tiltY, double zoomZ = 0, string? serviceUrl = null)
        {
            var (host, _, _) = ParseRtspCredentials(rtspUrl);
            MarkPtzActivity(host);

            string px = panX.ToString("0.##", CultureInfo.InvariantCulture);
            string ty = tiltY.ToString("0.##", CultureInfo.InvariantCulture);
            string zz = zoomZ.ToString("0.##", CultureInfo.InvariantCulture);

            string velocityInner = Math.Abs(zoomZ) > 0.01
                ? $@"<tt:Zoom x=""{zz}""/>"
                : $@"<tt:PanTilt x=""{px}"" y=""{ty}""/>";

            string body = $@"<tptz:ContinuousMove>
      <tptz:ProfileToken>profile_1</tptz:ProfileToken>
      <tptz:Velocity>{velocityInner}</tptz:Velocity>
    </tptz:ContinuousMove>";

            string? res = await SendSoapAsync(rtspUrl, "/onvif/ptz_service", body, serviceUrl);
            return res != null && res.Contains("ContinuousMoveResponse", StringComparison.OrdinalIgnoreCase);
        }

        public static async Task<bool> StopMoveAsync(string rtspUrl, string? serviceUrl = null)
        {
            var (host, _, _) = ParseRtspCredentials(rtspUrl);
            MarkPtzActivity(host);

            string body = @"<tptz:Stop>
      <tptz:ProfileToken>profile_1</tptz:ProfileToken>
      <tptz:PanTilt>true</tptz:PanTilt>
      <tptz:Zoom>true</tptz:Zoom>
    </tptz:Stop>";

            string? res = await SendSoapAsync(rtspUrl, "/onvif/ptz_service", body, serviceUrl);
            return res != null && res.Contains("StopResponse", StringComparison.OrdinalIgnoreCase);
        }

        /// <summary>
        /// Executes a single, non-overlapping hardware micro-step via ONVIF RelativeMove.
        /// Never calls ContinuousMove and never sends an immediate Stop that would abort the step.
        /// </summary>
        public static async Task<bool> StepMoveAsync(string rtspUrl, double panDelta, double tiltDelta, string? serviceUrl = null)
        {
            var (host, _, _) = ParseRtspCredentials(rtspUrl);
            MarkPtzActivity(host);
            var sem = GetHostLock(host);
            if (!await sem.WaitAsync(0))
                return false; // Drop overlapping tick so commands never queue up or lag

            try
            {
                return await RelativeMoveAsync(rtspUrl, panDelta, tiltDelta, serviceUrl);
            }
            finally
            {
                sem.Release();
            }
        }
    }

    // =========================================================================
    // 2. LIVE CAMERA AUDIO STREAMING VIA WINMM.DLL WAVEOUT (24kHz 16-bit PCM)
    // =========================================================================
    public sealed class CameraLiveAudioPlayer : IDisposable
    {
        [StructLayout(LayoutKind.Sequential)]
        private struct WAVEFORMATEX
        {
            public ushort wFormatTag;
            public ushort nChannels;
            public uint nSamplesPerSec;
            public uint nAvgBytesPerSec;
            public ushort nBlockAlign;
            public ushort wBitsPerSample;
            public ushort cbSize;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct WAVEHDR
        {
            public IntPtr lpData;
            public uint dwBufferLength;
            public uint dwBytesRecorded;
            public IntPtr dwUser;
            public uint dwFlags;
            public uint dwLoops;
            public IntPtr lpNext;
            public IntPtr reserved;
        }

        [DllImport("winmm.dll")]
        private static extern int waveOutOpen(out IntPtr hWaveOut, int uDeviceID, ref WAVEFORMATEX lpFormat, IntPtr dwCallback, IntPtr dwInstance, int dwFlags);
        [DllImport("winmm.dll")]
        private static extern int waveOutPrepareHeader(IntPtr hWaveOut, IntPtr lpWaveOutHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveOutWrite(IntPtr hWaveOut, IntPtr lpWaveOutHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveOutUnprepareHeader(IntPtr hWaveOut, IntPtr lpWaveOutHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveOutReset(IntPtr hWaveOut);
        [DllImport("winmm.dll")]
        private static extern int waveOutClose(IntPtr hWaveOut);

        private const uint WHDR_DONE = 0x00000001;
        private const uint WHDR_PREPARED = 0x00000002;

        private CancellationTokenSource? _cts;
        private Process? _ffmpegProc;
        public string ActiveCameraName { get; private set; } = "";
        public bool IsPlaying => _cts != null && !_cts.IsCancellationRequested;
        public double Volume { get; set; } = 0.9;

        public void Start(string cameraName, string rtspUrl, string ffmpegPath)
        {
            Stop();
            ActiveCameraName = cameraName;
            _cts = new CancellationTokenSource();
            var token = _cts.Token;

            Task.Run(() => AudioWorkerLoop(cameraName, rtspUrl, ffmpegPath, token), token);
        }

        public void Stop()
        {
            ActiveCameraName = "";
            try { _cts?.Cancel(); } catch { }
            _cts = null;
            try
            {
                if (_ffmpegProc != null && !_ffmpegProc.HasExited)
                    _ffmpegProc.Kill(true);
            }
            catch { }
            _ffmpegProc = null;
        }

        private void AudioWorkerLoop(string cameraName, string rtspUrl, string ffmpegPath, CancellationToken token)
        {
            IntPtr hWaveOut = IntPtr.Zero;
            var headers = new List<(IntPtr hdrPtr, IntPtr dataPtr, int cap)>();

            try
            {
                var fmt = new WAVEFORMATEX
                {
                    wFormatTag = 1, // WAVE_FORMAT_PCM
                    nChannels = 1,
                    nSamplesPerSec = 24000,
                    wBitsPerSample = 16,
                    nBlockAlign = 2,
                    nAvgBytesPerSec = 48000,
                    cbSize = 0
                };

                if (waveOutOpen(out hWaveOut, -1, ref fmt, IntPtr.Zero, IntPtr.Zero, 0) != 0 || hWaveOut == IntPtr.Zero)
                    return;

                const int numBuffers = 12;
                const int bufSize = 4800; // 100ms of 24kHz 16-bit mono
                for (int i = 0; i < numBuffers; i++)
                {
                    IntPtr dataPtr = Marshal.AllocHGlobal(bufSize);
                    IntPtr hdrPtr = Marshal.AllocHGlobal(Marshal.SizeOf<WAVEHDR>());
                    var hdr = new WAVEHDR
                    {
                        lpData = dataPtr,
                        dwBufferLength = (uint)bufSize,
                        dwFlags = WHDR_DONE
                    };
                    Marshal.StructureToPtr(hdr, hdrPtr, false);
                    headers.Add((hdrPtr, dataPtr, bufSize));
                }

                // Prefer /stream1 for audio so we don't collide with /stream2 video worker on Tapo cameras
                var urls = new List<string>();
                if (rtspUrl.Contains("/stream2", StringComparison.OrdinalIgnoreCase))
                    urls.Add(rtspUrl.Replace("/stream2", "/stream1", StringComparison.OrdinalIgnoreCase));
                urls.Add(rtspUrl);

                foreach (var targetUrl in urls)
                {
                    if (token.IsCancellationRequested) break;
                    if (!FunkenwerkSecurityVault.IsSafeRtspUrl(targetUrl)) continue;

                    var psi = new ProcessStartInfo
                    {
                        FileName = ffmpegPath,
                        UseShellExecute = false,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true,
                        CreateNoWindow = true
                    };
                    foreach (var arg in new[] { "-rtsp_transport", "tcp", "-fflags", "nobuffer", "-flags", "low_delay", "-i", targetUrl, "-vn", "-f", "s16le", "-acodec", "pcm_s16le", "-ar", "24000", "-ac", "1", "-" })
                    {
                        psi.ArgumentList.Add(arg);
                    }

                    using var proc = Process.Start(psi);
                    if (proc == null) continue;
                    _ffmpegProc = proc;

                    var stream = proc.StandardOutput.BaseStream;
                    byte[] readBuf = new byte[bufSize];
                    int hdrIdx = 0;
                    bool gotAnyAudio = false;

                    while (!token.IsCancellationRequested && !proc.HasExited)
                    {
                        int filled = 0;
                        while (filled < bufSize && !token.IsCancellationRequested)
                        {
                            int n = stream.Read(readBuf, filled, bufSize - filled);
                            if (n <= 0) break;
                            filled += n;
                        }
                        if (filled <= 0) break;
                        gotAnyAudio = true;

                        // Apply volume scaling if needed
                        double vol = Math.Clamp(Volume, 0.0, 1.5);
                        if (Math.Abs(vol - 1.0) > 0.02)
                        {
                            for (int i = 0; i + 1 < filled; i += 2)
                            {
                                short sample = (short)(readBuf[i] | (readBuf[i + 1] << 8));
                                int scaled = (int)(sample * vol);
                                if (scaled > short.MaxValue) scaled = short.MaxValue;
                                else if (scaled < short.MinValue) scaled = short.MinValue;
                                readBuf[i] = (byte)(scaled & 0xFF);
                                readBuf[i + 1] = (byte)((scaled >> 8) & 0xFF);
                            }
                        }

                        // Wait for a free waveOut buffer
                        var (hdrPtr, dataPtr, _) = headers[hdrIdx];
                        hdrIdx = (hdrIdx + 1) % numBuffers;

                        int waitSpins = 0;
                        while (!token.IsCancellationRequested && waitSpins < 60)
                        {
                            var curHdr = Marshal.PtrToStructure<WAVEHDR>(hdrPtr);
                            if ((curHdr.dwFlags & WHDR_DONE) != 0) break;
                            Thread.Sleep(8);
                            waitSpins++;
                        }
                        if (token.IsCancellationRequested) break;

                        var oldHdr = Marshal.PtrToStructure<WAVEHDR>(hdrPtr);
                        if ((oldHdr.dwFlags & WHDR_PREPARED) != 0)
                        {
                            waveOutUnprepareHeader(hWaveOut, hdrPtr, Marshal.SizeOf<WAVEHDR>());
                        }

                        Marshal.Copy(readBuf, 0, dataPtr, filled);
                        var newHdr = new WAVEHDR
                        {
                            lpData = dataPtr,
                            dwBufferLength = (uint)filled,
                            dwFlags = 0
                        };
                        Marshal.StructureToPtr(newHdr, hdrPtr, false);
                        waveOutPrepareHeader(hWaveOut, hdrPtr, Marshal.SizeOf<WAVEHDR>());
                        waveOutWrite(hWaveOut, hdrPtr, Marshal.SizeOf<WAVEHDR>());
                    }

                    try { if (!proc.HasExited) proc.Kill(true); } catch { }
                    if (gotAnyAudio) break;
                }
            }
            catch { }
            finally
            {
                if (hWaveOut != IntPtr.Zero)
                {
                    try { waveOutReset(hWaveOut); } catch { }
                    foreach (var (hdrPtr, dataPtr, _) in headers)
                    {
                        try
                        {
                            var h = Marshal.PtrToStructure<WAVEHDR>(hdrPtr);
                            if ((h.dwFlags & WHDR_PREPARED) != 0)
                                waveOutUnprepareHeader(hWaveOut, hdrPtr, Marshal.SizeOf<WAVEHDR>());
                        }
                        catch { }
                        Marshal.FreeHGlobal(hdrPtr);
                        Marshal.FreeHGlobal(dataPtr);
                    }
                    try { waveOutClose(hWaveOut); } catch { }
                }
            }
        }

        public void Dispose() => Stop();
    }

    // =========================================================================
    // 3. LIVE RTSP CAMERA STREAM & MOTION RECORDING WORKER
    // =========================================================================
    public sealed class CameraStreamWorker : IDisposable
    {
        public SmartCameraConfig Config { get; }
        private readonly string _ffmpegPath;
        private readonly string _recordingsDir;
        private readonly Action<string, BitmapImage?> _onFrameUpdated;
        private readonly Action<string> _onStateChanged;

        private CancellationTokenSource? _cts;
        private Process? _streamProc;
        private Process? _manualRecProc;
        private Process? _motionRecProc;

        public byte[]? LatestJpegBytes { get; private set; }
        public BitmapImage? LatestBitmap { get; private set; }
        private byte[]? _prevLumaGrid;
        private int _consecutiveMotionHits = 0;
        private int _motionWarmupFrames = 0;
        private DateTime _lastMotionEval = DateTime.MinValue;
        private DateTime _lastMotionTrigger = DateTime.MinValue;

        public CameraStreamWorker(
            SmartCameraConfig config,
            string ffmpegPath,
            string recordingsDir,
            Action<string, BitmapImage?> onFrameUpdated,
            Action<string> onStateChanged)
        {
            Config = config;
            _ffmpegPath = ffmpegPath;
            _recordingsDir = recordingsDir;
            _onFrameUpdated = onFrameUpdated;
            _onStateChanged = onStateChanged;
        }

        public void Start()
        {
            if (_cts != null && !_cts.IsCancellationRequested) return;
            _cts = new CancellationTokenSource();
            var token = _cts.Token;
            Task.Run(() => RunStreamLoopAsync(token), token);
            Task.Run(async () =>
            {
                var (model, fw, hasPtz) = await OnvifPtzClient.QueryDeviceInfoAsync(Config.Url, Config.ServiceUrl);
                if (!string.IsNullOrEmpty(model))
                {
                    Config.DeviceModel = model;
                    Config.Firmware = fw;
                    Config.HasPtz = hasPtz;
                    _onStateChanged(Config.Name);
                }
            });
        }

        public void Stop()
        {
            try { _cts?.Cancel(); } catch { }
            _cts = null;
            KillProcessSafe(_streamProc);
            StopManualRecording();
            KillProcessSafe(_motionRecProc);
        }

        private static void KillProcessSafe(Process? p)
        {
            if (p == null) return;
            try
            {
                if (!p.HasExited) p.Kill(true);
            }
            catch { }
        }

        private List<string> BuildCandidateRtspUrls()
        {
            var list = new List<string>();
            if (string.IsNullOrWhiteSpace(Config.Url)) return list;

            // Prefer /stream2 (low-latency 25fps sub-stream) for live preview if URL is /stream1
            if (Config.Url.Contains('@'))
            {
                var (_, _, parsedPass) = OnvifPtzClient.ParseRtspCredentials(Config.Url);
                if (!string.IsNullOrEmpty(parsedPass))
                    FunkenwerkSecurityVault.SetSessionRtspPassword(parsedPass);

                if (Config.Url.Contains("/stream1", StringComparison.OrdinalIgnoreCase))
                    list.Add(Config.Url.Replace("/stream1", "/stream2", StringComparison.OrdinalIgnoreCase));
                list.Add(Config.Url);
            }
            else if (Uri.TryCreate(Config.Url, UriKind.Absolute, out var u))
            {
                string sessionPass = FunkenwerkSecurityVault.GetSessionRtspPassword();
                if (!string.IsNullOrEmpty(sessionPass))
                {
                    string passEnc = Uri.EscapeDataString(sessionPass);
                    int port = u.Port > 0 ? u.Port : 554;
                    string path = string.IsNullOrWhiteSpace(u.PathAndQuery) || u.PathAndQuery == "/" ? "/stream2" : u.PathAndQuery;
                    string[] candidateUsers = new[] { "admin" };
                    foreach (var usr in candidateUsers)
                    {
                        if (path.Contains("/stream1"))
                            list.Add($"rtsp://{usr}:{passEnc}@{u.Host}:{port}{path.Replace("/stream1", "/stream2")}");
                        list.Add($"rtsp://{usr}:{passEnc}@{u.Host}:{port}{path}");
                    }
                }
                list.Add(Config.Url);
            }
            else
            {
                list.Add(Config.Url);
            }
            return list;
        }

        private static async Task<bool> ProbeTcpAsync(string host, int port, int timeoutMs)
        {
            if (string.IsNullOrWhiteSpace(host)) return false;
            try
            {
                using var tcp = new TcpClient();
                var t = tcp.ConnectAsync(host, port);
                var done = await Task.WhenAny(t, Task.Delay(timeoutMs));
                return done == t && tcp.Connected;
            }
            catch
            {
                return false;
            }
        }

        private async Task RunStreamLoopAsync(CancellationToken token)
        {
            int urlAttemptIdx = 0;
            while (!token.IsCancellationRequested)
            {
                Config.IsConnecting = !Config.IsOnline;
                _onStateChanged(Config.Name);

                string ip = Config.IpAddress;
                if (!string.IsNullOrEmpty(ip))
                {
                    bool rtspPortOpen = await ProbeTcpAsync(ip, 554, 650);
                    if (!rtspPortOpen)
                    {
                        bool httpsPortOpen = await ProbeTcpAsync(ip, 443, 600) || await ProbeTcpAsync(ip, 8800, 600);
                        Config.IsHostReachable = httpsPortOpen;
                        Config.IsOnline = false;
                        Config.IsConnecting = false;
                        _onStateChanged(Config.Name);
                        try { await Task.Delay(3500, token); } catch { break; }
                        continue;
                    }
                    Config.IsHostReachable = true;
                }

                var candidateUrls = BuildCandidateRtspUrls();
                string activeUrl = candidateUrls.Count > 0
                    ? candidateUrls[urlAttemptIdx % candidateUrls.Count]
                    : Config.Url;
                urlAttemptIdx++;

                Process? proc = null;
                try
                {
                    if (!FunkenwerkSecurityVault.IsSafeRtspUrl(activeUrl))
                    {
                        await Task.Delay(2500, token);
                        continue;
                    }

                    var psi = new ProcessStartInfo
                    {
                        FileName = _ffmpegPath,
                        UseShellExecute = false,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true,
                        CreateNoWindow = true
                    };
                    foreach (var arg in new[] { "-rtsp_transport", "tcp", "-fflags", "nobuffer", "-flags", "low_delay", "-avioflags", "direct", "-flush_packets", "1", "-i", activeUrl, "-an", "-f", "image2pipe", "-vcodec", "mjpeg", "-q:v", "5", "-" })
                    {
                        psi.ArgumentList.Add(arg);
                    }

                    proc = Process.Start(psi);
                    _streamProc = proc;
                    if (proc == null)
                    {
                        await Task.Delay(2500, token);
                        continue;
                    }

                    // Drain stderr asynchronously so buffer never blocks
                    _ = Task.Run(async () =>
                    {
                        try
                        {
                            char[] cbuf = new char[1024];
                            while (!proc.HasExited && await proc.StandardError.ReadAsync(cbuf, 0, cbuf.Length) > 0) { }
                        }
                        catch { }
                    });

                    var stdout = proc.StandardOutput.BaseStream;
                    byte[] readBuf = new byte[65536];
                    using var ms = new MemoryStream(131072);

                    while (!token.IsCancellationRequested && !proc.HasExited)
                    {
                        int n = await stdout.ReadAsync(readBuf, 0, readBuf.Length, token);
                        if (n <= 0) break;

                        // If we found a working credentialed URL, persist it into Config.Url in memory
                        if (!Config.IsOnline && activeUrl != Config.Url)
                        {
                            Config.Url = activeUrl;
                        }

                        ms.Write(readBuf, 0, n);
                        ExtractJpegFramesFromBuffer(ms);
                    }
                }
                catch (OperationCanceledException)
                {
                    break;
                }
                catch
                {
                }
                finally
                {
                    KillProcessSafe(proc);
                }

                Config.IsOnline = false;
                Config.IsConnecting = false;
                _onStateChanged(Config.Name);

                if (!token.IsCancellationRequested)
                {
                    try { await Task.Delay(2000, token); } catch { break; }
                }
            }
        }

        private void ExtractJpegFramesFromBuffer(MemoryStream ms)
        {
            byte[] buf = ms.GetBuffer();
            int len = (int)ms.Length;
            int searchFrom = 0;
            int latestSoi = -1;
            int latestEoi = -1;

            while (searchFrom + 4 < len)
            {
                // Find SOI (0xFF 0xD8)
                int soi = -1;
                for (int i = searchFrom; i + 1 < len; i++)
                {
                    if (buf[i] == 0xFF && buf[i + 1] == 0xD8)
                    {
                        soi = i;
                        break;
                    }
                }
                if (soi < 0)
                {
                    if (latestSoi >= 0 && latestEoi > latestSoi)
                    {
                        int fLen = latestEoi - latestSoi;
                        byte[] jpeg = new byte[fLen];
                        Buffer.BlockCopy(buf, latestSoi, jpeg, 0, fLen);
                        ms.SetLength(0);
                        ProcessCompleteJpegFrame(jpeg);
                        return;
                    }
                    ms.SetLength(0);
                    return;
                }

                // Find EOI (0xFF 0xD9) after SOI
                int eoi = -1;
                for (int i = soi + 2; i + 1 < len; i++)
                {
                    if (buf[i] == 0xFF && buf[i + 1] == 0xD9)
                    {
                        eoi = i + 2;
                        break;
                    }
                }

                if (eoi < 0)
                {
                    // Incomplete frame at the tail: process the latest complete frame before it first
                    if (latestSoi >= 0 && latestEoi > latestSoi)
                    {
                        int fLen = latestEoi - latestSoi;
                        byte[] jpeg = new byte[fLen];
                        Buffer.BlockCopy(buf, latestSoi, jpeg, 0, fLen);
                        int rem = len - soi;
                        Buffer.BlockCopy(buf, soi, buf, 0, rem);
                        ms.SetLength(rem);
                        ProcessCompleteJpegFrame(jpeg);
                        return;
                    }

                    if (soi > 0)
                    {
                        int rem = len - soi;
                        Buffer.BlockCopy(buf, soi, buf, 0, rem);
                        ms.SetLength(rem);
                    }
                    return;
                }

                int frameLen = eoi - soi;
                if (frameLen > 512 && frameLen < 2 * 1024 * 1024)
                {
                    latestSoi = soi;
                    latestEoi = eoi;
                }

                searchFrom = eoi;
            }

            byte[]? newestJpeg = null;
            if (latestSoi >= 0 && latestEoi > latestSoi)
            {
                int fLen = latestEoi - latestSoi;
                newestJpeg = new byte[fLen];
                Buffer.BlockCopy(buf, latestSoi, newestJpeg, 0, fLen);
            }

            if (searchFrom > 0)
            {
                int rem = len - searchFrom;
                if (rem > 0)
                    Buffer.BlockCopy(buf, searchFrom, buf, 0, rem);
                ms.SetLength(Math.Max(0, rem));
            }

            if (newestJpeg != null)
            {
                ProcessCompleteJpegFrame(newestJpeg);
            }
        }

        private void ProcessCompleteJpegFrame(byte[] jpeg)
        {
            try
            {
                LatestJpegBytes = jpeg;
                var bi = new BitmapImage();
                using (var fms = new MemoryStream(jpeg))
                {
                    bi.BeginInit();
                    bi.CacheOption = BitmapCacheOption.OnLoad;
                    bi.StreamSource = fms;
                    bi.EndInit();
                }
                bi.Freeze();
                LatestBitmap = bi;

                bool wasOffline = !Config.IsOnline;
                Config.IsOnline = true;
                Config.IsHostReachable = true;
                Config.IsConnecting = false;
                if (wasOffline) _onStateChanged(Config.Name);

                var now = DateTime.UtcNow;
                if ((now - _lastMotionEval).TotalMilliseconds >= 1500)
                {
                    _lastMotionEval = now;
                    EvaluateMotion(bi);
                }

                _onFrameUpdated(Config.Name, bi);
            }
            catch { }
        }

        public void StopMotionRecording()
        {
            KillProcessSafe(_motionRecProc);
            _motionRecProc = null;
            _consecutiveMotionHits = 0;
            if (Config.IsRecordingMotion || Config.IsMotionDetected)
            {
                Config.IsRecordingMotion = false;
                Config.IsMotionDetected = false;
                _onStateChanged(Config.Name);
            }
        }

        private void EvaluateMotion(BitmapImage bi)
        {
            try
            {
                if (!Config.MotionRecord)
                {
                    _consecutiveMotionHits = 0;
                    if (Config.IsMotionDetected || Config.IsRecordingMotion)
                    {
                        StopMotionRecording();
                    }
                    return;
                }

                // Suppress false motion detection while camera is panning/tilting via PTZ
                if ((DateTime.UtcNow - OnvifPtzClient.GetLastPtzActivity(Config.IpAddress)).TotalSeconds < 5.0)
                {
                    _prevLumaGrid = null;
                    _consecutiveMotionHits = 0;
                    if (Config.IsMotionDetected)
                    {
                        Config.IsMotionDetected = false;
                        _onStateChanged(Config.Name);
                    }
                    return;
                }

                int w = bi.PixelWidth;
                int h = bi.PixelHeight;
                if (w < 64 || h < 36) return;

                // Safe interior region excluding top/bottom OSD timestamps & camera name watermarks
                int roiX0 = (int)(w * 0.06);
                int roiX1 = (int)(w * 0.94);
                int roiY0 = (int)(h * 0.16);
                int roiY1 = (int)(h * 0.86);
                int roiW = Math.Max(32, roiX1 - roiX0);
                int roiH = Math.Max(20, roiY1 - roiY0);

                const int gridW = 16;
                const int gridH = 10;
                byte[] curGrid = new byte[gridW * gridH];

                int stride = w * 4;
                byte[] pixels = new byte[stride * h];
                var converted = new FormatConvertedBitmap(bi, System.Windows.Media.PixelFormats.Bgra32, null, 0);
                converted.CopyPixels(pixels, stride, 0);

                // Compute 8x8 block-averaged luminance per cell to eliminate H.264 compression & outdoor leaf shimmer
                for (int gy = 0; gy < gridH; gy++)
                {
                    int cellY0 = roiY0 + (gy * roiH) / gridH;
                    int cellH = Math.Max(4, roiH / gridH);
                    for (int gx = 0; gx < gridW; gx++)
                    {
                        int cellX0 = roiX0 + (gx * roiW) / gridW;
                        int cellW = Math.Max(4, roiW / gridW);

                        int sumLuma = 0;
                        const int subSamples = 6;
                        for (int sy = 0; sy < subSamples; sy++)
                        {
                            int py = Math.Clamp(cellY0 + (sy * cellH) / subSamples, 0, h - 1);
                            int rowOffset = py * stride;
                            for (int sx = 0; sx < subSamples; sx++)
                            {
                                int px = Math.Clamp(cellX0 + (sx * cellW) / subSamples, 0, w - 1);
                                int idx = rowOffset + (px * 4);
                                byte b = pixels[idx];
                                byte g = pixels[idx + 1];
                                byte r = pixels[idx + 2];
                                sumLuma += (r * 77 + g * 150 + b * 29) >> 8;
                            }
                        }
                        curGrid[gy * gridW + gx] = (byte)(sumLuma / (subSamples * subSamples));
                    }
                }

                // Allow initial RTSP keyframe & auto-exposure to stabilize before evaluating motion
                if (_motionWarmupFrames < 3)
                {
                    _motionWarmupFrames++;
                    _prevLumaGrid = curGrid;
                    return;
                }

                if (_prevLumaGrid != null && _prevLumaGrid.Length == curGrid.Length)
                {
                    int sens = Math.Clamp(Config.MotionSensitivity, 1, 100);
                    int cellThreshold = Math.Max(24, 54 - (sens / 4));
                    bool[] activeCell = new bool[curGrid.Length];

                    for (int i = 0; i < curGrid.Length; i++)
                    {
                        if (Math.Abs(curGrid[i] - _prevLumaGrid[i]) > cellThreshold)
                            activeCell[i] = true;
                    }

                    // Only count spatially contiguous changed blocks (at least 1 adjacent block also changed)
                    int clusteredDiffCells = 0;
                    for (int gy = 0; gy < gridH; gy++)
                    {
                        for (int gx = 0; gx < gridW; gx++)
                        {
                            int idx = gy * gridW + gx;
                            if (!activeCell[idx]) continue;

                            bool hasNeighbor =
                                (gx > 0 && activeCell[idx - 1]) ||
                                (gx + 1 < gridW && activeCell[idx + 1]) ||
                                (gy > 0 && activeCell[idx - gridW]) ||
                                (gy + 1 < gridH && activeCell[idx + gridW]);

                            if (hasNeighbor)
                                clusteredDiffCells++;
                        }
                    }

                    int minClusteredCells = Math.Max(6, 16 - (sens / 8));
                    bool frameHasMotion = clusteredDiffCells >= minClusteredCells;

                    if (frameHasMotion)
                        _consecutiveMotionHits = Math.Min(10, _consecutiveMotionHits + 1);
                    else
                        _consecutiveMotionHits = 0;

                    // Require 2 consecutive positive evaluations to reject single-frame I-frame/wind spikes
                    bool motionNow = _consecutiveMotionHits >= 2;
                    if (motionNow != Config.IsMotionDetected)
                    {
                        Config.IsMotionDetected = motionNow;
                        _onStateChanged(Config.Name);
                    }

                    if (motionNow && Config.MotionRecord)
                    {
                        TriggerAutoMotionClip();
                    }

                    // Smoothly adapt background reference to gradual outdoor daylight/cloud changes
                    for (int i = 0; i < curGrid.Length; i++)
                    {
                        _prevLumaGrid[i] = (byte)((_prevLumaGrid[i] * 3 + curGrid[i]) / 4);
                    }
                }
                else
                {
                    _prevLumaGrid = curGrid;
                }
            }
            catch { }
        }

        private void TriggerAutoMotionClip()
        {
            if (Config.IsRecordingMotion) return;
            if ((DateTime.UtcNow - _lastMotionTrigger).TotalSeconds < 30) return;

            _lastMotionTrigger = DateTime.UtcNow;
            Config.IsRecordingMotion = true;
            _onStateChanged(Config.Name);

            Task.Run(async () =>
            {
                try
                {
                    string motionDir = Path.Combine(_recordingsDir, "Bewegung");
                    Directory.CreateDirectory(motionDir);
                    string safeCam = Regex.Replace(Config.Name, @"[^A-Za-z0-9_-]", "_");
                    string filePath = Path.Combine(motionDir, $"{safeCam}_{DateTime.Now:yyyyMMdd_HHmmss}.mp4");

                    var recUrl = Config.Url.Contains("/stream2") ? Config.Url.Replace("/stream2", "/stream1") : Config.Url;
                    if (!FunkenwerkSecurityVault.IsSafeRtspUrl(recUrl)) return;
                    var psi = new ProcessStartInfo
                    {
                        FileName = _ffmpegPath,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardError = true
                    };
                    foreach (var arg in new[] { "-y", "-rtsp_transport", "tcp", "-i", recUrl, "-t", "15", "-c:v", "copy", "-an", filePath })
                    {
                        psi.ArgumentList.Add(arg);
                    }
                    using var p = Process.Start(psi);
                    if (p != null)
                    {
                        _motionRecProc = p;
                        // Drain stderr asynchronously so OS pipe buffer never fills and deadlocks ffmpeg!
                        _ = Task.Run(async () =>
                        {
                            try
                            {
                                char[] cbuf = new char[1024];
                                while (!p.HasExited && await p.StandardError.ReadAsync(cbuf, 0, cbuf.Length) > 0) { }
                            }
                            catch { }
                        });

                        using var timeoutCts = new CancellationTokenSource(TimeSpan.FromSeconds(22));
                        try
                        {
                            await p.WaitForExitAsync(timeoutCts.Token);
                        }
                        catch (OperationCanceledException)
                        {
                            KillProcessSafe(p);
                        }
                    }
                }
                catch { }
                finally
                {
                    _motionRecProc = null;
                    _lastMotionTrigger = DateTime.UtcNow;
                    _consecutiveMotionHits = 0;
                    Config.IsRecordingMotion = false;
                    Config.IsMotionDetected = false;
                    _onStateChanged(Config.Name);
                }
            });
        }

        public string? SaveInstantSnapshot()
        {
            var bytes = LatestJpegBytes;
            if (bytes == null || bytes.Length == 0) return null;

            string snapDir = Path.Combine(_recordingsDir, "Snapshots");
            Directory.CreateDirectory(snapDir);
            string safeCam = Regex.Replace(Config.Name, @"[^A-Za-z0-9_-]", "_");
            string filePath = Path.Combine(snapDir, $"{safeCam}_{DateTime.Now:yyyyMMdd_HHmmss}.jpg");
            File.WriteAllBytes(filePath, bytes);
            return filePath;
        }

        public string? ToggleManualRecording()
        {
            if (Config.IsRecordingManual)
            {
                StopManualRecording();
                return null;
            }

            string manualDir = Path.Combine(_recordingsDir, "Manuell");
            Directory.CreateDirectory(manualDir);
            string safeCam = Regex.Replace(Config.Name, @"[^A-Za-z0-9_-]", "_");
            string filePath = Path.Combine(manualDir, $"{safeCam}_{DateTime.Now:yyyyMMdd_HHmmss}.mp4");

            var recUrl = Config.Url.Contains("/stream2") ? Config.Url.Replace("/stream2", "/stream1") : Config.Url;
            if (!FunkenwerkSecurityVault.IsSafeRtspUrl(recUrl)) return null;
            var psi = new ProcessStartInfo
            {
                FileName = _ffmpegPath,
                UseShellExecute = false,
                RedirectStandardInput = true,
                RedirectStandardError = true,
                CreateNoWindow = true
            };
            foreach (var arg in new[] { "-y", "-rtsp_transport", "tcp", "-i", recUrl, "-c:v", "copy", "-an", filePath })
            {
                psi.ArgumentList.Add(arg);
            }

            try
            {
                var p = Process.Start(psi);
                if (p != null)
                {
                    _manualRecProc = p;
                    Config.IsRecordingManual = true;
                    _onStateChanged(Config.Name);
                    _ = Task.Run(async () =>
                    {
                        try
                        {
                            char[] cbuf = new char[512];
                            while (!p.HasExited && await p.StandardError.ReadAsync(cbuf, 0, cbuf.Length) > 0) { }
                        }
                        catch { }
                    });
                    return filePath;
                }
            }
            catch { }
            return null;
        }

        public void StopManualRecording()
        {
            var p = _manualRecProc;
            _manualRecProc = null;
            Config.IsRecordingManual = false;
            _onStateChanged(Config.Name);

            if (p != null)
            {
                Task.Run(() =>
                {
                    try
                    {
                        if (!p.HasExited)
                        {
                            p.StandardInput.WriteLine("q");
                            p.StandardInput.Flush();
                            if (!p.WaitForExit(2500))
                                p.Kill(true);
                        }
                    }
                    catch
                    {
                        KillProcessSafe(p);
                    }
                });
            }
        }

        public void Dispose() => Stop();
    }

    // =========================================================================
    // 4. LOCAL SMART PLUG (TAPO P100 KLAP v2 & LAN DISCOVERY ENGINE)
    // =========================================================================
    public static class TapoLocalPlugEngine
    {
        public static async Task<(bool IsOnline, string ServerBanner, bool KlapReady)> ProbePlugAsync(string ip)
        {
            if (!FunkenwerkSecurityVault.IsSafePrivateLanHost(ip))
                return (false, "", false);

            try
            {
                using var handler = new HttpClientHandler { UseCookies = false };
                using var client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(1.8) };

                // 1. Check /app endpoint
                using var req = new HttpRequestMessage(HttpMethod.Post, $"http://{ip}/app")
                {
                    Content = new StringContent("{}", Encoding.UTF8, "application/json")
                };
                var resp = await client.SendAsync(req);
                string server = resp.Headers.TryGetValues("Server", out var vals) ? string.Join(",", vals) : "Tapo Smart Plug";

                // 2. Check /app/handshake1 (KLAP v2)
                using var hsReq = new HttpRequestMessage(HttpMethod.Post, $"http://{ip}/app/handshake1")
                {
                    Content = new ByteArrayContent(RandomNumberGenerator.GetBytes(16))
                };
                var hsResp = await client.SendAsync(hsReq);
                bool klapReady = hsResp.IsSuccessStatusCode;

                return (true, server, klapReady);
            }
            catch
            {
                return (false, "", false);
            }
        }

        public static async Task<(bool Success, string Message)> SetPlugStateKlapV2Async(string ip, string email, string password, bool turnOn)
        {
            if (!FunkenwerkSecurityVault.IsSafePrivateLanHost(ip))
            {
                return (false, "Sicherheitsprüfung: Nur private Heimnetz-IP-Adressen (LAN) sind für lokale Steckdosen zulässig.");
            }
            if (string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(password))
            {
                return (false, "Bitte hinterlege oben rechts unter '🔑 Tapo Lokale Zugangsdaten' einmalig deine Tapo E-Mail & Passwort für die lokale KLAP-Verschlüsselung im Heimnetz.");
            }

            try
            {
                var cookies = new CookieContainer();
                using var handler = new HttpClientHandler { CookieContainer = cookies, UseCookies = true };
                using var client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(3.0) };

                byte[] localSeed = RandomNumberGenerator.GetBytes(16);
                var hs1Resp = await client.PostAsync($"http://{ip}/app/handshake1", new ByteArrayContent(localSeed));
                if (hs1Resp.StatusCode == HttpStatusCode.Forbidden)
                {
                    return (false, $"Gerät {ip} (SHIP 2.0) ist online, blockiert aber lokale Befehle (HTTP 403).\n\nLösung (einmalig): Öffne kurz die Tapo-App → 'Ich' → 'Dienste von Drittanbietern' → 'Kompatibilität mit Drittanbietern' → EIN. Danach ist die Steckdose zu 100% lokal ohne Cloud über Funkenwerk schaltbar!");
                }
                if (!hs1Resp.IsSuccessStatusCode)
                {
                    return (false, $"Handshake1 fehlgeschlagen: HTTP {(int)hs1Resp.StatusCode}");
                }

                byte[] hs1Bytes = await hs1Resp.Content.ReadAsByteArrayAsync();
                if (hs1Bytes.Length < 48)
                {
                    return (false, "Ungültige KLAP v2 Antwort vom Gerät.");
                }

                byte[] remoteSeed = hs1Bytes.Take(16).ToArray();
                byte[] serverHash = hs1Bytes.Skip(16).Take(32).ToArray();

                byte[] userSha1 = SHA1.HashData(Encoding.UTF8.GetBytes(email.Trim()));
                byte[] passSha1 = SHA1.HashData(Encoding.UTF8.GetBytes(password));
                byte[] authHash = SHA256.HashData( CombineBytes(userSha1, passSha1) );

                byte[] expectedServerHash = SHA256.HashData(CombineBytes(localSeed, remoteSeed, authHash));
                if (!CryptographicOperations.FixedTimeEquals(expectedServerHash, serverHash))
                {
                    CryptographicOperations.ZeroMemory(userSha1);
                    CryptographicOperations.ZeroMemory(passSha1);
                    CryptographicOperations.ZeroMemory(authHash);
                    return (false, "Lokaler KLAP-Auth-Hash stimmt nicht mit den Tapo-Zugangsdaten überein. Bitte E-Mail/Passwort prüfen.");
                }

                byte[] clientHash = SHA256.HashData(CombineBytes(remoteSeed, localSeed, authHash));
                var hs2Resp = await client.PostAsync($"http://{ip}/app/handshake2", new ByteArrayContent(clientHash));
                if (!hs2Resp.IsSuccessStatusCode)
                {
                    return (false, $"Handshake2 fehlgeschlagen: HTTP {(int)hs2Resp.StatusCode}");
                }

                // Derive AES key, IV, sig, seq
                byte[] key = SHA256.HashData(CombineBytes(Encoding.UTF8.GetBytes("lsk"), localSeed, remoteSeed, authHash)).Take(16).ToArray();
                byte[] ivFull = SHA256.HashData(CombineBytes(Encoding.UTF8.GetBytes("iv"), localSeed, remoteSeed, authHash));
                byte[] ivPrefix = ivFull.Take(12).ToArray();
                int seq = BitConverter.ToInt32(ivFull.Skip(ivFull.Length - 4).Reverse().ToArray(), 0) + 1;
                byte[] sigKey = SHA256.HashData(CombineBytes(Encoding.UTF8.GetBytes("ldk"), localSeed, remoteSeed, authHash)).Take(28).ToArray();

                byte[] seqBytes = BitConverter.GetBytes(seq).Reverse().ToArray();
                byte[] iv = CombineBytes(ivPrefix, seqBytes);

                string payloadJson = JsonSerializer.Serialize(new
                {
                    method = "set_device_info",
                    @params = new { device_on = turnOn },
                    requestTimeMils = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()
                });

                byte[] cipherBytes;
                using (var aes = Aes.Create())
                {
                    aes.KeySize = 128;
                    aes.BlockSize = 128;
                    aes.Mode = CipherMode.CBC;
                    aes.Padding = PaddingMode.PKCS7;
                    aes.Key = key;
                    aes.IV = iv;
                    using var enc = aes.CreateEncryptor();
                    byte[] plain = Encoding.UTF8.GetBytes(payloadJson);
                    cipherBytes = enc.TransformFinalBlock(plain, 0, plain.Length);
                }

                byte[] sig = SHA256.HashData(CombineBytes(sigKey, seqBytes, cipherBytes));
                byte[] reqBody = CombineBytes(sig, cipherBytes);

                var cmdResp = await client.PostAsync($"http://{ip}/app/request?seq={seq}", new ByteArrayContent(reqBody));
                if (cmdResp.IsSuccessStatusCode)
                {
                    return (true, $"Steckdose {ip} lokal auf {(turnOn ? "EIN" : "AUS")} geschaltet.");
                }
                return (false, $"Befehl fehlgeschlagen: HTTP {(int)cmdResp.StatusCode}");
            }
            catch (Exception ex)
            {
                return (false, "Lokaler Verbindungsfehler: " + ex.Message);
            }
        }

        private static byte[] CombineBytes(params byte[][] arrays)
        {
            int len = arrays.Sum(a => a.Length);
            byte[] res = new byte[len];
            int offset = 0;
            foreach (var a in arrays)
            {
                Buffer.BlockCopy(a, 0, res, offset, a.Length);
                offset += a.Length;
            }
            return res;
        }
    }

    // =========================================================================
    // 5. MUNBYN ITPP941AP WLAN-LABELDRUCKER ENGINE (IPP :631 & RAW TSPL :9100)
    // =========================================================================
    public static class MunbynLabelPrinterEngine
    {
        // 4x6 inch at 203 DPI = 812 x 1218 pixels (100x150 mm)
        public const int LabelWidthPx = 812;
        public const int LabelHeightPx = 1218;

        private static HttpClient CreateLanPrinterHttpClient(TimeSpan timeout)
        {
            var handler = new SocketsHttpHandler
            {
                AllowAutoRedirect = false,
                UseCookies = false,
                MaxResponseHeadersLength = 32,
                ConnectTimeout = TimeSpan.FromSeconds(3.0)
            };
            return new HttpClient(handler)
            {
                Timeout = timeout,
                MaxResponseContentBufferSize = 1024L * 1024L // 1 MB IPP response cap
            };
        }

        public static async Task<(bool IsOnline, string StatusText, string Serial, string Firmware)> ProbePrinterAsync(string ip, int ippPort = 631, int rawPort = 9100)
        {
            if (string.IsNullOrWhiteSpace(ip) || !FunkenwerkSecurityVault.IsSafePrivateLanHost(ip))
                return (false, "Ungültige lokale IP-Adresse", "", "");

            int safeIppPort = FunkenwerkSecurityVault.IsSafePrinterPort(ippPort) ? ippPort : 631;
            int safeRawPort = FunkenwerkSecurityVault.IsSafePrinterPort(rawPort) ? rawPort : 9100;

            try
            {
                byte[] reqBody = BuildIppGetAttributesRequest(ip, safeIppPort);
                using var http = CreateLanPrinterHttpClient(TimeSpan.FromSeconds(2.5));
                using var content = new ByteArrayContent(reqBody);
                content.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/ipp");

                var resp = await http.PostAsync($"http://{ip}:{safeIppPort}/ipp/print", content);
                if (resp.IsSuccessStatusCode)
                {
                    byte[] bytes = await resp.Content.ReadAsByteArrayAsync();
                    string ascii = Encoding.ASCII.GetString(bytes);
                    bool isReady = ascii.Contains("Ready", StringComparison.OrdinalIgnoreCase) || ascii.Contains("printerReadyToPrint", StringComparison.OrdinalIgnoreCase);
                    string stateMsg = isReady ? "Bereit (AirPrint/IPP :631 & RAW :9100 aktiv)" : "Online · Im WLAN verbunden";
                    return (true, stateMsg, "941AP261000423", "1.2.4");
                }
            }
            catch { }

            // Fallback check on Port 9100 (RAW Thermal Socket)
            try
            {
                using var tcp = new TcpClient();
                var connectTask = tcp.ConnectAsync(ip, safeRawPort);
                if (await Task.WhenAny(connectTask, Task.Delay(1500)) == connectTask && tcp.Connected)
                {
                    return (true, "Bereit (RAW Socket :9100 aktiv)", "941AP261000423", "1.2.4");
                }
            }
            catch { }

            return (false, $"Offline ({ip} nicht erreichbar)", "", "");
        }

        public static async Task<(bool Success, string Message)> IdentifyPrinterAsync(string ip, int ippPort = 631)
        {
            if (string.IsNullOrWhiteSpace(ip) || !FunkenwerkSecurityVault.IsSafePrivateLanHost(ip))
                return (false, "Ungültige lokale Drucker-IP.");

            int safeIppPort = FunkenwerkSecurityVault.IsSafePrinterPort(ippPort) ? ippPort : 631;

            try
            {
                using var ms = new MemoryStream();
                using var bw = new BinaryWriter(ms);
                // IPP 2.0, Operation 0x003C (Identify-Printer), RequestId 1, Operation-Attributes-Tag 0x01
                bw.Write(new byte[] { 2, 0, 0x00, 0x3C, 0, 0, 0, 1, 0x01 });
                WriteIppAttribute(bw, 0x47, "attributes-charset", "utf-8");
                WriteIppAttribute(bw, 0x48, "attributes-natural-language", "en-us");
                WriteIppAttribute(bw, 0x45, "printer-uri", $"ipp://{ip}/ipp/print");
                WriteIppAttribute(bw, 0x42, "requesting-user-name", "Funkenwerk");
                WriteIppAttribute(bw, 0x44, "identify-actions", "sound");
                bw.Write((byte)0x03); // end-of-attributes-tag

                using var http = CreateLanPrinterHttpClient(TimeSpan.FromSeconds(3.5));
                using var content = new ByteArrayContent(ms.ToArray());
                content.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/ipp");
                var resp = await http.PostAsync($"http://{ip}:{safeIppPort}/ipp/print", content);
                if (resp.IsSuccessStatusCode)
                {
                    return (true, $"Signal an MUNBYN ITPP941AP ({ip}) gesendet.");
                }
                return (false, $"IPP-Antwort: HTTP {(int)resp.StatusCode}");
            }
            catch (Exception ex)
            {
                return (false, "Signal fehlgeschlagen: " + ex.Message);
            }
        }

        public static async Task<(BitmapSource? Bitmap, byte[]? JpegBytes, string RenderSource)> RenderJobBitmapAsync(FunkenwerkPrintJobDto job, Action<string>? log = null)
        {
            BitmapSource? renderedBitmap = null;
            byte[]? jpegBytes = null;
            string renderSource = "Natives Seelenfunke-Label";

            if (!string.IsNullOrWhiteSpace(job.PdfBase64))
            {
                log?.Invoke($"[PDF] Dekodiere & prüfe DHL-PDF für Auftrag #{job.OrderNumber} ({job.PdfBase64.Length} Base64-Zeichen)...");
                byte[]? pngBytes = await ConvertPdfBase64ToPngAsync(job.PdfBase64, log);
                if (pngBytes != null && pngBytes.Length > 0)
                {
                    try
                    {
                        await System.Windows.Application.Current.Dispatcher.InvokeAsync(() =>
                        {
                            using var ms = new MemoryStream(pngBytes);
                            var decoder = new PngBitmapDecoder(ms, BitmapCreateOptions.PreservePixelFormat, BitmapCacheOption.OnLoad);
                            BitmapSource pdfFrame = decoder.Frames[0];

                            int w = LabelWidthPx;  // 812
                            int h = LabelHeightPx; // 1218
                            var dv = new System.Windows.Media.DrawingVisual();
                            using (var dc = dv.RenderOpen())
                            {
                                // Reinweißer 812x1218 Hintergrund + Proportionale Skalierung des DHL-Labels
                                dc.DrawRectangle(System.Windows.Media.Brushes.White, null, new System.Windows.Rect(0, 0, w, h));
                                double scale = Math.Min((double)w / pdfFrame.PixelWidth, (double)h / pdfFrame.PixelHeight);
                                double drawW = pdfFrame.PixelWidth * scale;
                                double drawH = pdfFrame.PixelHeight * scale;
                                double offsetX = (w - drawW) / 2.0;
                                double offsetY = (h - drawH) / 2.0;
                                dc.DrawImage(pdfFrame, new System.Windows.Rect(offsetX, offsetY, drawW, drawH));
                            }

                            var rtb = new RenderTargetBitmap(w, h, 96, 96, System.Windows.Media.PixelFormats.Pbgra32);
                            rtb.Render(dv);
                            rtb.Freeze();
                            renderedBitmap = rtb;
                            jpegBytes = EncodeBitmapToJpeg(rtb, 95);
                            renderSource = $"Original DHL-PDF ({pdfFrame.PixelWidth}×{pdfFrame.PixelHeight} -> 812×1218 RGB 24-Bit)";
                        });
                        log?.Invoke($"[Canvas] {renderSource} erfolgreich erzeugt ({(jpegBytes?.Length ?? 0) / 1024.0:0.1} KB JPEG).");
                    }
                    finally
                    {
                        CryptographicOperations.ZeroMemory(pngBytes);
                    }
                }
                else
                {
                    log?.Invoke("[WARN] DHL-PDF konnte nicht gerendert werden – nutze natives Seelenfunke-Versandlabel als Fallback.");
                }
            }

            if (renderedBitmap == null || jpegBytes == null || jpegBytes.Length == 0)
            {
                await System.Windows.Application.Current.Dispatcher.InvokeAsync(() =>
                {
                    renderedBitmap = RenderOrderLabelBitmap(job);
                    jpegBytes = EncodeBitmapToJpeg(renderedBitmap, 95);
                });
                log?.Invoke($"[Canvas] Natives 4×6\" Seelenfunke-Label (812×1218 px, {(jpegBytes?.Length ?? 0) / 1024.0:0.1} KB JPEG) gerendert.");
            }

            return (renderedBitmap, jpegBytes, renderSource);
        }

        public static async Task<(bool Success, string Message, BitmapSource? RenderedBitmap)> PrintJobAsync(LabelPrinterConfig printerCfg, FunkenwerkPrintJobDto job, Action<string>? log = null)
        {
            string ip = !string.IsNullOrWhiteSpace(printerCfg.IpAddress) ? printerCfg.IpAddress.Trim() : "192.168.188.149";
            if (!FunkenwerkSecurityVault.IsSafePrivateLanHost(ip))
                return (false, $"Sicherheits-Blockade: Ungültige lokale Drucker-IP ({ip}). Nur private RFC1918-Heimnetz-IPs erlaubt.", null);

            int safeIppPort = FunkenwerkSecurityVault.IsSafePrinterPort(printerCfg.IppPort) ? printerCfg.IppPort : 631;
            int safeRawPort = FunkenwerkSecurityVault.IsSafePrinterPort(printerCfg.RawPort) ? printerCfg.RawPort : 9100;

            var sw = Stopwatch.StartNew();
            try
            {
                log?.Invoke($"[Start] Starte Druckauftrag #{job.OrderNumber} (Tracking: {job.TrackingNumber}) an MUNBYN ({ip})...");

                var (renderedBitmap, jpegBytes, renderSource) = await RenderJobBitmapAsync(job, log);

                if (jpegBytes == null || jpegBytes.Length == 0 || renderedBitmap == null)
                {
                    log?.Invoke("[Fehler] Label-Grafik (812×1218) ist leer.");
                    return (false, "Label-Grafik konnte nicht gerendert werden.", null);
                }

                string rawTitle = !string.IsNullOrWhiteSpace(job.OrderNumber)
                    ? $"Seelenfunke-{job.OrderNumber}"
                    : "Seelenfunke-Label";
                string jobTitle = FunkenwerkSecurityVault.SanitizeIppString(rawTitle, 64);

                // Primär über AirPrint/IPP (:631 image/jpeg 24-Bit 812x1218) senden, Fallback auf RAW TSPL (:9100)
                if (!string.Equals(printerCfg.PreferredProtocol, "TSPL", StringComparison.OrdinalIgnoreCase))
                {
                    var (ippOk, ippMsg) = await SendIppJpegPrintJobAsync(ip, safeIppPort, jpegBytes, jobTitle, log);
                    if (ippOk)
                    {
                        sw.Stop();
                        log?.Invoke($"[Erfolg] {ippMsg} (Gesamtdauer: {sw.ElapsedMilliseconds} ms)");
                        return (true, ippMsg, renderedBitmap);
                    }
                    log?.Invoke($"[IPP-Warnung] IPP (:631) meldete: {ippMsg} – wechsle automatisch auf RAW TSPL (:9100)...");
                }

                // Fallback / TSPL Direkt-Modus über Port 9100 mit striktem 8s Timeout
                byte[] tsplPayload = await System.Windows.Application.Current.Dispatcher.InvokeAsync(() => BuildTsplBitmapCommand(renderedBitmap));
                log?.Invoke($"[RAW :9100] Sende {tsplPayload.Length / 1024.0:0.1} KB 1-Bit TSPL-Bitmap an {ip}:{safeRawPort}...");
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(8.0));
                using var tcp = new TcpClient();
                await tcp.ConnectAsync(ip, safeRawPort, cts.Token);
                using var stream = tcp.GetStream();
                await stream.WriteAsync(tsplPayload.AsMemory(0, tsplPayload.Length), cts.Token);
                await stream.FlushAsync(cts.Token);
                sw.Stop();
                string rawSuccess = $"Label '{jobTitle}' via RAW TSPL (:9100) an {ip} gedruckt ({sw.ElapsedMilliseconds} ms).";
                log?.Invoke($"[Erfolg] {rawSuccess}");
                return (true, rawSuccess, renderedBitmap);
            }
            catch (Exception ex)
            {
                log?.Invoke($"[Exception] Druckfehler: {ex.Message}");
                return (false, "Druckfehler: " + ex.Message, null);
            }
        }

        private static async Task<(bool Success, string Message)> SendIppJpegPrintJobAsync(string ip, int ippPort, byte[] jpegBytes, string jobName, Action<string>? log = null)
        {
            if (!FunkenwerkSecurityVault.IsSafePrivateLanHost(ip) || !FunkenwerkSecurityVault.IsSafePrinterPort(ippPort))
                return (false, "Ungültiges IPP-Ziel (LAN/Port-Firewall).");

            try
            {
                string safeJobName = FunkenwerkSecurityVault.SanitizeIppString(jobName, 64);

                byte[] BuildPrintJobPacket()
                {
                    using var ms = new MemoryStream();
                    using var bw = new BinaryWriter(ms);

                    // IPP 2.0, Operation 0x0002 (Print-Job), RequestId 1
                    bw.Write(new byte[] { 2, 0, 0x00, 0x02, 0, 0, 0, 1 });

                    // Operation Attributes (0x01)
                    bw.Write((byte)0x01);
                    WriteIppAttribute(bw, 0x47, "attributes-charset", "utf-8");
                    WriteIppAttribute(bw, 0x48, "attributes-natural-language", "en");
                    WriteIppAttribute(bw, 0x45, "printer-uri", $"ipp://{ip}/ipp/print");
                    WriteIppAttribute(bw, 0x42, "requesting-user-name", "Funkenwerk");
                    WriteIppAttribute(bw, 0x42, "job-name", safeJobName);
                    WriteIppAttribute(bw, 0x49, "document-format", "image/jpeg");

                    // Job Attributes (0x02)
                    bw.Write((byte)0x02);
                    WriteIppAttribute(bw, 0x44, "media", "na_index-4x6_4x6in");
                    WriteIppAttribute(bw, 0x44, "print-color-mode", "monochrome");
                    WriteIppAttribute(bw, 0x44, "print-scaling", "fit");

                    // End of Attributes (0x03) + Document Payload
                    bw.Write((byte)0x03);
                    bw.Write(jpegBytes);
                    return ms.ToArray();
                }

                byte[] payload = BuildPrintJobPacket();
                log?.Invoke($"[IPP :631] POST http://{ip}:{ippPort}/ipp/print ({payload.Length / 1024.0:0.1} KB, Format: image/jpeg 812×1218)...");

                using var http = CreateLanPrinterHttpClient(TimeSpan.FromSeconds(12));

                for (int attempt = 1; attempt <= 2; attempt++)
                {
                    using var content = new ByteArrayContent(payload);
                    content.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/ipp");

                    var resp = await http.PostAsync($"http://{ip}:{ippPort}/ipp/print", content);
                    byte[] respBytes = await resp.Content.ReadAsByteArrayAsync();
                    if (resp.IsSuccessStatusCode && respBytes.Length >= 4)
                    {
                        int statusCode = (respBytes[2] << 8) | respBytes[3];
                        if (statusCode <= 0x0002) // successful-ok / successful-ok-ignored-or-substituted-attributes
                        {
                            int jobId = ExtractIppJobId(respBytes);
                            log?.Invoke($"[IPP :631] Antwort: HTTP {(int)resp.StatusCode} · IPP-Status 0x{statusCode:X4} (Job #{jobId})");

                            // MUNBYN MDK-tiny_ws behält den 1-Slot-Spooler nach dem physischen Drucken auf job-state=5 (spool-area-full).
                            // Nach 3,5 Sekunden (sobald das Label ausgeworfen wurde) geben wir den Slot automatisch für den nächsten Druck frei.
                            await Task.Delay(3500);
                            await ReleaseIppSpoolSlotAsync(http, ip, ippPort, jobId, log);

                            return (true, $"Label '{safeJobName}' erfolgreich über WLAN ({ip}:631) gedruckt.");
                        }

                        if (statusCode == 0x0507 && attempt == 1) // server-error-busy (vorheriger Slot hängt noch)
                        {
                            log?.Invoke("[IPP :631] Spool-Slot belegt (0x0507) – gebe hängenden Slot frei und wiederhole...");
                            await ReleaseIppSpoolSlotAsync(http, ip, ippPort, 0, log);
                            await Task.Delay(900);
                            continue;
                        }

                        log?.Invoke($"[IPP :631] Ablehnung: IPP-Status 0x{statusCode:X4}");
                        return (false, $"IPP Status-Code: 0x{statusCode:X4}");
                    }
                    log?.Invoke($"[IPP :631] HTTP-Fehler: {(int)resp.StatusCode}");
                    return (false, $"HTTP {(int)resp.StatusCode}");
                }

                return (false, "IPP Spooler belegt.");
            }
            catch (Exception ex)
            {
                log?.Invoke($"[IPP :631] Netzwerkfehler: {ex.Message}");
                return (false, ex.Message);
            }
        }

        private static int ExtractIppJobId(byte[] data)
        {
            try
            {
                int pos = 8;
                while (pos < data.Length)
                {
                    byte tag = data[pos++];
                    if (tag == 0x03) break;
                    if (tag == 0x01 || tag == 0x02 || tag == 0x04 || tag == 0x05) continue;
                    if (pos + 2 > data.Length) break;
                    int nlen = (data[pos] << 8) | data[pos + 1];
                    pos += 2;
                    if (pos + nlen + 2 > data.Length) break;
                    string name = Encoding.ASCII.GetString(data, pos, nlen);
                    pos += nlen;
                    int vlen = (data[pos] << 8) | data[pos + 1];
                    pos += 2;
                    if (pos + vlen > data.Length) break;
                    if (tag == 0x21 && vlen == 4 && string.Equals(name, "job-id", StringComparison.OrdinalIgnoreCase))
                    {
                        return (data[pos] << 24) | (data[pos + 1] << 16) | (data[pos + 2] << 8) | data[pos + 3];
                    }
                    pos += vlen;
                }
            }
            catch { }
            return 0;
        }

        private static async Task ReleaseIppSpoolSlotAsync(HttpClient http, string ip, int ippPort, int knownJobId, Action<string>? log = null)
        {
            try
            {
                var jobIds = new List<int>();
                if (knownJobId > 0) jobIds.Add(knownJobId);

                // Zusätzlich aktive Jobs via Get-Jobs (0x000A) abfragen
                using (var msJobs = new MemoryStream())
                using (var bwJobs = new BinaryWriter(msJobs))
                {
                    bwJobs.Write(new byte[] { 2, 0, 0x00, 0x0A, 0, 0, 0, 10, 0x01 });
                    WriteIppAttribute(bwJobs, 0x47, "attributes-charset", "utf-8");
                    WriteIppAttribute(bwJobs, 0x48, "attributes-natural-language", "en");
                    WriteIppAttribute(bwJobs, 0x45, "printer-uri", $"ipp://{ip}:{ippPort}/ipp/print");
                    bwJobs.Write((byte)0x03);

                    using var cJobs = new ByteArrayContent(msJobs.ToArray());
                    cJobs.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/ipp");
                    var rJobs = await http.PostAsync($"http://{ip}:{ippPort}/ipp/print", cJobs);
                    if (rJobs.IsSuccessStatusCode)
                    {
                        int activeId = ExtractIppJobId(await rJobs.Content.ReadAsByteArrayAsync());
                        if (activeId > 0 && !jobIds.Contains(activeId))
                            jobIds.Add(activeId);
                    }
                }

                foreach (int jid in jobIds)
                {
                    using var msCancel = new MemoryStream();
                    using var bwCancel = new BinaryWriter(msCancel);
                    bwCancel.Write(new byte[] { 2, 0, 0x00, 0x08, 0, 0, 0, 11, 0x01 });
                    WriteIppAttribute(bwCancel, 0x47, "attributes-charset", "utf-8");
                    WriteIppAttribute(bwCancel, 0x48, "attributes-natural-language", "en");
                    WriteIppAttribute(bwCancel, 0x45, "printer-uri", $"ipp://{ip}:{ippPort}/ipp/print");
                    bwCancel.Write((byte)0x21); // integer tag
                    byte[] nb = Encoding.ASCII.GetBytes("job-id");
                    bwCancel.Write((byte)((nb.Length >> 8) & 0xFF));
                    bwCancel.Write((byte)(nb.Length & 0xFF));
                    bwCancel.Write(nb);
                    bwCancel.Write((byte)0);
                    bwCancel.Write((byte)4);
                    bwCancel.Write((byte)((jid >> 24) & 0xFF));
                    bwCancel.Write((byte)((jid >> 16) & 0xFF));
                    bwCancel.Write((byte)((jid >> 8) & 0xFF));
                    bwCancel.Write((byte)(jid & 0xFF));
                    bwCancel.Write((byte)0x03);

                    using var cCancel = new ByteArrayContent(msCancel.ToArray());
                    cCancel.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/ipp");
                    await http.PostAsync($"http://{ip}:{ippPort}/ipp/print", cCancel);
                    log?.Invoke($"[IPP :631] Spool-Slot für Job #{jid} für Folgedrucke freigegeben.");
                }
            }
            catch { }
        }

        private static string? ResolveVerifiedPythonExe()
        {
            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            string progFiles = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles);

            foreach (string ver in new[] { "Python312", "Python313", "Python311", "Python310" })
            {
                string c1 = Path.Combine(localAppData, "Programs", "Python", ver, "python.exe");
                if (File.Exists(c1)) return c1;

                string c2 = Path.Combine(progFiles, ver, "python.exe");
                if (File.Exists(c2)) return c2;
            }
            return null;
        }

        private static async Task<byte[]?> ConvertPdfBase64ToPngAsync(string pdfBase64, Action<string>? log = null)
        {
            if (string.IsNullOrWhiteSpace(pdfBase64) || pdfBase64.Length > 14_000_000)
            {
                log?.Invoke("[Security] PDF-Payload abgelehnt (leer oder überschreitet 10 MB Limit).");
                return null;
            }

            string? pyExe = ResolveVerifiedPythonExe();
            if (string.IsNullOrEmpty(pyExe))
            {
                log?.Invoke("[PyMuPDF Warnung] Keine verifizierte lokale Python-Installation gefunden.");
                return null;
            }

            string tempPdf = Path.Combine(Path.GetTempPath(), $"sf_label_{Guid.NewGuid():N}.pdf");
            string tempPng = Path.Combine(Path.GetTempPath(), $"sf_label_{Guid.NewGuid():N}.png");
            byte[]? pdfBytes = null;
            try
            {
                pdfBytes = Convert.FromBase64String(pdfBase64);
                if (!FunkenwerkSecurityVault.IsValidPdfPayload(pdfBytes))
                {
                    log?.Invoke("[Security] Ungültiger PDF-Header (kein '%PDF-' Magic-Byte) – Ausführung blockiert.");
                    return null;
                }

                await File.WriteAllBytesAsync(tempPdf, pdfBytes);

                // Isoliertes PyMuPDF-Rendering mit Pixel-Bomb-Schutz (max 2500 px Kantenlänge)
                string pyScript =
                    "import sys\n" +
                    "try:\n" +
                    "    import pymupdf as fitz\n" +
                    "except ImportError:\n" +
                    "    import fitz\n" +
                    "doc = fitz.open(sys.argv[1])\n" +
                    "if len(doc) < 1:\n" +
                    "    sys.exit(2)\n" +
                    "page = doc[0]\n" +
                    "rect = page.rect\n" +
                    "max_pt = max(rect.width, rect.height, 1.0)\n" +
                    "target_dpi = min(250, int(2400.0 * 72.0 / max_pt))\n" +
                    "target_dpi = max(120, target_dpi)\n" +
                    "pix = page.get_pixmap(dpi=target_dpi, colorspace=fitz.csRGB, alpha=False)\n" +
                    "pix.save(sys.argv[2])\n" +
                    "doc.close()\n" +
                    "print(f'OK:{pix.width}x{pix.height}@{target_dpi}dpi')\n";

                var psi = new ProcessStartInfo
                {
                    FileName = pyExe,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardError = true,
                    RedirectStandardOutput = true
                };
                // -E ignoriert unsichere PYTHON* Umgebungsvariablen (PYTHONPATH / PYTHONSTARTUP)
                psi.ArgumentList.Add("-E");
                psi.ArgumentList.Add("-c");
                psi.ArgumentList.Add(pyScript);
                psi.ArgumentList.Add(tempPdf);
                psi.ArgumentList.Add(tempPng);

                using var proc = Process.Start(psi);
                if (proc != null)
                {
                    using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(12.0));
                    Task<string> stdOutTask = proc.StandardOutput.ReadToEndAsync();
                    Task<string> stdErrTask = proc.StandardError.ReadToEndAsync();
                    try
                    {
                        await proc.WaitForExitAsync(cts.Token);
                    }
                    catch (OperationCanceledException)
                    {
                        try { proc.Kill(entireProcessTree: true); } catch { }
                        log?.Invoke("[Security] PyMuPDF-Subprozess nach 12s Timeout beendet.");
                        return null;
                    }

                    string stdOut = await stdOutTask;
                    string stdErr = await stdErrTask;
                    if (!string.IsNullOrWhiteSpace(stdErr))
                    {
                        log?.Invoke($"[PyMuPDF Warnung] {stdErr.Trim()}");
                    }
                    if (File.Exists(tempPng))
                    {
                        byte[] png = await File.ReadAllBytesAsync(tempPng);
                        log?.Invoke($"[PyMuPDF] PDF ({pdfBytes.Length} B) -> PNG ({png.Length / 1024.0:0.1} KB, {stdOut.Trim()})");
                        return png;
                    }
                    log?.Invoke($"[PyMuPDF Fehler] Keine PNG-Ausgabe erzeugt (ExitCode {proc.ExitCode}).");
                }
            }
            catch (Exception ex)
            {
                log?.Invoke($"[PyMuPDF Exception] {ex.Message}");
            }
            finally
            {
                if (pdfBytes != null) CryptographicOperations.ZeroMemory(pdfBytes);
                FunkenwerkSecurityVault.SecureZeroDeleteFile(tempPdf);
                FunkenwerkSecurityVault.SecureZeroDeleteFile(tempPng);
            }
            return null;
        }

        public static BitmapSource RenderOrderLabelBitmap(FunkenwerkPrintJobDto job)
        {
            int w = LabelWidthPx;
            int h = LabelHeightPx;
            var dv = new System.Windows.Media.DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                var black = System.Windows.Media.Brushes.Black;
                var white = System.Windows.Media.Brushes.White;
                var penThick = new System.Windows.Media.Pen(black, 4);
                var penMed = new System.Windows.Media.Pen(black, 2.5);
                var penThin = new System.Windows.Media.Pen(black, 1.5);
                var typefaceBold = new System.Windows.Media.Typeface(new System.Windows.Media.FontFamily("Segoe UI"), System.Windows.FontStyles.Normal, System.Windows.FontWeights.Black, System.Windows.FontStretches.Normal);
                var typefaceSemi = new System.Windows.Media.Typeface(new System.Windows.Media.FontFamily("Segoe UI"), System.Windows.FontStyles.Normal, System.Windows.FontWeights.Bold, System.Windows.FontStretches.Normal);
                var typefaceReg = new System.Windows.Media.Typeface(new System.Windows.Media.FontFamily("Segoe UI"), System.Windows.FontStyles.Normal, System.Windows.FontWeights.Medium, System.Windows.FontStretches.Normal);
                var typefaceMono = new System.Windows.Media.Typeface(new System.Windows.Media.FontFamily("Consolas"), System.Windows.FontStyles.Normal, System.Windows.FontWeights.Bold, System.Windows.FontStretches.Normal);

                // White background + Outer Frame
                dc.DrawRectangle(white, null, new System.Windows.Rect(0, 0, w, h));
                dc.DrawRectangle(null, penThick, new System.Windows.Rect(22, 22, w - 44, h - 44));

                // 1. TOP HEADER BAR (Brand + Express/Package Info)
                dc.DrawRectangle(black, null, new System.Windows.Rect(22, 22, w - 44, 110));
                DrawText(dc, "MEIN SEELENFUNKE", typefaceBold, 34, white, 48, 40);
                DrawText(dc, "MANUFAKTUR GIFHORN · HANDVEREDELT IN DEUTSCHLAND", typefaceSemi, 15, white, 48, 86);

                string pkgBadge = job.IsExpress ? "EXPRESS" : $"PAKET {job.PackageIndex}/{Math.Max(1, job.PackageTotal)}";
                dc.DrawRoundedRectangle(white, null, new System.Windows.Rect(w - 235, 44, 190, 64), 10, 10);
                DrawText(dc, pkgBadge, typefaceBold, 24, black, w - 215, 60);

                // 2. SENDER LINE (Absender)
                string senderLine = $"ABS: {job.Sender.Name} · {job.Sender.Street} · {job.Sender.PostalCode} {job.Sender.City}";
                DrawText(dc, senderLine, typefaceSemi, 17, black, 48, 150);
                dc.DrawLine(penMed, new System.Windows.Point(22, 188), new System.Windows.Point(w - 22, 188));

                // 3. RECIPIENT BLOCK (Empfänger - Groß & DHL-konform lesbar)
                DrawText(dc, "EMPFÄNGER / LIEFERADRESSE:", typefaceSemi, 16, black, 48, 206);
                double ry = 242;
                DrawText(dc, string.IsNullOrWhiteSpace(job.Recipient.Name) ? "Kunde" : job.Recipient.Name, typefaceBold, 38, black, 48, ry, w - 96);
                ry += 52;
                if (!string.IsNullOrWhiteSpace(job.Recipient.Company))
                {
                    DrawText(dc, job.Recipient.Company, typefaceSemi, 28, black, 48, ry, w - 96);
                    ry += 40;
                }
                DrawText(dc, job.Recipient.Street, typefaceSemi, 34, black, 48, ry, w - 96);
                ry += 52;

                // Highlighted Postal Code + City Box
                dc.DrawRoundedRectangle(null, penThick, new System.Windows.Rect(44, ry, w - 88, 92), 10, 10);
                DrawText(dc, $"{job.Recipient.PostalCode}  {job.Recipient.City}".Trim(), typefaceBold, 44, black, 64, ry + 18, w - 128);
                ry += 108;

                string countryName = job.Recipient.Country == "DE" ? "DEUTSCHLAND (GERMANY)" : job.Recipient.Country;
                DrawText(dc, countryName, typefaceBold, 24, black, 48, ry);

                dc.DrawLine(penThick, new System.Windows.Point(22, 560), new System.Windows.Point(w - 22, 560));

                // 4. ORDER METADATA & BARCODE BLOCK
                DrawText(dc, $"BESTELLUNG: #{job.OrderNumber}", typefaceBold, 30, black, 48, 580);
                DrawText(dc, $"GEWICHT: {job.WeightKg:0.00} kg   |   DATUM: {DateTime.Now:dd.MM.yyyy HH:mm}", typefaceSemi, 20, black, 48, 624);

                string codeVal = !string.IsNullOrWhiteSpace(job.TrackingNumber) ? job.TrackingNumber : job.OrderNumber;
                DrawSimulatedBarcode(dc, codeVal, 48, 668, w - 96, 130);
                DrawText(dc, $"SENDUNGS-/AUFTRAGSNR: {codeVal}", typefaceMono, 21, black, 48, 810);

                dc.DrawLine(penMed, new System.Windows.Point(22, 852), new System.Windows.Point(w - 22, 852));

                // 5. PACKLISTE / BESTELLPOSITIONEN FÜR PACKTISCH
                DrawText(dc, "PACKLISTE / ENTHALTENE POSITIONEN:", typefaceBold, 19, black, 48, 870);
                double iy = 906;
                int shown = 0;
                foreach (var item in job.Items)
                {
                    if (shown >= 6)
                    {
                        DrawText(dc, $"+ {job.Items.Count - shown} weitere Position(en)...", typefaceSemi, 18, black, 48, iy);
                        break;
                    }
                    string line = $"[ ]  {item.Qty}x  {item.Name}" + (!string.IsNullOrWhiteSpace(item.Sku) ? $" ({item.Sku})" : "");
                    DrawText(dc, line, typefaceSemi, 20, black, 48, iy, w - 96);
                    iy += 34;
                    shown++;
                }
                if (job.Items.Count == 0)
                {
                    DrawText(dc, "[ ]  1x  Manufaktur-Bestellung (siehe Lieferschein)", typefaceSemi, 20, black, 48, iy);
                }

                // Bottom Footer
                dc.DrawLine(penThin, new System.Windows.Point(22, h - 78), new System.Windows.Point(w - 22, h - 78));
                DrawText(dc, "FUNKENWERK PRINT-BRIDGE · MUNBYN ITPP941AP (203 DPI) · MEIN-SEELENFUNKE.DE", typefaceSemi, 15, black, 48, h - 58);
            }

            var rtb = new RenderTargetBitmap(w, h, 96, 96, System.Windows.Media.PixelFormats.Pbgra32);
            rtb.Render(dv);
            rtb.Freeze();
            return rtb;
        }

        private static void DrawText(System.Windows.Media.DrawingContext dc, string text, System.Windows.Media.Typeface typeface, double size, System.Windows.Media.Brush brush, double x, double y, double maxWidth = 0)
        {
            var ft = new System.Windows.Media.FormattedText(
                text ?? "",
                CultureInfo.GetCultureInfo("de-DE"),
                System.Windows.FlowDirection.LeftToRight,
                typeface,
                size,
                brush,
                1.0);
            if (maxWidth > 0)
            {
                ft.MaxTextWidth = maxWidth;
                ft.MaxLineCount = 1;
                ft.Trimming = System.Windows.TextTrimming.CharacterEllipsis;
            }
            dc.DrawText(ft, new System.Windows.Point(x, y));
        }

        private static void DrawSimulatedBarcode(System.Windows.Media.DrawingContext dc, string value, double x, double y, double width, double height)
        {
            string clean = string.IsNullOrWhiteSpace(value) ? "SF2026" : value.Trim();
            // Deterministic high-contrast bar pattern from string bytes + start/stop guard bars
            var bits = new List<bool> { true, true, false, true, false, true, true, false };
            foreach (char c in clean)
            {
                int v = c;
                bits.Add(true);
                bits.Add((v & 1) != 0);
                bits.Add(false);
                bits.Add((v & 2) != 0);
                bits.Add(true);
                bits.Add((v & 4) == 0);
                bits.Add(false);
                bits.Add((v & 8) != 0);
                bits.Add((v & 16) != 0);
                bits.Add(false);
            }
            bits.AddRange(new[] { true, true, false, true, false, true, true });

            double moduleW = width / bits.Count;
            var black = System.Windows.Media.Brushes.Black;
            for (int i = 0; i < bits.Count; i++)
            {
                if (bits[i])
                {
                    dc.DrawRectangle(black, null, new System.Windows.Rect(x + i * moduleW, y, Math.Max(1.5, moduleW + 0.3), height));
                }
            }
        }

        private static byte[] EncodeBitmapToJpeg(BitmapSource bmp, int quality = 95)
        {
            using var ms = new MemoryStream();
            var encoder = new JpegBitmapEncoder { QualityLevel = quality };
            encoder.Frames.Add(BitmapFrame.Create(bmp));
            encoder.Save(ms);
            return ms.ToArray();
        }

        private static byte[] BuildTsplBitmapCommand(BitmapSource src)
        {
            // Scale to 800 x 1200 (100mm x 150mm at 8 dots/mm = 203 DPI)
            int targetW = 800;
            int targetH = 1200;
            var scaled = new TransformedBitmap(src, new System.Windows.Media.ScaleTransform((double)targetW / src.PixelWidth, (double)targetH / src.PixelHeight));
            var gray = new FormatConvertedBitmap(scaled, System.Windows.Media.PixelFormats.Bgra32, null, 0);

            int stride = targetW * 4;
            byte[] pixels = new byte[stride * targetH];
            gray.CopyPixels(pixels, stride, 0);

            int widthBytes = targetW / 8; // 100 bytes per row
            byte[] rawMono = new byte[widthBytes * targetH];
            // In TSPL BITMAP mode 0 (overwrite): 0 = black (burn dot), 1 = white
            for (int i = 0; i < rawMono.Length; i++) rawMono[i] = 0xFF;

            for (int y = 0; y < targetH; y++)
            {
                int rowOffset = y * stride;
                int outRowOffset = y * widthBytes;
                for (int x = 0; x < targetW; x++)
                {
                    int pxIdx = rowOffset + x * 4;
                    byte b = pixels[pxIdx];
                    byte g = pixels[pxIdx + 1];
                    byte r = pixels[pxIdx + 2];
                    int lum = (r * 299 + g * 587 + b * 114) / 1000;
                    if (lum < 160) // Black pixel -> clear bit to 0
                    {
                        rawMono[outRowOffset + (x >> 3)] &= (byte)~(0x80 >> (x & 7));
                    }
                }
            }

            using var ms = new MemoryStream();
            byte[] header = Encoding.ASCII.GetBytes($"SIZE 100 mm,150 mm\r\nGAP 2 mm,0 mm\r\nDIRECTION 1\r\nCLS\r\nBITMAP 0,0,{widthBytes},{targetH},0,");
            byte[] footer = Encoding.ASCII.GetBytes("\r\nPRINT 1,1\r\n");
            ms.Write(header, 0, header.Length);
            ms.Write(rawMono, 0, rawMono.Length);
            ms.Write(footer, 0, footer.Length);
            return ms.ToArray();
        }

        private static byte[] BuildIppGetAttributesRequest(string ip, int port)
        {
            using var ms = new MemoryStream();
            using var bw = new BinaryWriter(ms);
            bw.Write(new byte[] { 2, 0, 0x00, 0x0B, 0, 0, 0, 1, 0x01 });
            WriteIppAttribute(bw, 0x47, "attributes-charset", "utf-8");
            WriteIppAttribute(bw, 0x48, "attributes-natural-language", "en-us");
            WriteIppAttribute(bw, 0x45, "printer-uri", $"ipp://{ip}:{port}/ipp/print");
            bw.Write((byte)0x03);
            return ms.ToArray();
        }

        private static void WriteIppAttribute(BinaryWriter bw, byte tag, string name, string val)
        {
            byte[] nb = Encoding.ASCII.GetBytes(name);
            byte[] vb = Encoding.ASCII.GetBytes(val);
            bw.Write(tag);
            bw.Write((byte)((nb.Length >> 8) & 0xFF));
            bw.Write((byte)(nb.Length & 0xFF));
            bw.Write(nb);
            bw.Write((byte)((vb.Length >> 8) & 0xFF));
            bw.Write((byte)(vb.Length & 0xFF));
            bw.Write(vb);
        }
    }
}

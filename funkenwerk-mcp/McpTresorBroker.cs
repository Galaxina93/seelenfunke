using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.WebSockets;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Web;

namespace FunkenwerkMcp
{
    public sealed class TresorAiEntryMetadataDto
    {
        [JsonPropertyName("id")]
        public string Id { get; set; } = "";

        [JsonPropertyName("title")]
        public string Title { get; set; } = "";

        [JsonPropertyName("allowed_host")]
        public string AllowedHost { get; set; } = "";

        [JsonPropertyName("has_username")]
        public bool HasUsername { get; set; }

        [JsonPropertyName("has_password")]
        public bool HasPassword { get; set; }

        [JsonPropertyName("has_totp")]
        public bool HasTotp { get; set; }

        [JsonPropertyName("has_api_token")]
        public bool HasApiToken { get; set; }

        [JsonPropertyName("updated_at")]
        public string UpdatedAt { get; set; } = "";
    }

    public sealed class TresorAiEntryListResultDto
    {
        [JsonPropertyName("vault_exists")]
        public bool VaultExists { get; set; }

        [JsonPropertyName("count")]
        public int Count { get; set; }

        [JsonPropertyName("entries")]
        public List<TresorAiEntryMetadataDto> Entries { get; set; } = new();
    }

    public sealed class TresorBrowserLoginResultDto
    {
        [JsonPropertyName("status")]
        public string Status { get; set; } = "ok";

        [JsonPropertyName("matched_host")]
        public string MatchedHost { get; set; } = "";

        [JsonPropertyName("filled_username")]
        public bool FilledUsername { get; set; }

        [JsonPropertyName("filled_password")]
        public bool FilledPassword { get; set; }

        [JsonPropertyName("filled_totp")]
        public bool FilledTotp { get; set; }

        [JsonPropertyName("submitted")]
        public bool Submitted { get; set; }
    }

    public sealed class MittwaldBrokerResultDto
    {
        [JsonPropertyName("status")]
        public string Status { get; set; } = "ok";

        [JsonPropertyName("preset")]
        public string Preset { get; set; } = "";

        [JsonPropertyName("http_status")]
        public int HttpStatus { get; set; }

        [JsonPropertyName("entry_title")]
        public string EntryTitle { get; set; } = "";

        [JsonPropertyName("data")]
        public JsonNode? Data { get; set; }
    }

    public static class McpTresorBroker
    {
        private static readonly byte[] s_brokerMagic = "FWAIBRK1"u8.ToArray();
        private static readonly byte[] s_brokerDpapiEntropy = "Funkenwerk-Tresor-AI-Broker-v1"u8.ToArray();
        private static readonly byte[] s_brokerAeadAad = "Funkenwerk-Tresor-AI-Broker-AEAD-v1"u8.ToArray();
        private const byte BrokerFormatVersion = 0x01;
        private const string Base32Alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

        private static readonly Regex SafeMittwaldResourceIdRegex = new(@"^[A-Za-z0-9\-_]{2,64}$", RegexOptions.Compiled);

        public static readonly HashSet<string> PinnedStageAppShortIds = new(StringComparer.OrdinalIgnoreCase)
        {
            "a-lc6tkm", // seelenfunke-stage
            "a-iurgq8", // worker-stage-3
            "a-yeq7el"  // twilio-bridge
        };

        public static readonly IReadOnlyList<string> AllowedMittwaldPresets = new[]
        {
            "user_self",
            "list_customers",
            "list_projects",
            "list_ai_hostings",
            "project_audit",
            "list_apps",
            "get_app",
            "list_cronjobs",
            "list_ingresses",
            "restart_stage_app",
            "execute_stage_cronjob"
        };

        // Multi-part public suffixes / shared hosting suffixes to prevent cross-tenant subdomain matching
        private static readonly HashSet<string> MultiPartPublicSuffixes = new(StringComparer.OrdinalIgnoreCase)
        {
            "co.uk", "org.uk", "ac.uk", "gov.uk", "me.uk", "net.uk",
            "com.au", "net.au", "org.au", "edu.au", "gov.au",
            "co.nz", "org.nz", "net.nz",
            "co.jp", "ne.jp", "or.jp", "ac.jp", "go.jp",
            "co.at", "or.at", "gv.at", "ac.at",
            "com.br", "net.br", "org.br",
            "co.za", "org.za", "web.za",
            "com.mx", "com.tr", "com.cn", "com.tw", "com.hk", "com.sg", "co.in", "co.kr",
            "github.io", "gitlab.io", "vercel.app", "netlify.app", "pages.dev",
            "ondigitalocean.app", "azurewebsites.net", "cloudfront.net", "project.host"
        };

        internal static string? TestBrokerVaultFileOverride { get; set; }
        internal static string? TestDevToolsActivePortFileOverride { get; set; }
        internal static int? TestDefaultCdpPortOverride { get; set; }
        internal static HttpClient? TestMittwaldHttpClientOverride { get; set; }

        public static string BrokerVaultFilePath =>
            !string.IsNullOrWhiteSpace(TestBrokerVaultFileOverride)
                ? Path.GetFullPath(TestBrokerVaultFileOverride!)
                : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkTresor", "ai_broker_vault.bin");

        public static string ChromeDevToolsActivePortPath =>
            !string.IsNullOrWhiteSpace(TestDevToolsActivePortFileOverride)
                ? Path.GetFullPath(TestDevToolsActivePortFileOverride!)
                : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Google", "Chrome", "User Data", "DevToolsActivePort");

        // =====================================================================
        // 1. ZERO-SECRET BROKER VAULT READER (DPAPI + AES-256-GCM + ZEROMEMORY)
        // =====================================================================
        internal sealed class TestBrokerSeedEntry
        {
            public string Id { get; set; } = "";
            public string Title { get; set; } = "";
            public string Url { get; set; } = "";
            public string Username { get; set; } = "";
            public string Password { get; set; } = "";
            public string TotpSeedOrConfig { get; set; } = "";
            public string ApiToken { get; set; } = "";
            public string UpdatedAtUtc { get; set; } = "";
            public bool AiBrokerEnabled { get; set; }
        }

        /// <summary>
        /// Test helper writing the exact same FWAIBRK1 DPAPI + AES-256-GCM envelope as FunkenwerkTresor.
        /// Only entries with AiBrokerEnabled == true are serialized.
        /// </summary>
        internal static void WriteBrokerVaultForTest(string targetFilePath, IEnumerable<TestBrokerSeedEntry> allEntries)
        {
            string fullPath = Path.GetFullPath(targetFilePath);
            string? dir = Path.GetDirectoryName(fullPath);
            if (!string.IsNullOrEmpty(dir))
            {
                Directory.CreateDirectory(dir);
                McpSecurityGuard.LockDownDirectoryAcl(dir);
            }

            var approved = allEntries.Where(e => e.AiBrokerEnabled).ToList();
            byte[]? plainJson = null;
            byte[]? dek = null;
            byte[]? wrappedDek = null;
            byte[]? nonce = null;
            byte[]? tag = null;
            byte[]? cipher = null;
            byte[]? envelope = null;

            try
            {
                using (var ms = new MemoryStream())
                {
                    using (var w = new Utf8JsonWriter(ms))
                    {
                        w.WriteStartArray();
                        foreach (var e in approved)
                        {
                            w.WriteStartObject();
                            w.WriteString("Id", e.Id);
                            w.WriteString("Title", e.Title);
                            w.WriteString("Url", e.Url);
                            w.WriteString("Username", e.Username);
                            w.WriteString("Password", e.Password);
                            w.WriteString("TotpSeedOrConfig", e.TotpSeedOrConfig);
                            w.WriteString("ApiToken", e.ApiToken);
                            w.WriteString("UpdatedAtUtc", string.IsNullOrWhiteSpace(e.UpdatedAtUtc) ? DateTime.UtcNow.ToString("O") : e.UpdatedAtUtc);
                            w.WriteEndObject();
                        }
                        w.WriteEndArray();
                        w.Flush();
                    }
                    plainJson = ms.ToArray();
                    if (ms.TryGetBuffer(out var seg) && seg.Array != null)
                        CryptographicOperations.ZeroMemory(seg.Array);
                }

                dek = RandomNumberGenerator.GetBytes(32);
                nonce = RandomNumberGenerator.GetBytes(12);
                tag = new byte[16];
                cipher = new byte[plainJson.Length];

                using (var aesGcm = new AesGcm(dek, 16))
                {
                    aesGcm.Encrypt(nonce, plainJson, cipher, tag, s_brokerAeadAad);
                }

                wrappedDek = ProtectedData.Protect(dek, s_brokerDpapiEntropy, DataProtectionScope.CurrentUser);

                using (var outMs = new MemoryStream())
                using (var bw = new BinaryWriter(outMs, Encoding.UTF8, leaveOpen: true))
                {
                    bw.Write(s_brokerMagic);
                    bw.Write(BrokerFormatVersion);
                    bw.Write(wrappedDek.Length);
                    bw.Write(wrappedDek);
                    bw.Write(nonce);
                    bw.Write(tag);
                    bw.Write(cipher.Length);
                    bw.Write(cipher);
                    bw.Flush();
                    envelope = outMs.ToArray();
                }

                File.WriteAllBytes(fullPath, envelope);
                McpSecurityGuard.LockDownFileAcl(fullPath);
            }
            finally
            {
                if (plainJson != null) CryptographicOperations.ZeroMemory(plainJson);
                if (dek != null) CryptographicOperations.ZeroMemory(dek);
                if (wrappedDek != null) CryptographicOperations.ZeroMemory(wrappedDek);
                if (nonce != null) CryptographicOperations.ZeroMemory(nonce);
                if (tag != null) CryptographicOperations.ZeroMemory(tag);
                if (cipher != null) CryptographicOperations.ZeroMemory(cipher);
                if (envelope != null) CryptographicOperations.ZeroMemory(envelope);
            }
        }

        private static byte[] DecryptBrokerVaultRawJsonUtf8(string vaultPath)
        {
            if (!File.Exists(vaultPath))
                throw new FileNotFoundException("KI-Broker-Tresordatei (ai_broker_vault.bin) wurde noch nicht erstellt. Bitte in Funkenwerk Tresor mindestens einen Eintrag freigeben und speichern.");

            McpSecurityGuard.RejectReparsePointsInHierarchy(vaultPath);

            byte[]? rawFile = null;
            byte[]? wrappedDek = null;
            byte[]? dek = null;
            byte[]? nonce = null;
            byte[]? tag = null;
            byte[]? cipherBytes = null;

            try
            {
                rawFile = File.ReadAllBytes(vaultPath);
                if (rawFile.Length < 45 || rawFile.Length > 32 * 1024 * 1024)
                    throw new InvalidDataException("KI-Broker-Tresordatei hat eine ungültige Dateigröße.");

                using var ms = new MemoryStream(rawFile, writable: false);
                using var br = new BinaryReader(ms, Encoding.UTF8, leaveOpen: true);

                byte[] magic = br.ReadBytes(8);
                if (!magic.AsSpan().SequenceEqual(s_brokerMagic))
                    throw new InvalidDataException("KI-Broker-Tresordatei besitzt keinen gültigen FWAIBRK1-Header.");

                byte ver = br.ReadByte();
                if (ver != BrokerFormatVersion)
                    throw new InvalidDataException($"Nicht unterstützte KI-Broker-Formatversion: {ver}.");

                int wrappedLen = br.ReadInt32();
                if (wrappedLen <= 0 || wrappedLen > 4096 || ms.Length - ms.Position < wrappedLen + 32)
                    throw new InvalidDataException("Ungültiger DPAPI-Schlüsselblock in ai_broker_vault.bin.");

                wrappedDek = br.ReadBytes(wrappedLen);
                nonce = br.ReadBytes(12);
                tag = br.ReadBytes(16);

                int cipherLen = br.ReadInt32();
                if (cipherLen < 2 || cipherLen > 16 * 1024 * 1024 || ms.Length - ms.Position != cipherLen)
                    throw new InvalidDataException("Ungültige AES-GCM-Nutzlastlänge in ai_broker_vault.bin.");

                cipherBytes = br.ReadBytes(cipherLen);
                dek = ProtectedData.Unprotect(wrappedDek, s_brokerDpapiEntropy, DataProtectionScope.CurrentUser);
                if (dek.Length != 32)
                    throw new CryptographicException("Entpackter Broker-DEK hat keine 256 Bit.");

                byte[] plainUtf8 = new byte[cipherLen];
                try
                {
                    using var aesGcm = new AesGcm(dek, 16);
                    aesGcm.Decrypt(nonce, cipherBytes, tag, plainUtf8, s_brokerAeadAad);
                    return plainUtf8;
                }
                catch
                {
                    CryptographicOperations.ZeroMemory(plainUtf8);
                    throw;
                }
            }
            finally
            {
                if (rawFile != null) CryptographicOperations.ZeroMemory(rawFile);
                if (wrappedDek != null) CryptographicOperations.ZeroMemory(wrappedDek);
                if (dek != null) CryptographicOperations.ZeroMemory(dek);
                if (nonce != null) CryptographicOperations.ZeroMemory(nonce);
                if (tag != null) CryptographicOperations.ZeroMemory(tag);
                if (cipherBytes != null) CryptographicOperations.ZeroMemory(cipherBytes);
            }
        }

        private sealed class BrokerSecretLease : IDisposable
        {
            public string Id { get; init; } = "";
            public string Title { get; init; } = "";
            public string Url { get; init; } = "";
            public string UpdatedAtUtc { get; init; } = "";
            public byte[] UsernameUtf8 { get; init; } = Array.Empty<byte>();
            public byte[] PasswordUtf8 { get; init; } = Array.Empty<byte>();
            public byte[] TotpSeedOrConfigUtf8 { get; init; } = Array.Empty<byte>();
            public byte[] ApiTokenUtf8 { get; init; } = Array.Empty<byte>();

            public void Dispose()
            {
                if (UsernameUtf8.Length > 0) CryptographicOperations.ZeroMemory(UsernameUtf8);
                if (PasswordUtf8.Length > 0) CryptographicOperations.ZeroMemory(PasswordUtf8);
                if (TotpSeedOrConfigUtf8.Length > 0) CryptographicOperations.ZeroMemory(TotpSeedOrConfigUtf8);
                if (ApiTokenUtf8.Length > 0) CryptographicOperations.ZeroMemory(ApiTokenUtf8);
            }
        }

        private static byte[] ExtractJsonPropertyUtf8Copy(JsonElement obj, string propName)
        {
            if (!obj.TryGetProperty(propName, out var el) || el.ValueKind != JsonValueKind.String)
                return Array.Empty<byte>();

            string? val = el.GetString();
            if (string.IsNullOrEmpty(val))
                return Array.Empty<byte>();

            return Encoding.UTF8.GetBytes(val);
        }

        private static bool HasNonEmptyJsonString(JsonElement obj, string propName)
        {
            if (!obj.TryGetProperty(propName, out var el) || el.ValueKind != JsonValueKind.String)
                return false;
            string? val = el.GetString();
            return !string.IsNullOrWhiteSpace(val);
        }

        public static TresorAiEntryListResultDto ListApprovedEntries()
        {
            string vaultPath = BrokerVaultFilePath;
            if (!File.Exists(vaultPath))
            {
                McpSecurityGuard.AppendAuditLog("tresor_list_ai_entries", "vault_missing", "ALLOW");
                return new TresorAiEntryListResultDto
                {
                    VaultExists = false,
                    Count = 0,
                    Entries = new List<TresorAiEntryMetadataDto>()
                };
            }

            byte[]? plainUtf8 = null;
            try
            {
                plainUtf8 = DecryptBrokerVaultRawJsonUtf8(vaultPath);
                using var doc = JsonDocument.Parse(plainUtf8, new JsonDocumentOptions { MaxDepth = 8 });
                if (doc.RootElement.ValueKind != JsonValueKind.Array)
                    throw new InvalidDataException("Ungültige JSON-Struktur in ai_broker_vault.bin.");

                var result = new List<TresorAiEntryMetadataDto>();
                foreach (var el in doc.RootElement.EnumerateArray())
                {
                    string id = el.TryGetProperty("Id", out var idEl) ? (idEl.GetString() ?? "") : "";
                    string title = el.TryGetProperty("Title", out var tEl) ? (tEl.GetString() ?? "") : "";
                    string url = el.TryGetProperty("Url", out var uEl) ? (uEl.GetString() ?? "") : "";
                    string updated = el.TryGetProperty("UpdatedAtUtc", out var upEl) ? (upEl.GetString() ?? "") : "";

                    result.Add(new TresorAiEntryMetadataDto
                    {
                        Id = id,
                        Title = title,
                        AllowedHost = TryExtractConfiguredHttpsHost(url, out string host, out _) ? host : "",
                        HasUsername = HasNonEmptyJsonString(el, "Username"),
                        HasPassword = HasNonEmptyJsonString(el, "Password"),
                        HasTotp = HasNonEmptyJsonString(el, "TotpSeedOrConfig"),
                        HasApiToken = HasNonEmptyJsonString(el, "ApiToken"),
                        UpdatedAt = updated
                    });
                }

                McpSecurityGuard.AppendAuditLog("tresor_list_ai_entries", $"count={result.Count}", "ALLOW");
                return new TresorAiEntryListResultDto
                {
                    VaultExists = true,
                    Count = result.Count,
                    Entries = result
                };
            }
            finally
            {
                if (plainUtf8 != null)
                    CryptographicOperations.ZeroMemory(plainUtf8);
            }
        }

        private static BrokerSecretLease ResolveEntrySecretLease(string? titleOrId, bool requireApiToken = false)
        {
            string query = (titleOrId ?? "").Trim();
            if (string.IsNullOrEmpty(query))
                throw new ArgumentException("Eintrags-Bezeichnung oder ID darf nicht leer sein.");

            byte[]? plainUtf8 = null;
            try
            {
                plainUtf8 = DecryptBrokerVaultRawJsonUtf8(BrokerVaultFilePath);
                using var doc = JsonDocument.Parse(plainUtf8, new JsonDocumentOptions { MaxDepth = 8 });
                if (doc.RootElement.ValueKind != JsonValueKind.Array)
                    throw new InvalidDataException("Ungültige JSON-Struktur in ai_broker_vault.bin.");

                var elements = doc.RootElement.EnumerateArray().ToList();
                if (elements.Count == 0)
                    throw new InvalidOperationException("Es sind aktuell keine Einträge im Funkenwerk Tresor für den KI-Broker freigegeben.");

                JsonElement? matched = null;

                // 1. Exact ID match
                foreach (var el in elements)
                {
                    string id = el.TryGetProperty("Id", out var p) ? (p.GetString() ?? "") : "";
                    if (string.Equals(id, query, StringComparison.OrdinalIgnoreCase))
                    {
                        matched = el;
                        break;
                    }
                }

                // 2. Exact Title match
                if (!matched.HasValue)
                {
                    var exactTitle = elements.Where(el =>
                    {
                        string t = el.TryGetProperty("Title", out var p) ? (p.GetString() ?? "") : "";
                        return string.Equals(t, query, StringComparison.OrdinalIgnoreCase);
                    }).ToList();

                    if (exactTitle.Count > 0)
                    {
                        matched = requireApiToken
                            ? (exactTitle.FirstOrDefault(e => HasNonEmptyJsonString(e, "ApiToken")).ValueKind != JsonValueKind.Undefined
                                ? exactTitle.First(e => HasNonEmptyJsonString(e, "ApiToken"))
                                : exactTitle[0])
                            : exactTitle[0];
                    }
                }

                // 3. Substring Title match
                if (!matched.HasValue)
                {
                    var partial = elements.Where(el =>
                    {
                        string t = el.TryGetProperty("Title", out var p) ? (p.GetString() ?? "") : "";
                        return t.Contains(query, StringComparison.OrdinalIgnoreCase);
                    }).ToList();

                    if (requireApiToken)
                    {
                        var withTok = partial.Where(e => HasNonEmptyJsonString(e, "ApiToken")).ToList();
                        if (withTok.Count > 0)
                            partial = withTok;
                    }

                    if (partial.Count == 1)
                    {
                        matched = partial[0];
                    }
                    else if (partial.Count > 1)
                    {
                        throw new InvalidOperationException($"Mehrdeutige Tresor-Auswahl '{McpSecurityGuard.ScrubSecrets(query)}' ({partial.Count} freigegebene Treffer). Bitte exakte ID aus tresor_list_ai_entries angeben.");
                    }
                }

                if (!matched.HasValue)
                {
                    McpSecurityGuard.AppendAuditLog("tresor_broker_lookup", query, "DENY:entry_not_approved_or_missing");
                    throw new UnauthorizedAccessException($"Kein für den KI-Broker freigegebener Tresor-Eintrag für '{McpSecurityGuard.ScrubSecrets(query)}' gefunden.");
                }

                var m = matched.Value;
                var lease = new BrokerSecretLease
                {
                    Id = m.TryGetProperty("Id", out var idP) ? (idP.GetString() ?? "") : "",
                    Title = m.TryGetProperty("Title", out var tP) ? (tP.GetString() ?? "") : "",
                    Url = m.TryGetProperty("Url", out var uP) ? (uP.GetString() ?? "") : "",
                    UpdatedAtUtc = m.TryGetProperty("UpdatedAtUtc", out var upP) ? (upP.GetString() ?? "") : "",
                    UsernameUtf8 = ExtractJsonPropertyUtf8Copy(m, "Username"),
                    PasswordUtf8 = ExtractJsonPropertyUtf8Copy(m, "Password"),
                    TotpSeedOrConfigUtf8 = ExtractJsonPropertyUtf8Copy(m, "TotpSeedOrConfig"),
                    ApiTokenUtf8 = ExtractJsonPropertyUtf8Copy(m, "ApiToken")
                };

                if (requireApiToken && lease.ApiTokenUtf8.Length == 0)
                {
                    lease.Dispose();
                    throw new InvalidOperationException($"Der freigegebene Tresor-Eintrag '{lease.Title}' enthält kein hinterlegtes API-Token.");
                }

                return lease;
            }
            finally
            {
                if (plainUtf8 != null)
                    CryptographicOperations.ZeroMemory(plainUtf8);
            }
        }

        // =====================================================================
        // 2. ANTI-PHISHING DOMAIN GUARD & RFC-6238 TOTP ENGINE
        // =====================================================================
        public static bool TryExtractConfiguredHttpsHost(string? rawConfiguredUrl, out string asciiHost, out int port)
        {
            asciiHost = "";
            port = 443;
            if (string.IsNullOrWhiteSpace(rawConfiguredUrl))
                return false;

            string trimmed = rawConfiguredUrl.Trim();
            if (!trimmed.Contains("://", StringComparison.Ordinal))
                trimmed = "https://" + trimmed;

            if (!Uri.TryCreate(trimmed, UriKind.Absolute, out var uri))
                return false;
            if (!string.Equals(uri.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase))
                return false;
            if (!string.IsNullOrEmpty(uri.UserInfo) || string.IsNullOrWhiteSpace(uri.IdnHost))
                return false;

            asciiHost = uri.IdnHost.TrimEnd('.').ToLowerInvariant();
            port = uri.Port;
            return asciiHost.Length > 0;
        }

        public static string GetRegistrableBaseDomain(string asciiHost)
        {
            if (string.IsNullOrWhiteSpace(asciiHost))
                return "";

            string clean = asciiHost.Trim().TrimEnd('.').ToLowerInvariant();
            if (IPAddress.TryParse(clean, out _))
                return clean;

            string[] labels = clean.Split('.');
            if (labels.Length < 2 || labels.Any(string.IsNullOrWhiteSpace))
                return "";

            string lastTwo = $"{labels[^2]}.{labels[^1]}";
            if (MultiPartPublicSuffixes.Contains(lastTwo))
            {
                if (labels.Length < 3)
                    return ""; // Bare public suffix itself is never a registrable base domain
                return $"{labels[^3]}.{lastTwo}";
            }

            return lastTwo;
        }

        public static bool TryMatchAntiPhishingUrl(string? configuredEntryUrl, string? actualTabUrl, out string matchedHost, out string denyReason)
        {
            matchedHost = "";
            denyReason = "";

            if (!TryExtractConfiguredHttpsHost(configuredEntryUrl, out string entryHost, out int entryPort))
            {
                denyReason = "Tresor-Eintrag besitzt keine gültige HTTPS-Ziel-URL.";
                return false;
            }

            if (string.IsNullOrWhiteSpace(actualTabUrl) || !Uri.TryCreate(actualTabUrl.Trim(), UriKind.Absolute, out var tabUri))
            {
                denyReason = "Ziel-Tab besitzt keine gültige absolute URL.";
                return false;
            }

            if (!string.Equals(tabUri.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase))
            {
                denyReason = $"Ziel-Tab nutzt unsicheres Schema '{tabUri.Scheme}://' (zwingend https:// erforderlich).";
                return false;
            }

            if (!string.IsNullOrEmpty(tabUri.UserInfo))
            {
                denyReason = "UserInfo ('user@host') in Ziel-Tab-URL ist gesperrt.";
                return false;
            }

            if (tabUri.Port != entryPort)
            {
                denyReason = $"Ziel-Tab-Port ({tabUri.Port}) weicht vom erlaubten Port ({entryPort}) ab.";
                return false;
            }

            string tabHost = tabUri.IdnHost.TrimEnd('.').ToLowerInvariant();
            if (string.IsNullOrEmpty(tabHost))
            {
                denyReason = "Leerer Hostname im Ziel-Tab.";
                return false;
            }

            if (IPAddress.TryParse(entryHost, out _) || IPAddress.TryParse(tabHost, out _))
            {
                if (string.Equals(entryHost, tabHost, StringComparison.Ordinal))
                {
                    matchedHost = tabHost;
                    return true;
                }
                denyReason = $"IP-Host '{tabHost}' stimmt nicht exakt mit '{entryHost}' überein.";
                return false;
            }

            // 1. Exact host match (e.g. studio.mittwald.de == studio.mittwald.de)
            if (string.Equals(tabHost, entryHost, StringComparison.Ordinal))
            {
                matchedHost = tabHost;
                return true;
            }

            // 2. Registrable Base-Domain (eTLD+1) or direct subdomain match (e.g. login.mittwald.de for studio.mittwald.de)
            string entryBase = GetRegistrableBaseDomain(entryHost);
            string tabBase = GetRegistrableBaseDomain(tabHost);
            if (string.IsNullOrEmpty(entryBase) || !string.Equals(entryBase, tabBase, StringComparison.Ordinal))
            {
                denyReason = $"Fremde Domain '{tabHost}' stimmt nicht mit Tresor-Basisdomain '{(string.IsNullOrEmpty(entryBase) ? entryHost : entryBase)}' überein.";
                return false;
            }

            if (string.Equals(tabHost, entryBase, StringComparison.Ordinal))
            {
                matchedHost = tabHost;
                return true;
            }

            string suffix = "." + entryBase;
            if (tabHost.EndsWith(suffix, StringComparison.Ordinal))
            {
                string subPrefix = tabHost.Substring(0, tabHost.Length - suffix.Length);
                // Direct subdomain of registrable base domain (single non-empty label without dots)
                if (!string.IsNullOrWhiteSpace(subPrefix) && !subPrefix.Contains('.', StringComparison.Ordinal))
                {
                    matchedHost = tabHost;
                    return true;
                }
            }

            denyReason = $"Host '{tabHost}' ist keine direkte Subdomain von '{entryBase}'.";
            return false;
        }

        public static string ValidateAntiPhishingTargetUrl(string? configuredEntryUrl, string? actualTabUrl)
        {
            if (TryMatchAntiPhishingUrl(configuredEntryUrl, actualTabUrl, out string matchedHost, out string denyReason))
                return matchedHost;

            string safeTabSummary = "";
            if (!string.IsNullOrWhiteSpace(actualTabUrl) && Uri.TryCreate(actualTabUrl.Trim(), UriKind.Absolute, out var u))
                safeTabSummary = $"{u.Scheme}://{u.IdnHost}";

            McpSecurityGuard.AppendAuditLog("tresor_browser_login", safeTabSummary, "DENY:anti_phishing_blocked");
            throw new UnauthorizedAccessException($"ANTI-PHISHING-SPERRE: {denyReason}");
        }

        public static bool TryGenerateTotpCode(ReadOnlySpan<byte> secretOrUriUtf8, DateTimeOffset timestampUtc, out string codeDigits, out int remainingSeconds)
        {
            codeDigits = "";
            remainingSeconds = 30;
            if (secretOrUriUtf8.IsEmpty)
                return false;

            char[]? chars = null;
            byte[]? keyBytes = null;
            byte[]? hash = null;
            try
            {
                int charCount = Encoding.UTF8.GetCharCount(secretOrUriUtf8);
                chars = new char[charCount];
                Encoding.UTF8.GetChars(secretOrUriUtf8, chars);
                string trimmed = new string(chars).Trim();

                int digits = 6;
                int period = 30;
                string algo = "SHA1";
                string secretPart = trimmed;

                if (trimmed.StartsWith("otpauth://", StringComparison.OrdinalIgnoreCase))
                {
                    if (!Uri.TryCreate(trimmed, UriKind.Absolute, out var uri) ||
                        !string.Equals(uri.Scheme, "otpauth", StringComparison.OrdinalIgnoreCase) ||
                        !string.Equals(uri.Host, "totp", StringComparison.OrdinalIgnoreCase))
                    {
                        return false;
                    }

                    var q = HttpUtility.ParseQueryString(uri.Query);
                    string? sec = q["secret"];
                    if (string.IsNullOrWhiteSpace(sec))
                        return false;
                    secretPart = sec.Trim();

                    if (int.TryParse(q["digits"], out int d) && (d == 6 || d == 8)) digits = d;
                    if (int.TryParse(q["period"], out int p) && p >= 10 && p <= 120) period = p;
                    if (!string.IsNullOrWhiteSpace(q["algorithm"])) algo = q["algorithm"]!.Trim().ToUpperInvariant();
                }

                var cleanBase32 = new StringBuilder(secretPart.Length);
                foreach (char c in secretPart.ToUpperInvariant())
                {
                    if (Base32Alphabet.IndexOf(c) >= 0)
                        cleanBase32.Append(c);
                }

                if (cleanBase32.Length < 8)
                    return false;

                keyBytes = DecodeBase32(cleanBase32.ToString());
                if (keyBytes.Length == 0)
                    return false;

                long unixSeconds = timestampUtc.ToUnixTimeSeconds();
                long counter = unixSeconds / period;
                remainingSeconds = period - (int)(unixSeconds % period);

                byte[] counterBytes = BitConverter.GetBytes(counter);
                if (BitConverter.IsLittleEndian)
                    Array.Reverse(counterBytes);

                hash = algo switch
                {
                    "SHA256" => HMACSHA256.HashData(keyBytes, counterBytes),
                    "SHA512" => HMACSHA512.HashData(keyBytes, counterBytes),
                    _ => HMACSHA1.HashData(keyBytes, counterBytes)
                };

                int offset = hash[^1] & 0x0F;
                int binaryCode =
                    ((hash[offset] & 0x7F) << 24) |
                    ((hash[offset + 1] & 0xFF) << 16) |
                    ((hash[offset + 2] & 0xFF) << 8) |
                    (hash[offset + 3] & 0xFF);

                int mod = digits == 8 ? 100_000_000 : 1_000_000;
                int otp = binaryCode % mod;
                codeDigits = otp.ToString(digits == 8 ? "D8" : "D6", CultureInfo.InvariantCulture);
                return true;
            }
            catch
            {
                return false;
            }
            finally
            {
                if (chars != null) Array.Clear(chars, 0, chars.Length);
                if (keyBytes != null) CryptographicOperations.ZeroMemory(keyBytes);
                if (hash != null) CryptographicOperations.ZeroMemory(hash);
            }
        }

        private static byte[] DecodeBase32(string cleanUpperBase32)
        {
            var bytes = new List<byte>((cleanUpperBase32.Length * 5) / 8);
            int buffer = 0;
            int bitsLeft = 0;
            foreach (char c in cleanUpperBase32)
            {
                int val = Base32Alphabet.IndexOf(c);
                if (val < 0) continue;
                buffer = (buffer << 5) | val;
                bitsLeft += 5;
                if (bitsLeft >= 8)
                {
                    bitsLeft -= 8;
                    bytes.Add((byte)((buffer >> bitsLeft) & 0xFF));
                }
            }
            return bytes.ToArray();
        }

        // =====================================================================
        // 3. BLIND CDP LOOPBACK BROWSER AUTO-FILL (127.0.0.1 ONLY)
        // =====================================================================
        private const string DomAutofillFunctionDeclaration = @"
function(mode, userVal, passVal, totpVal, doSubmit) {
    function isVisible(el) {
        if (!el || el.disabled || el.readOnly) return false;
        const st = window.getComputedStyle(el);
        if (!st || st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity || '1') === 0) return false;
        const r = el.getBoundingClientRect();
        return r.width > 4 && r.height > 4;
    }
    function setNativeInput(el, val) {
        el.focus();
        const proto = Object.getPrototypeOf(el);
        const desc = Object.getOwnPropertyDescriptor(proto, 'value')
                  || Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        const prev = el.value;
        if (desc && desc.set) {
            desc.set.call(el, val);
        } else {
            el.value = val;
        }
        if (el._valueTracker && typeof el._valueTracker.setValue === 'function') {
            el._valueTracker.setValue(prev);
        }
        try {
            el.dispatchEvent(new InputEvent('input', { bubbles: true, composed: true, inputType: 'insertText', data: val }));
        } catch (_) {
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.blur();
    }
    function attrStr(el) {
        return [el.name, el.id, el.autocomplete, el.placeholder, el.getAttribute('aria-label'), el.getAttribute('data-testid')]
            .filter(Boolean).join(' ').toLowerCase();
    }
    function isOtpInput(el) {
        const t = (el.type || 'text').toLowerCase();
        if (t === 'password' || t === 'email' || t === 'hidden' || t === 'submit' || t === 'checkbox' || t === 'radio') return false;
        const ac = (el.autocomplete || '').toLowerCase();
        if (ac === 'one-time-code') return true;
        const a = attrStr(el);
        if (/(otp|totp|2fa|mfa|two.?factor|multi.?factor|authenticator|sicherheitscode|bestätigungscode|einmalcode|verification.?code)/.test(a)) return true;
        if (/\bcode\b/.test(a) && !/(postal|zip|promo|voucher|coupon|rabatt)/.test(a)) return true;
        if (el.maxLength === 6 && (el.inputMode === 'numeric' || t === 'tel' || t === 'number')) return true;
        return false;
    }
    function isPowReady() {
        const powInputs = Array.from(document.querySelectorAll('input[name=""eu-captcha-response""], input[name=""cf-turnstile-response""], input[name=""frc-captcha-solution""]'));
        if (powInputs.length === 0) return true;
        return powInputs.every(i => ((i.value || '').length > 0));
    }
    function findSubmitButton(anchor) {
        const form = anchor ? anchor.closest('form') : document.querySelector('form');
        const isHelperBtn = (b) => {
            const meta = ((b.innerText || b.value || '') + ' ' + (b.getAttribute('aria-label') || '') + ' ' + (b.title || '') + ' ' + (b.getAttribute('slot') || '')).toLowerCase();
            if (/(passwort anzeigen|kennwort anzeigen|show password|hide password|passwort verbergen|löschen|clear|auge|toggle)/.test(meta)) return true;
            const rawType = (b.getAttribute('type') || '').toLowerCase();
            const visibleTxt = (b.innerText || b.value || '').trim();
            if (rawType === 'button' && visibleTxt.length === 0) return true;
            return false;
        };
        const scope = form || document;
        let btn = Array.from(scope.querySelectorAll('button[type=""submit""], input[type=""submit""]')).find(b => isVisible(b) && !isHelperBtn(b)) || null;
        if (!btn && form) {
            btn = Array.from(form.querySelectorAll('button:not([type])')).find(b => isVisible(b) && !isHelperBtn(b)) || null;
        }
        if (!btn) {
            btn = Array.from(scope.querySelectorAll('button, [role=""button""]')).find(b => {
                if (!isVisible(b) || isHelperBtn(b)) return false;
                const txt = ((b.innerText || b.value || '') + ' ' + (b.getAttribute('aria-label') || '')).toLowerCase();
                return /(anmelden|login|sign in|weiter|continue|next|bestätigen|verify|submit|einloggen)/.test(txt);
            }) || null;
        }
        return { form: form, btn: btn };
    }
    function triggerSubmit(anchor) {
        const { form, btn } = findSubmitButton(anchor);
        if (btn) {
            if (btn.disabled || btn.getAttribute('aria-disabled') === 'true') return false;
            try {
                btn.focus();
                btn.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true, composed: true, button: 0, buttons: 1 }));
                btn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, composed: true, button: 0, buttons: 1 }));
                btn.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, cancelable: true, composed: true, button: 0, buttons: 0 }));
                btn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, composed: true, button: 0, buttons: 0 }));
            } catch (_) {}
            btn.click();
            return true;
        }
        if (form && typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return true;
        }
        if (anchor) {
            anchor.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
            return true;
        }
        return false;
    }

    const allInputs = Array.from(document.querySelectorAll('input')).filter(isVisible);
    const passInput = allInputs.find(el => (el.type || '').toLowerCase() === 'password') || null;
    const singleOtpInput = allInputs.find(isOtpInput) || null;
    const splitOtpInputs = allInputs.filter(el => {
        const t = (el.type || 'text').toLowerCase();
        return (t === 'text' || t === 'tel' || t === 'number') && el.maxLength === 1;
    });
    let userInput = allInputs.find(el => {
        const t = (el.type || 'text').toLowerCase();
        if (t !== 'email' && t !== 'text' && t !== 'tel') return false;
        if (el === singleOtpInput || isOtpInput(el) || el.maxLength === 1) return false;
        const a = attrStr(el);
        return t === 'email' || /(email|e-mail|user|login|account|benutzer|kennung)/.test(a);
    }) || null;
    if (!userInput && (mode === 'credentials')) {
        userInput = allInputs.find(el => {
            const t = (el.type || 'text').toLowerCase();
            return t === 'text' && el !== singleOtpInput && !isOtpInput(el) && el.maxLength !== 1 && !/(search|suche|filter)/.test(attrStr(el));
        }) || null;
    }

    let filledUser = false;
    let filledPass = false;
    let filledTotp = false;
    let submitted = false;
    let pendingPow = false;
    let anchorEl = passInput || userInput || singleOtpInput || (splitOtpInputs.length > 0 ? splitOtpInputs[splitOtpInputs.length - 1] : null);

    if (mode === 'credentials') {
        if (userInput && userVal) {
            setNativeInput(userInput, userVal);
            filledUser = true;
            anchorEl = userInput;
        }
        if (passInput && passVal) {
            setNativeInput(passInput, passVal);
            filledPass = true;
            anchorEl = passInput;
        }
    } else if (mode === 'password_only') {
        if (passInput && passVal) {
            setNativeInput(passInput, passVal);
            filledPass = true;
            anchorEl = passInput;
        }
    } else if (mode === 'totp_only') {
        if (totpVal) {
            if (singleOtpInput) {
                setNativeInput(singleOtpInput, totpVal);
                filledTotp = true;
                anchorEl = singleOtpInput;
            } else if (splitOtpInputs.length >= 6 && totpVal.length >= 6) {
                for (let i = 0; i < 6; i++) {
                    setNativeInput(splitOtpInputs[i], totpVal.charAt(i));
                }
                filledTotp = true;
                anchorEl = splitOtpInputs[5];
            }
        }
    } else if (mode === 'submit_only') {
        submitted = triggerSubmit(anchorEl);
        return {
            filledUser: false,
            filledPass: false,
            filledTotp: false,
            submitted: submitted,
            pendingPow: false,
            hasPassNow: !!passInput,
            hasOtpNow: !!(singleOtpInput || splitOtpInputs.length >= 6)
        };
    } else if (mode === 'probe') {
        const { btn } = findSubmitButton(anchorEl);
        const btnReady = !btn || (!btn.disabled && btn.getAttribute('aria-disabled') !== 'true');
        return {
            hasUser: !!userInput,
            hasPass: !!passInput,
            hasOtp: !!(singleOtpInput || splitOtpInputs.length >= 6),
            powReady: isPowReady() && btnReady,
            href: String(window.location.href || '')
        };
    }

    if (doSubmit && (filledUser || filledPass || filledTotp)) {
        if (!isPowReady()) {
            pendingPow = true;
        } else {
            submitted = triggerSubmit(anchorEl);
            if (!submitted) pendingPow = true;
        }
    }

    return {
        filledUser: filledUser,
        filledPass: filledPass,
        filledTotp: filledTotp,
        submitted: submitted,
        pendingPow: pendingPow,
        hasPassNow: !!passInput,
        hasOtpNow: !!(singleOtpInput || splitOtpInputs.length >= 6)
    };
}";

        private static Uri ValidateLoopbackWebSocketUri(string rawWsUrl)
        {
            if (string.IsNullOrWhiteSpace(rawWsUrl) || !Uri.TryCreate(rawWsUrl.Trim(), UriKind.Absolute, out var uri))
                throw new UnauthorizedAccessException("Ungültige Chrome-DevTools WebSocket-URL.");

            if (!string.Equals(uri.Scheme, "ws", StringComparison.OrdinalIgnoreCase))
                throw new UnauthorizedAccessException("CDP-Verbindungen sind ausschließlich über unverschlüsseltes lokales Loopback ws://127.0.0.1 erlaubt.");

            if (!string.IsNullOrEmpty(uri.UserInfo) || uri.Port < 1 || uri.Port > 65535)
                throw new UnauthorizedAccessException("Ungültiger Port oder UserInfo in CDP-WebSocket-URL.");

            if (string.Equals(uri.Host, "localhost", StringComparison.OrdinalIgnoreCase))
            {
                var ub = new UriBuilder(uri) { Host = "127.0.0.1" };
                return ub.Uri;
            }

            if (!string.Equals(uri.Host, "127.0.0.1", StringComparison.Ordinal))
                throw new UnauthorizedAccessException($"CDP-Endpunkt '{uri.Host}' abgelehnt (strikt 127.0.0.1 Loopback-Only).");

            return uri;
        }

        private sealed class CdpTargetCandidate
        {
            public string TargetId { get; init; } = "";
            public string Type { get; init; } = "";
            public string Url { get; init; } = "";
            public string WebSocketDebuggerUrl { get; init; } = "";
        }

        private static CdpTargetCandidate ChoosePageTargetOrFailClosed(List<CdpTargetCandidate> targets, string configuredEntryUrl)
        {
            var pages = targets
                .Where(t => string.Equals(t.Type, "page", StringComparison.OrdinalIgnoreCase))
                .ToList();

            if (pages.Count == 0)
                throw new InvalidOperationException("In Chrome wurde kein geöffneter Webseiten-Tab ('page') gefunden.");

            TryExtractConfiguredHttpsHost(configuredEntryUrl, out string exactConfiguredHost, out _);

            // 1. Prefer exact host match on https://
            foreach (var p in pages)
            {
                if (TryMatchAntiPhishingUrl(configuredEntryUrl, p.Url, out string mh, out _)
                    && string.Equals(mh, exactConfiguredHost, StringComparison.OrdinalIgnoreCase))
                {
                    return p;
                }
            }

            // 2. Direct subdomain match of same registrable base domain on https://
            foreach (var p in pages)
            {
                if (TryMatchAntiPhishingUrl(configuredEntryUrl, p.Url, out _, out _))
                    return p;
            }

            // 3. No matching tab open -> pick best candidate tab and trigger fail-closed Anti-Phishing exception!
            var fallbackTab = pages.FirstOrDefault(p =>
                    !p.Url.StartsWith("chrome://", StringComparison.OrdinalIgnoreCase) &&
                    !p.Url.StartsWith("devtools://", StringComparison.OrdinalIgnoreCase) &&
                    !p.Url.StartsWith("about:", StringComparison.OrdinalIgnoreCase))
                ?? pages[0];

            ValidateAntiPhishingTargetUrl(configuredEntryUrl, fallbackTab.Url);
            return fallbackTab;
        }

        private sealed class CdpLoopbackConnection : IAsyncDisposable
        {
            private readonly ClientWebSocket _ws;
            private int _nextId = 1;
            public string? AttachedSessionId { get; set; }

            public CdpLoopbackConnection(ClientWebSocket ws)
            {
                _ws = ws;
            }

            public int NextCommandId() => Interlocked.Increment(ref _nextId);

            public async Task<JsonObject> SendCommandPayloadBytesAsync(byte[] utf8Payload, int commandId, CancellationToken ct)
            {
                await _ws.SendAsync(new ArraySegment<byte>(utf8Payload), WebSocketMessageType.Text, endOfMessage: true, ct);
                return await ReceiveResponseByIdAsync(commandId, ct);
            }

            public async Task<JsonObject> SendSimpleCommandAsync(string method, JsonObject? @params, bool useAttachedSession, CancellationToken ct)
            {
                int id = NextCommandId();
                var req = new JsonObject
                {
                    ["id"] = id,
                    ["method"] = method,
                    ["params"] = @params ?? new JsonObject()
                };
                if (useAttachedSession && !string.IsNullOrEmpty(AttachedSessionId))
                {
                    req["sessionId"] = AttachedSessionId;
                }

                byte[] bytes = Encoding.UTF8.GetBytes(req.ToJsonString());
                return await SendCommandPayloadBytesAsync(bytes, id, ct);
            }

            private async Task<JsonObject> ReceiveResponseByIdAsync(int expectedId, CancellationToken ct)
            {
                byte[] recvBuf = new byte[16384];
                using var frameMs = new MemoryStream();

                while (!ct.IsCancellationRequested)
                {
                    frameMs.SetLength(0);
                    WebSocketReceiveResult res;
                    do
                    {
                        res = await _ws.ReceiveAsync(new ArraySegment<byte>(recvBuf), ct);
                        if (res.MessageType == WebSocketMessageType.Close)
                            throw new InvalidOperationException("Chrome DevTools WebSocket wurde unerwartet geschlossen.");

                        frameMs.Write(recvBuf, 0, res.Count);
                        if (frameMs.Length > 2 * 1024 * 1024)
                            throw new InvalidOperationException("CDP-Antwort überschreitet das 2 MB Sicherheitslimit.");
                    }
                    while (!res.EndOfMessage);

                    var node = JsonNode.Parse(frameMs.ToArray()) as JsonObject;
                    if (node == null) continue;

                    if (node["id"] != null && node["id"]!.GetValue<int>() == expectedId)
                    {
                        if (node["error"] is JsonObject errObj)
                        {
                            string errMsg = errObj["message"]?.GetValue<string>() ?? "CDP Fehler";
                            throw new InvalidOperationException($"CDP-Befehl fehlgeschlagen: {McpSecurityGuard.ScrubSecrets(errMsg)}");
                        }
                        return (node["result"] as JsonObject) ?? new JsonObject();
                    }
                }

                throw new OperationCanceledException("Zeitüberschreitung beim Warten auf Chrome DevTools Antwort.");
            }

            public async ValueTask DisposeAsync()
            {
                try
                {
                    if (_ws.State == WebSocketState.Open && !string.IsNullOrEmpty(AttachedSessionId))
                    {
                        using var detachCts = new CancellationTokenSource(TimeSpan.FromMilliseconds(500));
                        await SendSimpleCommandAsync(
                            "Target.detachFromTarget",
                            new JsonObject { ["sessionId"] = AttachedSessionId },
                            useAttachedSession: false,
                            detachCts.Token);
                    }
                }
                catch { }

                try
                {
                    if (_ws.State == WebSocketState.Open || _ws.State == WebSocketState.CloseReceived)
                    {
                        using var closeCts = new CancellationTokenSource(TimeSpan.FromMilliseconds(500));
                        await _ws.CloseAsync(WebSocketCloseStatus.NormalClosure, "done", closeCts.Token);
                    }
                }
                catch { }

                _ws.Dispose();
            }
        }

        private static async Task<CdpLoopbackConnection> ConnectAndAttachVerifiedTabAsync(
            string configuredEntryUrl,
            int? cdpPortOverride,
            CancellationToken ct)
        {
            var connectionErrors = new List<string>();
            int? activePortFromFile = null;

            // Path A: Modern Chrome DevToolsActivePort (Browser-WS -> Target.getTargets + Target.attachToTarget flatten: true)
            string activePortFile = ChromeDevToolsActivePortPath;
            if (!cdpPortOverride.HasValue && File.Exists(activePortFile))
            {
                try
                {
                    string[] lines = await File.ReadAllLinesAsync(activePortFile, ct);
                    if (lines.Length >= 2 &&
                        int.TryParse(lines[0].Trim(), out int parsedPort) &&
                        parsedPort is >= 1 and <= 65535)
                    {
                        activePortFromFile = parsedPort;
                        string wsPath = lines[1].Trim();
                        if (wsPath.StartsWith("/devtools/", StringComparison.Ordinal))
                        {
                            Uri browserWsUri = ValidateLoopbackWebSocketUri($"ws://127.0.0.1:{parsedPort}{wsPath}");
                            var ws = new ClientWebSocket();
                            ws.Options.UseDefaultCredentials = false;
                            ws.Options.Proxy = null;

                            try
                            {
                                using var connCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                                connCts.CancelAfter(TimeSpan.FromSeconds(4));
                                await ws.ConnectAsync(browserWsUri, connCts.Token);

                                var cdp = new CdpLoopbackConnection(ws);
                                try
                                {
                                    var targetsRes = await cdp.SendSimpleCommandAsync("Target.getTargets", null, useAttachedSession: false, ct);
                                    var list = new List<CdpTargetCandidate>();
                                    if (targetsRes["targetInfos"] is JsonArray arr)
                                    {
                                        foreach (var item in arr)
                                        {
                                            if (item is not JsonObject tObj) continue;
                                            list.Add(new CdpTargetCandidate
                                            {
                                                TargetId = tObj["targetId"]?.GetValue<string>() ?? "",
                                                Type = tObj["type"]?.GetValue<string>() ?? "",
                                                Url = tObj["url"]?.GetValue<string>() ?? ""
                                            });
                                        }
                                    }

                                    // Anti-Phishing target selection (throws UnauthorizedAccessException immediately if mismatched!)
                                    var chosen = ChoosePageTargetOrFailClosed(list, configuredEntryUrl);

                                    var attachRes = await cdp.SendSimpleCommandAsync(
                                        "Target.attachToTarget",
                                        new JsonObject
                                        {
                                            ["targetId"] = chosen.TargetId,
                                            ["flatten"] = true
                                        },
                                        useAttachedSession: false,
                                        ct);

                                    string sessionId = attachRes["sessionId"]?.GetValue<string>() ?? "";
                                    if (string.IsNullOrWhiteSpace(sessionId))
                                        throw new InvalidOperationException("Target.attachToTarget lieferte keine gültige sessionId.");

                                    cdp.AttachedSessionId = sessionId;
                                    return cdp;
                                }
                                catch
                                {
                                    await cdp.DisposeAsync();
                                    throw;
                                }
                            }
                            catch (UnauthorizedAccessException)
                            {
                                ws.Dispose();
                                throw; // Fail-closed immediately on Anti-Phishing block!
                            }
                            catch (Exception ex)
                            {
                                ws.Dispose();
                                connectionErrors.Add($"DevToolsActivePort({parsedPort}): {ex.Message}");
                            }
                        }
                    }
                }
                catch (UnauthorizedAccessException)
                {
                    throw;
                }
                catch (Exception ex)
                {
                    connectionErrors.Add($"DevToolsActivePort-Read: {ex.Message}");
                }
            }

            // Path B: HTTP Loopback /json/list Fallback (127.0.0.1 ONLY)
            var candidatePorts = new List<int>();
            if (cdpPortOverride.HasValue)
                candidatePorts.Add(cdpPortOverride.Value);
            if (activePortFromFile.HasValue && !candidatePorts.Contains(activePortFromFile.Value))
                candidatePorts.Add(activePortFromFile.Value);
            int defaultPort = TestDefaultCdpPortOverride ?? 9222;
            if (!candidatePorts.Contains(defaultPort))
                candidatePorts.Add(defaultPort);

            using var http = McpSecurityGuard.CreateHardenedApiClient(TimeSpan.FromSeconds(3));
            foreach (int port in candidatePorts)
            {
                if (port < 1 || port > 65535) continue;
                try
                {
                    using var resp = await http.GetAsync($"http://127.0.0.1:{port}/json/list", ct);
                    if (!resp.IsSuccessStatusCode)
                    {
                        connectionErrors.Add($"127.0.0.1:{port}/json/list -> HTTP {(int)resp.StatusCode}");
                        continue;
                    }

                    string body = await resp.Content.ReadAsStringAsync(ct);
                    using var doc = JsonDocument.Parse(body, new JsonDocumentOptions { MaxDepth = 12 });
                    if (doc.RootElement.ValueKind != JsonValueKind.Array)
                        continue;

                    var targets = new List<CdpTargetCandidate>();
                    foreach (var el in doc.RootElement.EnumerateArray())
                    {
                        targets.Add(new CdpTargetCandidate
                        {
                            TargetId = el.TryGetProperty("id", out var idP) ? (idP.GetString() ?? "") : "",
                            Type = el.TryGetProperty("type", out var tP) ? (tP.GetString() ?? "") : "",
                            Url = el.TryGetProperty("url", out var uP) ? (uP.GetString() ?? "") : "",
                            WebSocketDebuggerUrl = el.TryGetProperty("webSocketDebuggerUrl", out var wsP) ? (wsP.GetString() ?? "") : ""
                        });
                    }

                    var chosen = ChoosePageTargetOrFailClosed(targets, configuredEntryUrl);
                    if (string.IsNullOrWhiteSpace(chosen.WebSocketDebuggerUrl))
                        throw new InvalidOperationException("Ziel-Tab in /json/list besitzt keine webSocketDebuggerUrl (evtl. DevTools bereits verbunden).");

                    Uri pageWsUri = ValidateLoopbackWebSocketUri(chosen.WebSocketDebuggerUrl);
                    var ws = new ClientWebSocket();
                    ws.Options.UseDefaultCredentials = false;
                    ws.Options.Proxy = null;

                    try
                    {
                        await ws.ConnectAsync(pageWsUri, ct);
                        return new CdpLoopbackConnection(ws);
                    }
                    catch
                    {
                        ws.Dispose();
                        throw;
                    }
                }
                catch (UnauthorizedAccessException)
                {
                    throw; // Anti-Phishing block must never fall through!
                }
                catch (Exception ex)
                {
                    connectionErrors.Add($"127.0.0.1:{port}: {ex.Message}");
                }
            }

            throw new InvalidOperationException(
                "Lokaler Chrome-Debugging-Endpunkt auf 127.0.0.1 nicht erreichbar. Bitte in Chrome 'chrome://inspect/#remote-debugging' aktivieren (oder Chrome mit --remote-debugging-port=9222 starten). Details: " +
                string.Join(" | ", connectionErrors));
        }

        private static async Task<string> VerifyLiveWindowLocationAsync(CdpLoopbackConnection cdp, string configuredEntryUrl, CancellationToken ct)
        {
            var evalRes = await cdp.SendSimpleCommandAsync(
                "Runtime.evaluate",
                new JsonObject
                {
                    ["expression"] = "String(window.location.href || '')",
                    ["returnByValue"] = true
                },
                useAttachedSession: true,
                ct);

            string liveHref = evalRes["result"]?["value"]?.GetValue<string>() ?? "";
            return ValidateAntiPhishingTargetUrl(configuredEntryUrl, liveHref);
        }

        private static async Task<string> GetWindowObjectIdAsync(CdpLoopbackConnection cdp, CancellationToken ct)
        {
            var evalRes = await cdp.SendSimpleCommandAsync(
                "Runtime.evaluate",
                new JsonObject
                {
                    ["expression"] = "window",
                    ["returnByValue"] = false
                },
                useAttachedSession: true,
                ct);

            string objId = evalRes["result"]?["objectId"]?.GetValue<string>() ?? "";
            if (string.IsNullOrWhiteSpace(objId))
                throw new InvalidOperationException("Konnte window-Objekt im Ziel-Tab nicht referenzieren.");
            return objId;
        }

        private static async Task<JsonObject> InvokeBlindDomAutofillPassAsync(
            CdpLoopbackConnection cdp,
            string windowObjectId,
            string mode,
            ReadOnlyMemory<byte> usernameUtf8,
            ReadOnlyMemory<byte> passwordUtf8,
            ReadOnlyMemory<byte> totpUtf8,
            bool doSubmit,
            CancellationToken ct)
        {
            int cmdId = cdp.NextCommandId();
            byte[]? frameBytes = null;

            using var ms = new MemoryStream(2048);
            try
            {
                using (var w = new Utf8JsonWriter(ms))
                {
                    w.WriteStartObject();
                    w.WriteNumber("id", cmdId);
                    if (!string.IsNullOrEmpty(cdp.AttachedSessionId))
                        w.WriteString("sessionId", cdp.AttachedSessionId);
                    w.WriteString("method", "Runtime.callFunctionOn");
                    w.WriteStartObject("params");
                    w.WriteString("objectId", windowObjectId);
                    w.WriteString("functionDeclaration", DomAutofillFunctionDeclaration);
                    w.WriteBoolean("returnByValue", true);
                    w.WriteBoolean("awaitPromise", false);
                    w.WriteStartArray("arguments");

                    w.WriteStartObject(); w.WriteString("value", mode); w.WriteEndObject();
                    w.WriteStartObject(); w.WriteString("value", usernameUtf8.Span); w.WriteEndObject();
                    w.WriteStartObject(); w.WriteString("value", passwordUtf8.Span); w.WriteEndObject();
                    w.WriteStartObject(); w.WriteString("value", totpUtf8.Span); w.WriteEndObject();
                    w.WriteStartObject(); w.WriteBoolean("value", doSubmit); w.WriteEndObject();

                    w.WriteEndArray();
                    w.WriteEndObject();
                    w.WriteEndObject();
                    w.Flush();
                }

                frameBytes = ms.ToArray();
                if (ms.TryGetBuffer(out ArraySegment<byte> seg) && seg.Array != null)
                    CryptographicOperations.ZeroMemory(seg.Array);

                var res = await cdp.SendCommandPayloadBytesAsync(frameBytes, cmdId, ct);
                return (res["result"]?["value"] as JsonObject) ?? new JsonObject();
            }
            finally
            {
                if (frameBytes != null)
                    CryptographicOperations.ZeroMemory(frameBytes);
            }
        }

        public static async Task<TresorBrowserLoginResultDto> BrowserLoginAsync(
            string titleOrId,
            bool submit = false,
            bool totpOnly = false,
            int? cdpPortOverride = null,
            CancellationToken ct = default)
        {
            using var lease = ResolveEntrySecretLease(titleOrId, requireApiToken: false);

            if (totpOnly && lease.TotpSeedOrConfigUtf8.Length == 0)
                throw new InvalidOperationException($"Für den Tresor-Eintrag '{lease.Title}' ist kein 2FA/TOTP-Secret hinterlegt.");

            using var timeoutCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            timeoutCts.CancelAfter(TimeSpan.FromSeconds(18));
            var token = timeoutCts.Token;

            await using var cdp = await ConnectAndAttachVerifiedTabAsync(lease.Url, cdpPortOverride, token);

            // Live window.location.href Anti-Phishing Verification BEFORE touching any input!
            string matchedHost = await VerifyLiveWindowLocationAsync(cdp, lease.Url, token);

            bool filledUser = false;
            bool filledPass = false;
            bool filledTotp = false;
            bool anySubmitted = false;

            async Task WaitForInlinePowAndSubmitAsync()
            {
                for (int powPoll = 0; powPoll < 14; powPoll++)
                {
                    await Task.Delay(400, token);
                    matchedHost = await VerifyLiveWindowLocationAsync(cdp, lease.Url, token);
                    string powWinId = await GetWindowObjectIdAsync(cdp, token);
                    var powProbe = await InvokeBlindDomAutofillPassAsync(
                        cdp, powWinId, "probe",
                        ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty,
                        false, token);

                    if (powProbe["powReady"]?.GetValue<bool>() == true || powPoll == 13)
                    {
                        var subRes = await InvokeBlindDomAutofillPassAsync(
                            cdp, powWinId, "submit_only",
                            ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty,
                            true, token);
                        if (subRes["submitted"]?.GetValue<bool>() == true) anySubmitted = true;
                        break;
                    }
                }
            }

            async Task<bool> TryFillFreshTotpAsync(bool submitOtp)
            {
                if (lease.TotpSeedOrConfigUtf8.Length == 0)
                    return false;

                if (!TryGenerateTotpCode(lease.TotpSeedOrConfigUtf8, DateTimeOffset.UtcNow, out string otpDigits, out int remSec))
                    throw new InvalidOperationException($"Ungültiges TOTP-Secret im Tresor-Eintrag '{lease.Title}'.");

                if (remSec <= 1)
                {
                    await Task.Delay(1150, token);
                    if (!TryGenerateTotpCode(lease.TotpSeedOrConfigUtf8, DateTimeOffset.UtcNow, out otpDigits, out _))
                        return false;
                }

                matchedHost = await VerifyLiveWindowLocationAsync(cdp, lease.Url, token);
                string winObjId = await GetWindowObjectIdAsync(cdp, token);

                byte[] otpBytes = Encoding.ASCII.GetBytes(otpDigits);
                try
                {
                    var otpRes = await InvokeBlindDomAutofillPassAsync(
                        cdp,
                        winObjId,
                        "totp_only",
                        ReadOnlyMemory<byte>.Empty,
                        ReadOnlyMemory<byte>.Empty,
                        otpBytes,
                        submitOtp,
                        token);

                    bool ok = otpRes["filledTotp"]?.GetValue<bool>() == true;
                    if (ok) filledTotp = true;
                    if (otpRes["submitted"]?.GetValue<bool>() == true) anySubmitted = true;
                    else if (ok && submitOtp && otpRes["pendingPow"]?.GetValue<bool>() == true)
                    {
                        await WaitForInlinePowAndSubmitAsync();
                    }
                    return ok;
                }
                finally
                {
                    CryptographicOperations.ZeroMemory(otpBytes);
                }
            }

            if (totpOnly)
            {
                await TryFillFreshTotpAsync(submit);
            }
            else
            {
                string winObjId = await GetWindowObjectIdAsync(cdp, token);
                var pass1 = await InvokeBlindDomAutofillPassAsync(
                    cdp,
                    winObjId,
                    "credentials",
                    lease.UsernameUtf8,
                    lease.PasswordUtf8,
                    ReadOnlyMemory<byte>.Empty,
                    submit,
                    token);

                if (pass1["filledUser"]?.GetValue<bool>() == true) filledUser = true;
                if (pass1["filledPass"]?.GetValue<bool>() == true) filledPass = true;
                if (pass1["submitted"]?.GetValue<bool>() == true) anySubmitted = true;

                if (submit && !anySubmitted && pass1["pendingPow"]?.GetValue<bool>() == true)
                {
                    await WaitForInlinePowAndSubmitAsync();
                }

                // Multi-step Step 2: Email was filled & submitted, wait for password input to appear
                if (filledUser && !filledPass && submit && lease.PasswordUtf8.Length > 0)
                {
                    for (int poll = 0; poll < 10; poll++)
                    {
                        await Task.Delay(400, token);
                        matchedHost = await VerifyLiveWindowLocationAsync(cdp, lease.Url, token);
                        string stepWinId = await GetWindowObjectIdAsync(cdp, token);
                        var probe = await InvokeBlindDomAutofillPassAsync(
                            cdp, stepWinId, "probe",
                            ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty,
                            false, token);

                        if (probe["hasPass"]?.GetValue<bool>() == true)
                        {
                            var pass2 = await InvokeBlindDomAutofillPassAsync(
                                cdp, stepWinId, "password_only",
                                ReadOnlyMemory<byte>.Empty, lease.PasswordUtf8, ReadOnlyMemory<byte>.Empty,
                                submit, token);
                            if (pass2["filledPass"]?.GetValue<bool>() == true) filledPass = true;
                            if (pass2["submitted"]?.GetValue<bool>() == true) anySubmitted = true;
                            if (submit && !anySubmitted && pass2["pendingPow"]?.GetValue<bool>() == true)
                            {
                                await WaitForInlinePowAndSubmitAsync();
                            }
                            break;
                        }
                    }
                }

                // Multi-step Step 3 (or direct 2FA screen): Fill TOTP if entry has TOTP
                if (lease.TotpSeedOrConfigUtf8.Length > 0)
                {
                    if (!filledUser && !filledPass && pass1["hasOtpNow"]?.GetValue<bool>() == true)
                    {
                        await TryFillFreshTotpAsync(submit);
                    }
                    else if (filledPass && submit)
                    {
                        for (int poll = 0; poll < 10; poll++)
                        {
                            await Task.Delay(420, token);
                            matchedHost = await VerifyLiveWindowLocationAsync(cdp, lease.Url, token);
                            string stepWinId = await GetWindowObjectIdAsync(cdp, token);
                            var probe = await InvokeBlindDomAutofillPassAsync(
                                cdp, stepWinId, "probe",
                                ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty, ReadOnlyMemory<byte>.Empty,
                                false, token);

                            if (probe["hasOtp"]?.GetValue<bool>() == true)
                            {
                                await TryFillFreshTotpAsync(submit);
                                break;
                            }
                        }
                    }
                    else if (pass1["hasOtpNow"]?.GetValue<bool>() == true)
                    {
                        await TryFillFreshTotpAsync(submit);
                    }
                }
            }

            string status = (filledUser || filledPass || filledTotp) ? "ok" : "no_matching_inputs";
            McpSecurityGuard.AppendAuditLog(
                "tresor_browser_login",
                $"host={matchedHost},u={filledUser},p={filledPass},totp={filledTotp},sub={anySubmitted}",
                "ALLOW");

            return new TresorBrowserLoginResultDto
            {
                Status = status,
                MatchedHost = matchedHost,
                FilledUsername = filledUser,
                FilledPassword = filledPass,
                FilledTotp = filledTotp,
                Submitted = anySubmitted
            };
        }

        // =====================================================================
        // 4. OFFICIAL MITTWALD mSTUDIO REST API v2 BROKER (WITH LIVE-AIR-GAP)
        // =====================================================================
        public static string ValidateSafeMittwaldId(string? rawId, string paramName)
        {
            string clean = (rawId ?? "").Trim();
            if (string.IsNullOrWhiteSpace(clean) || !SafeMittwaldResourceIdRegex.IsMatch(clean) || clean.Contains("..", StringComparison.Ordinal))
                throw new ArgumentException($"Ungültige oder fehlende Mittwald-{paramName} ('{McpSecurityGuard.ScrubSecrets(clean)}').");
            return clean;
        }

        private static Uri BuildPinnedMittwaldUri(string v2RelativePath)
        {
            if (string.IsNullOrWhiteSpace(v2RelativePath) || !v2RelativePath.StartsWith("/v2/", StringComparison.Ordinal) || v2RelativePath.Contains("..", StringComparison.Ordinal))
                throw new UnauthorizedAccessException("Ungültiger Mittwald API-v2 Pfad blockiert.");

            var uri = new Uri("https://api.mittwald.de" + v2RelativePath, UriKind.Absolute);
            if (!string.Equals(uri.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase)
                || !string.Equals(uri.Host, "api.mittwald.de", StringComparison.OrdinalIgnoreCase)
                || !string.IsNullOrEmpty(uri.UserInfo)
                || uri.Port != 443)
            {
                throw new UnauthorizedAccessException("SSRF-Schutz: Mittwald API-Broker kommuniziert ausschließlich per HTTPS mit api.mittwald.de:443.");
            }
            return uri;
        }

        private static bool ContainsForbiddenLiveIndicator(string? text)
        {
            if (string.IsNullOrWhiteSpace(text)) return false;
            string s = text.Trim();
            if (s.Contains("seelenfunke-live", StringComparison.OrdinalIgnoreCase)
                || s.Contains("steinhauer-invest", StringComparison.OrdinalIgnoreCase)
                || s.Contains("a-bycqrf", StringComparison.OrdinalIgnoreCase)
                || s.Contains("a-s4sdym", StringComparison.OrdinalIgnoreCase)
                || s.Contains("93f33f15-d27c-4d15-9f85-155eb9adace8", StringComparison.OrdinalIgnoreCase)
                || s.Contains("2be54dff-9651-416e-8aba-79a5b63749ff", StringComparison.OrdinalIgnoreCase)
                || s.Contains("mysql_fg9zlg", StringComparison.OrdinalIgnoreCase)
                || s.Contains("production", StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }

            if (Regex.IsMatch(s, @"(?<![A-Za-z0-9])(live|prod)(?![A-Za-z0-9])", RegexOptions.IgnoreCase))
                return true;

            // Block bare mein-seelenfunke.de (live) while allowing stage.mein-seelenfunke.de
            foreach (Match m in Regex.Matches(s, @"(?i)(?:[a-z0-9\-]+\.)*mein-seelenfunke\.de"))
            {
                if (!m.Value.StartsWith("stage.", StringComparison.OrdinalIgnoreCase) &&
                    !s.Contains("kontakt@mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase))
                {
                    return true;
                }
            }
            return false;
        }

        private static bool HasExplicitStageMarker(string? text)
        {
            if (string.IsNullOrWhiteSpace(text)) return false;
            return text.Contains("stage", StringComparison.OrdinalIgnoreCase)
                || text.Contains("worker-stage", StringComparison.OrdinalIgnoreCase)
                || text.Contains("twilio-bridge", StringComparison.OrdinalIgnoreCase);
        }

        public static string VerifyAppJsonIsStrictlyStage(JsonElement appObj, string requestedAppId)
        {
            if (appObj.ValueKind != JsonValueKind.Object)
                throw new UnauthorizedAccessException("MITTWALD LIVE-AIR-GAP SPERRE: Vorab-Prüfung der App lieferte kein gültiges Objekt.");

            string resolvedId = appObj.TryGetProperty("id", out var idP) ? (idP.GetString() ?? requestedAppId) : requestedAppId;
            string shortId = appObj.TryGetProperty("shortId", out var shP) ? (shP.GetString() ?? "") : "";
            string description = appObj.TryGetProperty("description", out var dP) ? (dP.GetString() ?? "") : "";
            string installPath = appObj.TryGetProperty("installationPath", out var ipP) ? (ipP.GetString() ?? "") : "";
            string docRoot = appObj.TryGetProperty("customDocumentRoot", out var drP) ? (drP.GetString() ?? "") : "";

            string combined = $"{requestedAppId} | {resolvedId} | {shortId} | {description} | {installPath} | {docRoot}";
            if (ContainsForbiddenLiveIndicator(combined))
            {
                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"app={shortId}:{description}", "DENY:live_airgap_blocked");
                throw new UnauthorizedAccessException(
                    $"MITTWALD LIVE-AIR-GAP SPERRE: Mutation auf Live-/Production-App ('{McpSecurityGuard.ScrubSecrets(description)}' / '{shortId}') ist technisch gesperrt!");
            }

            bool isPinnedStageShortId = PinnedStageAppShortIds.Contains(shortId) || PinnedStageAppShortIds.Contains(requestedAppId);
            bool hasStageText = HasExplicitStageMarker(description) || HasExplicitStageMarker(installPath);

            if (!isPinnedStageShortId && !hasStageText)
            {
                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"app={shortId}:{description}", "DENY:unverified_stage_app");
                throw new UnauthorizedAccessException(
                    $"MITTWALD LIVE-AIR-GAP SPERRE: App '{McpSecurityGuard.ScrubSecrets(requestedAppId)}' ('{McpSecurityGuard.ScrubSecrets(description)}') konnte nicht eindeutig als Stage/Worker-Stage/Twilio-Bridge verifiziert werden.");
            }

            return ValidateSafeMittwaldId(resolvedId, "appId");
        }

        private static async Task<(int StatusCode, string CleanBody, JsonNode? Node)> SendMittwaldRequestAsync(
            HttpClient client,
            HttpMethod method,
            string v2Path,
            ReadOnlyMemory<byte> apiTokenUtf8,
            CancellationToken ct,
            ReadOnlyMemory<byte>? jsonBodyUtf8 = null,
            ReadOnlyMemory<char>? extraSecretCharsToScrub = null)
        {
            string currentPath = v2Path;
            char[] tokenChars = new char[Encoding.UTF8.GetCharCount(apiTokenUtf8.Span)];
            Encoding.UTF8.GetChars(apiTokenUtf8.Span, tokenChars);
            string tokenStr = new string(tokenChars);

            try
            {
                for (int hop = 0; hop < 4; hop++)
                {
                    Uri uri = BuildPinnedMittwaldUri(currentPath);
                    using var req = new HttpRequestMessage(method, uri);
                    req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", tokenStr);
                    req.Headers.TryAddWithoutValidation("x-access-token", tokenStr);
                    req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                    byte[]? reqBodyCopy = null;
                    try
                    {
                        if (jsonBodyUtf8.HasValue)
                        {
                            reqBodyCopy = jsonBodyUtf8.Value.ToArray();
                            var content = new ByteArrayContent(reqBodyCopy);
                            content.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };
                            req.Content = content;
                        }

                        using var resp = await client.SendAsync(req, ct);
                        int code = (int)resp.StatusCode;

                        // Follow Mittwald shortId -> UUID 301/302/307/308 redirects ONLY if strictly on https://api.mittwald.de:443/v2/...
                        if (code is 301 or 302 or 307 or 308 && resp.Headers.Location != null && hop < 3)
                        {
                            Uri loc = resp.Headers.Location;
                            string nextRelPath;
                            if (!loc.IsAbsoluteUri)
                            {
                                string rawRel = loc.OriginalString;
                                nextRelPath = rawRel.StartsWith("/", StringComparison.Ordinal) ? rawRel : "/" + rawRel;
                            }
                            else
                            {
                                if (!string.Equals(loc.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase)
                                    || !string.Equals(loc.Host, "api.mittwald.de", StringComparison.OrdinalIgnoreCase)
                                    || !string.IsNullOrEmpty(loc.UserInfo)
                                    || loc.Port != 443)
                                {
                                    throw new UnauthorizedAccessException("SSRF-Schutz: Mittwald API-Redirect auf fremden Host blockiert.");
                                }
                                nextRelPath = loc.PathAndQuery;
                            }

                            BuildPinnedMittwaldUri(nextRelPath);
                            currentPath = nextRelPath;
                            continue;
                        }

                        string rawBody = resp.Content != null ? await resp.Content.ReadAsStringAsync(ct) : "";

                        if (!string.IsNullOrEmpty(tokenStr) && rawBody.Contains(tokenStr, StringComparison.Ordinal))
                            rawBody = rawBody.Replace(tokenStr, "[REDACTED-BY-FUNKENWERK-DLP]", StringComparison.Ordinal);

                        if (extraSecretCharsToScrub.HasValue && extraSecretCharsToScrub.Value.Length > 0 && rawBody.Length > 0)
                        {
                            int idx = rawBody.AsSpan().IndexOf(extraSecretCharsToScrub.Value.Span, StringComparison.Ordinal);
                            while (idx >= 0)
                            {
                                rawBody = string.Concat(
                                    rawBody.AsSpan(0, idx),
                                    "[REDACTED-BY-FUNKENWERK-DLP]",
                                    rawBody.AsSpan(idx + extraSecretCharsToScrub.Value.Length));
                                idx = rawBody.AsSpan().IndexOf(extraSecretCharsToScrub.Value.Span, StringComparison.Ordinal);
                            }
                        }

                        string cleanBody = McpSecurityGuard.ScrubSecrets(rawBody);
                        JsonNode? parsed = null;
                        if (!string.IsNullOrWhiteSpace(cleanBody))
                        {
                            try
                            {
                                parsed = JsonNode.Parse(cleanBody, documentOptions: new JsonDocumentOptions { MaxDepth = 24 });
                            }
                            catch
                            {
                                parsed = JsonValue.Create(cleanBody);
                            }
                        }

                        return (code, cleanBody, parsed);
                    }
                    finally
                    {
                        if (reqBodyCopy != null)
                            CryptographicOperations.ZeroMemory(reqBodyCopy);
                    }
                }

                throw new InvalidOperationException("Zu viele HTTP-Weiterleitungen bei Mittwald API v2.");
            }
            finally
            {
                Array.Clear(tokenChars, 0, tokenChars.Length);
            }
        }

        private static async Task<string> VerifyAndResolveStageAppIdAsync(
            HttpClient client,
            string requestedAppId,
            string? optionalProjectId,
            ReadOnlyMemory<byte> apiTokenUtf8,
            CancellationToken ct)
        {
            string safeAppId = ValidateSafeMittwaldId(requestedAppId, "appId");
            if (ContainsForbiddenLiveIndicator(safeAppId))
            {
                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"restart_stage_app:{safeAppId}", "DENY:live_airgap_blocked");
                throw new UnauthorizedAccessException($"MITTWALD LIVE-AIR-GAP SPERRE: App-ID '{safeAppId}' ist als Live/Production gesperrt.");
            }

            var (status, _, node) = await SendMittwaldRequestAsync(client, HttpMethod.Get, $"/v2/app-installations/{safeAppId}", apiTokenUtf8, ct);
            if (status is >= 200 and < 300 && node != null)
            {
                using var doc = JsonDocument.Parse(node.ToJsonString());
                return VerifyAppJsonIsStrictlyStage(doc.RootElement, safeAppId);
            }

            // If user passed a pinned shortId (e.g. a-lc6tkm) and Mittwald expects UUID, resolve via project app-installations
            if (PinnedStageAppShortIds.Contains(safeAppId))
            {
                var projectIds = new List<string>();
                if (!string.IsNullOrWhiteSpace(optionalProjectId))
                {
                    projectIds.Add(ValidateSafeMittwaldId(optionalProjectId, "projectId"));
                }
                else
                {
                    var (pStatus, _, pNode) = await SendMittwaldRequestAsync(client, HttpMethod.Get, "/v2/projects", apiTokenUtf8, ct);
                    if (pStatus is >= 200 and < 300 && pNode is JsonArray pArr)
                    {
                        foreach (var pItem in pArr)
                        {
                            string? pid = pItem?["id"]?.GetValue<string>();
                            if (!string.IsNullOrWhiteSpace(pid) && SafeMittwaldResourceIdRegex.IsMatch(pid))
                                projectIds.Add(pid);
                        }
                    }
                }

                foreach (string pid in projectIds)
                {
                    var (aStatus, _, aNode) = await SendMittwaldRequestAsync(client, HttpMethod.Get, $"/v2/projects/{pid}/app-installations", apiTokenUtf8, ct);
                    if (aStatus is >= 200 and < 300 && aNode is JsonArray aArr)
                    {
                        foreach (var appItem in aArr)
                        {
                            string? sh = appItem?["shortId"]?.GetValue<string>();
                            if (string.Equals(sh, safeAppId, StringComparison.OrdinalIgnoreCase) && appItem != null)
                            {
                                using var doc = JsonDocument.Parse(appItem.ToJsonString());
                                return VerifyAppJsonIsStrictlyStage(doc.RootElement, safeAppId);
                            }
                        }
                    }
                }
            }

            throw new InvalidOperationException($"Vorab-Prüfung von App '{safeAppId}' fehlgeschlagen (HTTP {status}). Fail-Closed Abbruch.");
        }

        private static async Task VerifyStageCronjobPreFlightAsync(
            HttpClient client,
            string cronjobId,
            ReadOnlyMemory<byte> apiTokenUtf8,
            CancellationToken ct)
        {
            string safeCronId = ValidateSafeMittwaldId(cronjobId, "cronjobId");
            if (ContainsForbiddenLiveIndicator(safeCronId))
            {
                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"execute_stage_cronjob:{safeCronId}", "DENY:live_airgap_blocked");
                throw new UnauthorizedAccessException($"MITTWALD LIVE-AIR-GAP SPERRE: Cronjob-ID '{safeCronId}' ist als Live/Production gesperrt.");
            }

            var (status, cleanJson, node) = await SendMittwaldRequestAsync(client, HttpMethod.Get, $"/v2/cronjobs/{safeCronId}", apiTokenUtf8, ct);
            if (status is < 200 or >= 300 || node is not JsonObject cronObj)
                throw new InvalidOperationException($"Vorab-Prüfung von Cronjob '{safeCronId}' fehlgeschlagen (HTTP {status}). Fail-Closed Abbruch.");

            string desc = cronObj["description"]?.GetValue<string>() ?? "";
            string shortId = cronObj["shortId"]?.GetValue<string>() ?? "";
            string appId = (cronObj["appId"] ?? cronObj["appInstallationId"])?.GetValue<string>() ?? "";
            string destJson = cronObj["destination"]?.ToJsonString() ?? "";

            string combined = $"{safeCronId} | {shortId} | {desc} | {destJson}";
            if (ContainsForbiddenLiveIndicator(combined))
            {
                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"cronjob={shortId}:{desc}", "DENY:live_airgap_blocked");
                throw new UnauthorizedAccessException(
                    $"MITTWALD LIVE-AIR-GAP SPERRE: Ausführung von Live-/Production-Cronjob ('{McpSecurityGuard.ScrubSecrets(desc)}') ist technisch gesperrt!");
            }

            bool verifiedByOwningApp = false;
            if (!string.IsNullOrWhiteSpace(appId) && SafeMittwaldResourceIdRegex.IsMatch(appId))
            {
                if (PinnedStageAppShortIds.Contains(appId))
                {
                    verifiedByOwningApp = true;
                }
                else
                {
                    var (appStatus, _, appNode) = await SendMittwaldRequestAsync(client, HttpMethod.Get, $"/v2/app-installations/{appId}", apiTokenUtf8, ct);
                    if (appStatus is >= 200 and < 300 && appNode != null)
                    {
                        using var appDoc = JsonDocument.Parse(appNode.ToJsonString());
                        VerifyAppJsonIsStrictlyStage(appDoc.RootElement, appId);
                        verifiedByOwningApp = true;
                    }
                }
            }

            bool verifiedByText = HasExplicitStageMarker(desc) || HasExplicitStageMarker(destJson);
            if (!verifiedByOwningApp && !verifiedByText)
            {
                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"cronjob={safeCronId}:{desc}", "DENY:unverified_stage_cronjob");
                throw new UnauthorizedAccessException(
                    $"MITTWALD LIVE-AIR-GAP SPERRE: Cronjob '{safeCronId}' konnte im Vorab-Check nicht eindeutig einer Stage-App zugeordnet werden.");
            }
        }

        public static async Task<MittwaldBrokerResultDto> ExecuteMittwaldApiPresetAsync(
            string preset,
            string? entryTitleOrId = null,
            string? projectId = null,
            string? appId = null,
            string? cronjobId = null,
            CancellationToken ct = default)
        {
            string normPreset = (preset ?? "").Trim().ToLowerInvariant();
            if (!AllowedMittwaldPresets.Contains(normPreset, StringComparer.Ordinal))
            {
                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", normPreset, "DENY:unknown_preset");
                throw new UnauthorizedAccessException(
                    $"Unbekanntes Mittwald-Preset '{McpSecurityGuard.ScrubSecrets(preset)}'. Erlaubt: {string.Join(", ", AllowedMittwaldPresets)}.");
            }

            // Fail-closed Live-Air-Gap Pre-Check BEFORE even touching the DPAPI broker vault!
            if (normPreset == "restart_stage_app")
            {
                string checkApp = ValidateSafeMittwaldId(appId, "appId");
                if (ContainsForbiddenLiveIndicator(checkApp))
                {
                    McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"restart_stage_app:{checkApp}", "DENY:live_airgap_blocked");
                    throw new UnauthorizedAccessException($"MITTWALD LIVE-AIR-GAP SPERRE: App-ID '{checkApp}' ist als Live/Production gesperrt.");
                }
            }
            else if (normPreset == "execute_stage_cronjob")
            {
                string checkCron = ValidateSafeMittwaldId(cronjobId, "cronjobId");
                if (ContainsForbiddenLiveIndicator(checkCron))
                {
                    McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"execute_stage_cronjob:{checkCron}", "DENY:live_airgap_blocked");
                    throw new UnauthorizedAccessException($"MITTWALD LIVE-AIR-GAP SPERRE: Cronjob-ID '{checkCron}' ist als Live/Production gesperrt.");
                }
            }

            string lookupQuery = string.IsNullOrWhiteSpace(entryTitleOrId) ? "Mittwald" : entryTitleOrId!.Trim();
            using var lease = ResolveEntrySecretLease(lookupQuery, requireApiToken: true);

            bool ownsClient = TestMittwaldHttpClientOverride == null;
            HttpClient client = TestMittwaldHttpClientOverride ?? McpSecurityGuard.CreateHardenedApiClient(TimeSpan.FromSeconds(25));

            try
            {
                HttpMethod method;
                string v2Path;

                switch (normPreset)
                {
                    case "user_self":
                        method = HttpMethod.Get;
                        v2Path = "/v2/users/self";
                        break;

                    case "list_customers":
                        method = HttpMethod.Get;
                        v2Path = "/v2/customers";
                        break;

                    case "list_projects":
                        method = HttpMethod.Get;
                        v2Path = "/v2/projects";
                        break;

                    case "list_ai_hostings":
                        method = HttpMethod.Get;
                        v2Path = "/v2/ai-hostings";
                        break;

                    case "project_audit":
                    {
                        string pid = ValidateSafeMittwaldId(projectId, "projectId");
                        var auditObj = new JsonObject();

                        var (projStatus, _, projNode) = await SendMittwaldRequestAsync(client, HttpMethod.Get, $"/v2/projects/{pid}", lease.ApiTokenUtf8, ct);
                        auditObj["project_http"] = projStatus;
                        auditObj["project"] = projNode?.DeepClone();

                        string resolvedUuid = (projNode as JsonObject)?["id"]?.GetValue<string>() ?? pid;
                        resolvedUuid = ValidateSafeMittwaldId(resolvedUuid, "projectId");

                        (string Key, string Path)[] readEndpoints =
                        {
                            ("domains_query", $"/v2/domains?projectId={Uri.EscapeDataString(resolvedUuid)}"),
                            ("dns_zones", $"/v2/projects/{resolvedUuid}/dns-zones"),
                            ("mysql_databases", $"/v2/projects/{resolvedUuid}/mysql-databases"),
                            ("redis_databases", $"/v2/projects/{resolvedUuid}/redis-databases"),
                            ("stacks", $"/v2/projects/{resolvedUuid}/stacks"),
                            ("services", $"/v2/projects/{resolvedUuid}/services"),
                            ("volumes", $"/v2/projects/{resolvedUuid}/volumes"),
                            ("ssh_users", $"/v2/projects/{resolvedUuid}/ssh-users"),
                            ("sftp_users", $"/v2/projects/{resolvedUuid}/sftp-users"),
                            ("mail_addresses", $"/v2/projects/{resolvedUuid}/mail-addresses"),
                            ("delivery_boxes", $"/v2/projects/{resolvedUuid}/delivery-boxes"),
                            ("mail_settings", $"/v2/projects/{resolvedUuid}/mail-settings"),
                            ("backup_schedules", $"/v2/projects/{resolvedUuid}/backup-schedules"),
                            ("storage_stats", $"/v2/projects/{resolvedUuid}/storage-space-statistics"),
                            ("project_ai_hostings", $"/v2/projects/{resolvedUuid}/ai-hostings")
                        };

                        foreach (var (key, epPath) in readEndpoints)
                        {
                            var (st, body, n) = await SendMittwaldRequestAsync(client, HttpMethod.Get, epPath, lease.ApiTokenUtf8, ct);
                            if (st is >= 200 and < 300 && n != null)
                            {
                                if ((key == "ssh_users" || key == "sftp_users") && n is JsonArray usrArr)
                                {
                                    foreach (var uItem in usrArr)
                                    {
                                        if (uItem is JsonObject uObj && uObj["publicKeys"] is JsonArray pkArr)
                                        {
                                            uObj["publicKeysCount"] = pkArr.Count;
                                            var comments = new JsonArray();
                                            foreach (var pk in pkArr)
                                            {
                                                if (pk is JsonObject pkObj && pkObj["comment"] != null)
                                                    comments.Add(pkObj["comment"]!.DeepClone());
                                            }
                                            uObj["publicKeyComments"] = comments;
                                            uObj.Remove("publicKeys");
                                        }
                                    }
                                }
                                auditObj[key] = n.DeepClone();
                            }
                            else
                            {
                                auditObj[key] = new JsonObject { ["_http_status"] = st, ["_error"] = body };
                            }
                        }

                        if (auditObj["dns_zones"] is JsonArray zonesArr)
                        {
                            var zoneDetails = new JsonArray();
                            foreach (var z in zonesArr)
                            {
                                string? zid = (z as JsonObject)?["id"]?.GetValue<string>();
                                if (!string.IsNullOrWhiteSpace(zid) && SafeMittwaldResourceIdRegex.IsMatch(zid))
                                {
                                    var (zSt, _, zNode) = await SendMittwaldRequestAsync(client, HttpMethod.Get, $"/v2/dns-zones/{zid}", lease.ApiTokenUtf8, ct);
                                    if (zSt is >= 200 and < 300 && zNode != null)
                                        zoneDetails.Add(zNode.DeepClone());
                                }
                            }
                            auditObj["dns_zone_details"] = zoneDetails;
                        }

                        McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"project_audit:{resolvedUuid}", "ALLOW");
                        return new MittwaldBrokerResultDto
                        {
                            Status = "ok",
                            Preset = normPreset,
                            HttpStatus = 200,
                            EntryTitle = lease.Title,
                            Data = auditObj
                        };
                    }

                    case "list_apps":
                    {
                        string pid = ValidateSafeMittwaldId(projectId, "projectId");
                        method = HttpMethod.Get;
                        v2Path = $"/v2/projects/{pid}/app-installations";
                        break;
                    }

                    case "get_app":
                    {
                        string aid = ValidateSafeMittwaldId(appId, "appId");
                        method = HttpMethod.Get;
                        v2Path = $"/v2/app-installations/{aid}";
                        break;
                    }

                    case "list_cronjobs":
                    {
                        string pid = ValidateSafeMittwaldId(projectId, "projectId");
                        method = HttpMethod.Get;
                        v2Path = $"/v2/projects/{pid}/cronjobs";
                        break;
                    }

                    case "list_ingresses":
                    {
                        string pid = ValidateSafeMittwaldId(projectId, "projectId");
                        method = HttpMethod.Get;
                        v2Path = $"/v2/ingresses?projectId={Uri.EscapeDataString(pid)}";
                        break;
                    }

                    case "restart_stage_app":
                    {
                        string resolvedStageAppUuid = await VerifyAndResolveStageAppIdAsync(
                            client,
                            appId!,
                            projectId,
                            lease.ApiTokenUtf8,
                            ct);
                        method = HttpMethod.Post;
                        v2Path = $"/v2/app-installations/{resolvedStageAppUuid}/actions/restart";
                        break;
                    }

                    case "execute_stage_cronjob":
                    {
                        string safeCronId = ValidateSafeMittwaldId(cronjobId, "cronjobId");
                        await VerifyStageCronjobPreFlightAsync(client, safeCronId, lease.ApiTokenUtf8, ct);
                        method = HttpMethod.Post;
                        v2Path = $"/v2/cronjobs/{safeCronId}/actions/execute";
                        break;
                    }

                    default:
                        throw new UnauthorizedAccessException($"Preset '{normPreset}' gesperrt.");
                }

                var (httpStatus, cleanBody, parsedNode) = await SendMittwaldRequestAsync(client, method, v2Path, lease.ApiTokenUtf8, ct);
                if (httpStatus is < 200 or >= 300)
                {
                    McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"{normPreset}:http={httpStatus}", "ERROR");
                    throw new InvalidOperationException($"Mittwald API v2 antwortete mit HTTP {httpStatus} bei '{normPreset}': {cleanBody}");
                }

                McpSecurityGuard.AppendAuditLog("mittwald_api_broker", $"{normPreset}:http={httpStatus}", "ALLOW");
                return new MittwaldBrokerResultDto
                {
                    Status = "ok",
                    Preset = normPreset,
                    HttpStatus = httpStatus,
                    EntryTitle = lease.Title,
                    Data = parsedNode ?? new JsonObject { ["accepted"] = true }
                };
            }
            finally
            {
                if (ownsClient)
                    client.Dispose();
            }
        }

        // =====================================================================
        // 5. PINNED SEELENFUNKE-LIVE CLI PREPARATION ENGINE (CLI-ONLY, ZERO-SECRET)
        // =====================================================================
        internal static Func<string, string, Task<byte[]?>>? TestReadContainerEnvOverride { get; set; }
        internal static Func<Task<(bool Connected, string StatusToken)>>? TestProbeLiveMysqlPdoOverride { get; set; }
        internal static Func<ReadOnlyMemory<byte>, Task<string>>? TestWriteLiveEnvOverride { get; set; }
        internal static int TestPdoRetryDelayMs { get; set; } = 1500;

        public static void GenerateMittwaldCompliantPasswordChars(Span<char> dest)
        {
            if (dest.Length != 28)
                throw new ArgumentException("Live-MySQL-Passwort muss exakt 28 Zeichen lang sein.", nameof(dest));

            const string upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
            const string lower = "abcdefghijkmnopqrstuvwxyz";
            const string digits = "0123456789";
            // Intersection of .env/JSON-safe chars and Mittwald OpenAPI v2 allowed specials (#!~%^*_+-=?{}()<>|.,;$:/)
            const string special = "!%^*_-+=";
            const string firstPool = upper + lower;
            const string allPool = upper + lower + digits + special;

            // Index 0 strictly ASCII letter -> never starts with '-', '_' or ';'
            dest[0] = firstPool[RandomNumberGenerator.GetInt32(firstPool.Length)];
            dest[1] = upper[RandomNumberGenerator.GetInt32(upper.Length)];
            dest[2] = lower[RandomNumberGenerator.GetInt32(lower.Length)];
            dest[3] = digits[RandomNumberGenerator.GetInt32(digits.Length)];
            dest[4] = special[RandomNumberGenerator.GetInt32(special.Length)];

            for (int i = 5; i < dest.Length; i++)
            {
                dest[i] = allPool[RandomNumberGenerator.GetInt32(allPool.Length)];
            }

            // Fisher-Yates shuffle on positions 1..27
            for (int i = 1; i < dest.Length - 1; i++)
            {
                int j = RandomNumberGenerator.GetInt32(i, dest.Length);
                (dest[i], dest[j]) = (dest[j], dest[i]);
            }
        }

        private static byte[] BuildJsonPasswordPatchBytesZeroSecret(ReadOnlySpan<char> passwordChars)
        {
            ReadOnlySpan<byte> prefix = "{\"password\":\""u8;
            ReadOnlySpan<byte> suffix = "\"}"u8;
            int pwdByteCount = Encoding.UTF8.GetByteCount(passwordChars);
            byte[] buf = new byte[prefix.Length + pwdByteCount + suffix.Length];
            prefix.CopyTo(buf.AsSpan(0, prefix.Length));
            Encoding.UTF8.GetBytes(passwordChars, buf.AsSpan(prefix.Length, pwdByteCount));
            suffix.CopyTo(buf.AsSpan(prefix.Length + pwdByteCount));
            return buf;
        }

        private static ReadOnlySpan<byte> StripUtf8Bom(ReadOnlySpan<byte> data)
        {
            if (data.Length >= 3 && data[0] == 0xEF && data[1] == 0xBB && data[2] == 0xBF)
                return data.Slice(3);
            return data;
        }

        private static ReadOnlySpan<byte> UnquoteEnvValueSpan(ReadOnlySpan<byte> rawVal)
        {
            ReadOnlySpan<byte> trimmed = TrimAsciiWhitespace(rawVal);
            if (trimmed.Length >= 2 &&
                ((trimmed[0] == (byte)'"' && trimmed[^1] == (byte)'"') ||
                 (trimmed[0] == (byte)'\'' && trimmed[^1] == (byte)'\'')))
            {
                return trimmed.Slice(1, trimmed.Length - 2);
            }
            return trimmed;
        }

        private static ReadOnlySpan<byte> TrimAsciiWhitespace(ReadOnlySpan<byte> span)
        {
            int start = 0;
            while (start < span.Length && (span[start] == (byte)' ' || span[start] == (byte)'\t' || span[start] == (byte)'\r'))
                start++;
            int end = span.Length - 1;
            while (end >= start && (span[end] == (byte)' ' || span[end] == (byte)'\t' || span[end] == (byte)'\r'))
                end--;
            return start > end ? ReadOnlySpan<byte>.Empty : span.Slice(start, end - start + 1);
        }

        private static bool AsciiContainsIgnoreCase(ReadOnlySpan<byte> haystack, ReadOnlySpan<byte> needleLowerAscii)
        {
            if (needleLowerAscii.Length == 0 || haystack.Length < needleLowerAscii.Length)
                return false;

            for (int i = 0; i <= haystack.Length - needleLowerAscii.Length; i++)
            {
                bool match = true;
                for (int j = 0; j < needleLowerAscii.Length; j++)
                {
                    byte b = haystack[i + j];
                    if (b is >= (byte)'A' and <= (byte)'Z')
                        b = (byte)(b + 32);
                    if (b != needleLowerAscii[j])
                    {
                        match = false;
                        break;
                    }
                }
                if (match) return true;
            }
            return false;
        }

        private static List<(string? Key, byte[] RawLineOrValue)> ParseEnvLinesZeroSecret(
            ReadOnlySpan<byte> envUtf8,
            List<byte[]> scratchTracker,
            out Dictionary<string, byte[]> keyMap)
        {
            keyMap = new Dictionary<string, byte[]>(StringComparer.Ordinal);
            var lines = new List<(string? Key, byte[] RawLineOrValue)>();
            ReadOnlySpan<byte> clean = StripUtf8Bom(envUtf8);
            if (clean.IsEmpty)
                return lines;

            int pos = 0;
            while (pos < clean.Length)
            {
                int nl = clean.Slice(pos).IndexOf((byte)'\n');
                ReadOnlySpan<byte> rawLine = nl >= 0 ? clean.Slice(pos, nl) : clean.Slice(pos);
                pos = nl >= 0 ? pos + nl + 1 : clean.Length;

                if (!rawLine.IsEmpty && rawLine[^1] == (byte)'\r')
                    rawLine = rawLine.Slice(0, rawLine.Length - 1);

                ReadOnlySpan<byte> trimmed = TrimAsciiWhitespace(rawLine);
                if (trimmed.IsEmpty || trimmed[0] == (byte)'#')
                {
                    byte[] copy = rawLine.ToArray();
                    scratchTracker.Add(copy);
                    lines.Add((null, copy));
                    continue;
                }

                ReadOnlySpan<byte> working = trimmed;
                if (working.Length > 7 && working.Slice(0, 7).SequenceEqual("export "u8))
                    working = TrimAsciiWhitespace(working.Slice(7));

                int eq = working.IndexOf((byte)'=');
                if (eq <= 0)
                {
                    byte[] copy = rawLine.ToArray();
                    scratchTracker.Add(copy);
                    lines.Add((null, copy));
                    continue;
                }

                ReadOnlySpan<byte> keySpan = TrimAsciiWhitespace(working.Slice(0, eq));
                bool validKey = !keySpan.IsEmpty;
                for (int i = 0; i < keySpan.Length; i++)
                {
                    byte c = keySpan[i];
                    bool ok = (c is >= (byte)'A' and <= (byte)'Z')
                           || (c is >= (byte)'a' and <= (byte)'z')
                           || (c is >= (byte)'0' and <= (byte)'9')
                           || c == (byte)'_';
                    if (!ok) { validKey = false; break; }
                }

                if (!validKey)
                {
                    byte[] copy = rawLine.ToArray();
                    scratchTracker.Add(copy);
                    lines.Add((null, copy));
                    continue;
                }

                string keyName = Encoding.ASCII.GetString(keySpan);
                byte[] valBytes = working.Slice(eq + 1).ToArray();
                scratchTracker.Add(valBytes);
                keyMap[keyName] = valBytes;
                lines.Add((keyName, valBytes));
            }

            return lines;
        }

        internal static (byte[] FinalEnvUtf8, bool ReusedExistingAppKey, bool ReusedExistingDbPassword, int PreservedServiceKeyCount)
            BuildIsolatedSeelenfunkeLiveEnvZeroSecret(
                ReadOnlySpan<byte> stageEnvUtf8,
                ReadOnlySpan<byte> existingLiveEnvUtf8,
                bool preserveExistingLiveDbPassword,
                ReadOnlySpan<char> newDbPasswordChars)
        {
            var scratch = new List<byte[]>();
            Span<byte> csprngAppKey32 = stackalloc byte[32];
            char[] b64AppKeyChars = new char[44];

            try
            {
                var stageLines = ParseEnvLinesZeroSecret(stageEnvUtf8, scratch, out var stageMap);
                var liveLines = ParseEnvLinesZeroSecret(existingLiveEnvUtf8, scratch, out var liveMap);

                // 1. Determine APP_KEY (reuse Live APP_KEY ONLY if already set and distinct from Stage APP_KEY)
                bool reusedAppKey = false;
                byte[] appKeyValUtf8;
                ReadOnlySpan<byte> stageAppKeyUnquoted = stageMap.TryGetValue("APP_KEY", out var stAk) ? UnquoteEnvValueSpan(stAk) : ReadOnlySpan<byte>.Empty;
                ReadOnlySpan<byte> liveAppKeyUnquoted = liveMap.TryGetValue("APP_KEY", out var lvAk) ? UnquoteEnvValueSpan(lvAk) : ReadOnlySpan<byte>.Empty;

                if (liveAppKeyUnquoted.Length >= 40
                    && liveAppKeyUnquoted.StartsWith("base64:"u8)
                    && (stageAppKeyUnquoted.IsEmpty || !liveAppKeyUnquoted.SequenceEqual(stageAppKeyUnquoted)))
                {
                    appKeyValUtf8 = liveMap["APP_KEY"].ToArray();
                    scratch.Add(appKeyValUtf8);
                    reusedAppKey = true;
                }
                else
                {
                    RandomNumberGenerator.Fill(csprngAppKey32);
                    if (!Convert.TryToBase64Chars(csprngAppKey32, b64AppKeyChars, out int written) || written != 44)
                        throw new InvalidOperationException("CSPRNG APP_KEY Base64-Erzeugung fehlgeschlagen.");

                    ReadOnlySpan<byte> akPre = "\"base64:"u8;
                    int akMidLen = Encoding.ASCII.GetByteCount(b64AppKeyChars);
                    appKeyValUtf8 = new byte[akPre.Length + akMidLen + 1];
                    scratch.Add(appKeyValUtf8);
                    akPre.CopyTo(appKeyValUtf8);
                    Encoding.ASCII.GetBytes(b64AppKeyChars, appKeyValUtf8.AsSpan(akPre.Length, akMidLen));
                    appKeyValUtf8[^1] = (byte)'"';
                }

                // 2. Determine DB_PASSWORD
                bool reusedDbPassword = false;
                byte[] dbPasswordValUtf8;
                if (preserveExistingLiveDbPassword
                    && liveMap.TryGetValue("DB_PASSWORD", out var existingDbPw)
                    && UnquoteEnvValueSpan(existingDbPw).Length > 0)
                {
                    dbPasswordValUtf8 = existingDbPw.ToArray();
                    scratch.Add(dbPasswordValUtf8);
                    reusedDbPassword = true;
                }
                else
                {
                    if (newDbPasswordChars.IsEmpty)
                        throw new ArgumentException("Neues Live-DB-Passwort fehlt bei Neu-Provisionierung.");

                    int pwByteLen = Encoding.UTF8.GetByteCount(newDbPasswordChars);
                    dbPasswordValUtf8 = new byte[pwByteLen + 2];
                    scratch.Add(dbPasswordValUtf8);
                    dbPasswordValUtf8[0] = (byte)'"';
                    Encoding.UTF8.GetBytes(newDbPasswordChars, dbPasswordValUtf8.AsSpan(1, pwByteLen));
                    dbPasswordValUtf8[^1] = (byte)'"';
                }

                // 3. Strict Live-Isolation Overrides (guaranteed zero sharing with Stage DB / Redis / Queue!)
                var forcedOverrides = new Dictionary<string, byte[]>(StringComparer.Ordinal)
                {
                    ["APP_NAME"] = "\"Mein Seelenfunke\""u8.ToArray(),
                    ["APP_ENV"] = "production"u8.ToArray(),
                    ["APP_DEBUG"] = "false"u8.ToArray(),
                    ["APP_KEY"] = appKeyValUtf8,
                    ["APP_URL"] = "https://www.mein-seelenfunke.de"u8.ToArray(),
                    ["DB_CONNECTION"] = "mysql"u8.ToArray(),
                    ["DB_HOST"] = Encoding.ASCII.GetBytes(McpSecurityGuard.PinnedLiveMysqlHost),
                    ["DB_PORT"] = "3306"u8.ToArray(),
                    ["DB_DATABASE"] = Encoding.ASCII.GetBytes(McpSecurityGuard.PinnedLiveMysqlDatabase),
                    ["DB_USERNAME"] = Encoding.ASCII.GetBytes(McpSecurityGuard.PinnedLiveMysqlUser),
                    ["DB_PASSWORD"] = dbPasswordValUtf8,
                    ["CACHE_STORE"] = "database"u8.ToArray(),
                    ["CACHE_DRIVER"] = "database"u8.ToArray(),
                    ["CACHE_PREFIX"] = "seelenfunke_live_cache_"u8.ToArray(),
                    ["SESSION_DRIVER"] = "database"u8.ToArray(),
                    ["QUEUE_CONNECTION"] = "sync"u8.ToArray(),
                    ["BROADCAST_CONNECTION"] = "log"u8.ToArray(),
                    ["BROADCAST_DRIVER"] = "log"u8.ToArray(),
                    ["REDIS_HOST"] = "127.0.0.1"u8.ToArray(),
                    ["REDIS_PASSWORD"] = "null"u8.ToArray(),
                    ["REDIS_PORT"] = "6379"u8.ToArray(),
                    ["GOOGLE_REDIRECT_URL"] = "https://www.mein-seelenfunke.de/auth/google/callback"u8.ToArray()
                };

                var primaryLines = (preserveExistingLiveDbPassword && liveLines.Count > 0) ? liveLines : stageLines;
                var secondaryLines = (preserveExistingLiveDbPassword && liveLines.Count > 0) ? stageLines : liveLines;

                // Neutralize Stage-specific cookie/asset domains if inherited from Stage .env
                foreach (var map in new[] { stageMap, liveMap })
                {
                    if (map.TryGetValue("SESSION_DOMAIN", out var sdVal) && AsciiContainsIgnoreCase(sdVal, "stage"u8))
                        forcedOverrides["SESSION_DOMAIN"] = "null"u8.ToArray();
                    if (map.TryGetValue("ASSET_URL", out var auVal) && AsciiContainsIgnoreCase(auVal, "stage.mein-seelenfunke.de"u8))
                        forcedOverrides["ASSET_URL"] = "https://www.mein-seelenfunke.de"u8.ToArray();
                    if (map.TryGetValue("SANCTUM_STATEFUL_DOMAINS", out var ssdVal) && AsciiContainsIgnoreCase(ssdVal, "stage.mein-seelenfunke.de"u8))
                        forcedOverrides["SANCTUM_STATEFUL_DOMAINS"] = "www.mein-seelenfunke.de,mein-seelenfunke.de"u8.ToArray();
                }

                var emittedKeys = new HashSet<string>(StringComparer.Ordinal);
                int preservedServiceKeys = 0;

                using var ms = new MemoryStream(8192);
                try
                {
                    void WriteKeyVal(string key, ReadOnlySpan<byte> val)
                    {
                        byte[] kBytes = Encoding.ASCII.GetBytes(key);
                        ms.Write(kBytes, 0, kBytes.Length);
                        ms.WriteByte((byte)'=');
                        ms.Write(val);
                        ms.WriteByte((byte)'\n');
                    }

                    foreach (var (key, rawOrVal) in primaryLines)
                    {
                        if (key == null)
                        {
                            ms.Write(rawOrVal, 0, rawOrVal.Length);
                            ms.WriteByte((byte)'\n');
                            continue;
                        }

                        if (!emittedKeys.Add(key))
                            continue;

                        if (forcedOverrides.TryGetValue(key, out var forcedVal))
                        {
                            WriteKeyVal(key, forcedVal);
                        }
                        else
                        {
                            WriteKeyVal(key, rawOrVal);
                            preservedServiceKeys++;
                        }
                    }

                    // Merge any missing service keys from secondary template (e.g. newly added MAIL_*/STRIPE_* on Stage)
                    foreach (var (key, rawOrVal) in secondaryLines)
                    {
                        if (key == null || !emittedKeys.Add(key))
                            continue;

                        if (forcedOverrides.TryGetValue(key, out var forcedVal))
                        {
                            WriteKeyVal(key, forcedVal);
                        }
                        else
                        {
                            WriteKeyVal(key, rawOrVal);
                            preservedServiceKeys++;
                        }
                    }

                    // Append any forced isolation keys that were not present in the base template at all
                    foreach (var kv in forcedOverrides)
                    {
                        if (emittedKeys.Add(kv.Key))
                        {
                            WriteKeyVal(kv.Key, kv.Value);
                        }
                    }

                    byte[] finalExact = new byte[ms.Length];
                    Buffer.BlockCopy(ms.GetBuffer(), 0, finalExact, 0, finalExact.Length);
                    return (finalExact, reusedAppKey, reusedDbPassword, preservedServiceKeys);
                }
                finally
                {
                    CryptographicOperations.ZeroMemory(ms.GetBuffer());
                }
            }
            finally
            {
                CryptographicOperations.ZeroMemory(csprngAppKey32);
                Array.Clear(b64AppKeyChars, 0, b64AppKeyChars.Length);
                foreach (var buf in scratch)
                {
                    CryptographicOperations.ZeroMemory(buf);
                }
            }
        }

        public static async Task<JsonObject> ExecutePinnedSeelenfunkeLivePrepareAsync(
            string step = "all",
            string? entryTitleOrId = null,
            CancellationToken ct = default)
        {
            string normStep = string.IsNullOrWhiteSpace(step) ? "all" : step.Trim().ToLowerInvariant();
            if (normStep is not ("all" or "infra" or "env" or "cron" or "check"))
                throw new ArgumentException($"Ungültiger --step='{McpSecurityGuard.ScrubSecrets(step)}'. Erlaubt: all, infra, env, cron, check.");

            if (!string.IsNullOrWhiteSpace(entryTitleOrId)
                && (entryTitleOrId.Contains("steinhauer-invest", StringComparison.OrdinalIgnoreCase)
                    || entryTitleOrId.Contains("a-s4sdym", StringComparison.OrdinalIgnoreCase)
                    || entryTitleOrId.Contains("2be54dff-9651-416e-8aba-79a5b63749ff", StringComparison.OrdinalIgnoreCase)
                    || entryTitleOrId.Contains("mysql_fg9zlg", StringComparison.OrdinalIgnoreCase)))
            {
                McpSecurityGuard.AppendAuditLog("mittwald_prepare_live", McpSecurityGuard.ScrubSecrets(entryTitleOrId), "DENY:steinhauer_invest_blocked");
                throw new UnauthorizedAccessException("HARTE SICHERHEITSSPERRE: Zugriff auf 'steinhauer-invest' ist zu 100 % technisch gesperrt!");
            }

            // Hard pin verification on all target constants
            McpSecurityGuard.ValidatePinnedSeelenfunkeLiveTarget(
                McpSecurityGuard.PinnedLiveProjectId,
                McpSecurityGuard.PinnedLiveAppUuid,
                McpSecurityGuard.PinnedLiveRemoteDir,
                McpSecurityGuard.PinnedLiveSshUser,
                McpSecurityGuard.PinnedLiveMysqlDatabase,
                McpSecurityGuard.PinnedLiveMysqlUserUuid);

            string lookupQuery = string.IsNullOrWhiteSpace(entryTitleOrId) ? "Mittwald" : entryTitleOrId!.Trim();
            using var lease = ResolveEntrySecretLease(lookupQuery, requireApiToken: true);

            bool ownsClient = TestMittwaldHttpClientOverride == null;
            HttpClient client = TestMittwaldHttpClientOverride ?? McpSecurityGuard.CreateHardenedApiClient(TimeSpan.FromSeconds(30));

            var summary = new JsonObject
            {
                ["status"] = "ok",
                ["step"] = normStep,
                ["pinned_project_id"] = McpSecurityGuard.PinnedLiveProjectId,
                ["pinned_app_id"] = McpSecurityGuard.PinnedLiveAppUuid,
                ["pinned_app_short_id"] = McpSecurityGuard.PinnedLiveAppShortId
            };

            try
            {
                if (normStep == "check")
                {
                    var checkObj = new JsonObject { ["tresor_entry_title"] = lease.Title };

                    var (cAppSt, _, cAppNode) = await SendMittwaldRequestAsync(
                        client, HttpMethod.Get, $"/v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid}", lease.ApiTokenUtf8, ct);
                    checkObj["live_app_http"] = cAppSt;
                    checkObj["custom_document_root"] = (cAppNode as JsonObject)?["customDocumentRoot"]?.GetValue<string>() ?? "";

                    var (cIngSt, _, cIngNode) = await SendMittwaldRequestAsync(
                        client, HttpMethod.Get, $"/v2/ingresses?projectId={Uri.EscapeDataString(McpSecurityGuard.PinnedLiveProjectId)}", lease.ApiTokenUtf8, ct);
                    bool hasWww = (cIngNode as JsonArray)?.Any(x => string.Equals(x?["hostname"]?.GetValue<string>(), McpSecurityGuard.PinnedLiveWwwHostname, StringComparison.OrdinalIgnoreCase)) == true;
                    checkObj["ingresses_http"] = cIngSt;
                    checkObj["www_ingress_exists"] = hasWww;

                    var (cCronSt, _, cCronNode) = await SendMittwaldRequestAsync(
                        client, HttpMethod.Get, $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/cronjobs", lease.ApiTokenUtf8, ct);
                    bool hasLiveCron = (cCronNode as JsonArray)?.Any(x =>
                        string.Equals(x?["appInstallationId"]?.GetValue<string>(), McpSecurityGuard.PinnedLiveAppUuid, StringComparison.Ordinal) ||
                        string.Equals(x?["target"]?["appInstallationId"]?.GetValue<string>(), McpSecurityGuard.PinnedLiveAppUuid, StringComparison.Ordinal)) == true;
                    checkObj["cronjobs_http"] = cCronSt;
                    checkObj["live_cronjob_exists"] = hasLiveCron;

                    byte[]? chkLiveEnv = null;
                    byte[]? chkStageEnv = null;
                    try
                    {
                        var readFn = TestReadContainerEnvOverride ?? McpSecurityGuard.ReadPinnedContainerEnvBytesZeroSecretAsync;
                        var probeFn = TestProbeLiveMysqlPdoOverride ?? McpSecurityGuard.ProbePinnedLiveMysqlPdoZeroSecretAsync;

                        chkLiveEnv = await readFn(McpSecurityGuard.PinnedLiveSshUser, McpSecurityGuard.PinnedLiveEnvRemotePath);
                        checkObj["live_ssh_sftp_ok"] = true;
                        checkObj["live_env_exists"] = chkLiveEnv != null && chkLiveEnv.Length > 0;

                        chkStageEnv = await readFn(McpSecurityGuard.PinnedLiveSshUser, McpSecurityGuard.PinnedStageEnvRemotePath)
                                   ?? await readFn(McpSecurityGuard.PinnedStageWebSshUser, McpSecurityGuard.PinnedStageEnvRemotePath);
                        checkObj["stage_env_readable_in_ram"] = chkStageEnv != null && chkStageEnv.Length > 0;

                        var (pdoOk, pdoToken) = await probeFn();
                        checkObj["live_pdo_ok"] = pdoOk;
                        checkObj["live_pdo_status"] = pdoToken;
                    }
                    catch (Exception sshEx)
                    {
                        checkObj["live_ssh_sftp_ok"] = false;
                        checkObj["live_ssh_error"] = McpSecurityGuard.ScrubSecrets(sshEx.Message);
                    }
                    finally
                    {
                        if (chkLiveEnv != null) CryptographicOperations.ZeroMemory(chkLiveEnv);
                        if (chkStageEnv != null) CryptographicOperations.ZeroMemory(chkStageEnv);
                    }

                    summary["check"] = checkObj;
                    return summary;
                }

                // =============================================================
                // STEP 1 & 2: INFRA (DocumentRoot /public + Ingress + ACME + DNS)
                // =============================================================
                if (normStep is "all" or "infra")
                {
                    var infraObj = new JsonObject();
                    var infraErrors = new List<string>();

                    // 1) DocumentRoot /public on 93f33f15-d27c-4d15-9f85-155eb9adace8
                    var (appSt, appBody, appNode) = await SendMittwaldRequestAsync(
                        client,
                        HttpMethod.Get,
                        $"/v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid}",
                        lease.ApiTokenUtf8,
                        ct);
                    if (appSt is < 200 or >= 300 || appNode is not JsonObject appJson)
                        throw new InvalidOperationException($"GET /v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid} fehlgeschlagen (HTTP {appSt}): {appBody}");

                    string fetchedId = appJson["id"]?.GetValue<string>() ?? "";
                    string fetchedProj = appJson["projectId"]?.GetValue<string>() ?? "";
                    string fetchedShort = appJson["shortId"]?.GetValue<string>() ?? "";
                    string fetchedDesc = appJson["description"]?.GetValue<string>() ?? "";

                    if (!string.Equals(fetchedId, McpSecurityGuard.PinnedLiveAppUuid, StringComparison.Ordinal)
                        || !string.Equals(fetchedProj, McpSecurityGuard.PinnedLiveProjectId, StringComparison.Ordinal)
                        || !string.Equals(fetchedShort, McpSecurityGuard.PinnedLiveAppShortId, StringComparison.Ordinal)
                        || fetchedDesc.Contains("steinhauer-invest", StringComparison.OrdinalIgnoreCase))
                    {
                        throw new UnauthorizedAccessException("PINNING-SPERRE: Mittwald AppInstallation entspricht nicht exakt 'seelenfunke-live' (a-bycqrf).");
                    }

                    string docRootBefore = appJson["customDocumentRoot"]?.GetValue<string>() ?? "";
                    bool docRootPatched = false;
                    string docRootAfter = docRootBefore;

                    if (!string.Equals(docRootBefore, "/public", StringComparison.Ordinal))
                    {
                        byte[] patchDocRootBytes = Encoding.UTF8.GetBytes("{\"customDocumentRoot\":\"/public\"}");
                        var (patchSt, patchBody, _) = await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Patch,
                            $"/v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid}",
                            lease.ApiTokenUtf8,
                            ct,
                            jsonBodyUtf8: patchDocRootBytes);
                        if (patchSt is < 200 or >= 300)
                        {
                            string hint = patchSt == 403 ? $" [Tresor-Token '{lease.Title}' hat nur api_read; benötigt api_write]" : "";
                            infraErrors.Add($"PATCH /v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid} -> HTTP {patchSt}{hint}: {patchBody}");
                        }
                        else
                        {
                            docRootPatched = true;
                            var (reSt, _, reNode) = await SendMittwaldRequestAsync(
                                client,
                                HttpMethod.Get,
                                $"/v2/app-installations/{McpSecurityGuard.PinnedLiveAppUuid}",
                                lease.ApiTokenUtf8,
                                ct);
                            if (reSt is >= 200 and < 300 && reNode is JsonObject reJson)
                                docRootAfter = reJson["customDocumentRoot"]?.GetValue<string>() ?? "/public";
                            else
                                docRootAfter = "/public";
                        }
                    }

                    infraObj["document_root_before"] = docRootBefore;
                    infraObj["document_root_after"] = docRootAfter;
                    infraObj["document_root_patched"] = docRootPatched;

                    // 2) Ingress www.mein-seelenfunke.de -> 93f33f15-d27c-4d15-9f85-155eb9adace8 (/) + ACME TLS + DNS Zone
                    var (ingListSt, ingListBody, ingListNode) = await SendMittwaldRequestAsync(
                        client,
                        HttpMethod.Get,
                        $"/v2/ingresses?projectId={Uri.EscapeDataString(McpSecurityGuard.PinnedLiveProjectId)}",
                        lease.ApiTokenUtf8,
                        ct);
                    if (ingListSt is < 200 or >= 300 || ingListNode is not JsonArray ingArr)
                        throw new InvalidOperationException($"GET /v2/ingresses fehlgeschlagen (HTTP {ingListSt}): {ingListBody}");

                    JsonObject? wwwIngressObj = null;
                    foreach (var item in ingArr)
                    {
                        if (item is JsonObject io &&
                            string.Equals(io["hostname"]?.GetValue<string>(), McpSecurityGuard.PinnedLiveWwwHostname, StringComparison.OrdinalIgnoreCase))
                        {
                            wwwIngressObj = io;
                            break;
                        }
                    }

                    bool ingressCreated = false;
                    string wwwIngressId = "";

                    if (wwwIngressObj == null)
                    {
                        var createIngPayload = new JsonObject
                        {
                            ["projectId"] = McpSecurityGuard.PinnedLiveProjectId,
                            ["hostname"] = McpSecurityGuard.PinnedLiveWwwHostname,
                            ["paths"] = new JsonArray
                            {
                                new JsonObject
                                {
                                    ["path"] = "/",
                                    ["target"] = new JsonObject
                                    {
                                        ["installationId"] = McpSecurityGuard.PinnedLiveAppUuid
                                    }
                                }
                            }
                        };
                        byte[] createIngBytes = Encoding.UTF8.GetBytes(createIngPayload.ToJsonString());
                        var (crSt, crBody, crNode) = await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Post,
                            "/v2/ingresses",
                            lease.ApiTokenUtf8,
                            ct,
                            jsonBodyUtf8: createIngBytes);
                        if (crSt is < 200 or >= 300 || crNode is not JsonObject crObj)
                        {
                            string hint = crSt == 403 ? $" [Tresor-Token '{lease.Title}' hat nur api_read; benötigt api_write]" : "";
                            infraErrors.Add($"POST /v2/ingresses ({McpSecurityGuard.PinnedLiveWwwHostname}) -> HTTP {crSt}{hint}: {crBody}");
                        }
                        else
                        {
                            wwwIngressId = ValidateSafeMittwaldId(crObj["id"]?.GetValue<string>(), "ingressId");
                            ingressCreated = true;

                            var (gSt, _, gNode) = await SendMittwaldRequestAsync(
                                client,
                                HttpMethod.Get,
                                $"/v2/ingresses/{wwwIngressId}",
                                lease.ApiTokenUtf8,
                                ct);
                            if (gSt is >= 200 and < 300 && gNode is JsonObject gObj)
                                wwwIngressObj = gObj;
                        }
                    }
                    else
                    {
                        wwwIngressId = ValidateSafeMittwaldId(wwwIngressObj["id"]?.GetValue<string>(), "ingressId");

                        bool rootPointsToLive = false;
                        if (wwwIngressObj["paths"] is JsonArray pathsArr)
                        {
                            foreach (var pNode in pathsArr)
                            {
                                if (pNode is JsonObject po
                                    && string.Equals(po["path"]?.GetValue<string>(), "/", StringComparison.Ordinal)
                                    && string.Equals(po["target"]?["installationId"]?.GetValue<string>(), McpSecurityGuard.PinnedLiveAppUuid, StringComparison.Ordinal))
                                {
                                    rootPointsToLive = true;
                                    break;
                                }
                            }
                        }

                        if (!rootPointsToLive)
                        {
                            var patchPathsArr = new JsonArray
                            {
                                new JsonObject
                                {
                                    ["path"] = "/",
                                    ["target"] = new JsonObject
                                    {
                                        ["installationId"] = McpSecurityGuard.PinnedLiveAppUuid
                                    }
                                }
                            };
                            byte[] patchPathsBytes = Encoding.UTF8.GetBytes(patchPathsArr.ToJsonString());
                            var (ppSt, ppBody, _) = await SendMittwaldRequestAsync(
                                client,
                                HttpMethod.Patch,
                                $"/v2/ingresses/{wwwIngressId}/paths",
                                lease.ApiTokenUtf8,
                                ct,
                                jsonBodyUtf8: patchPathsBytes);
                            if (ppSt is < 200 or >= 300)
                                infraErrors.Add($"PATCH /v2/ingresses/{wwwIngressId}/paths -> HTTP {ppSt}: {ppBody}");
                        }
                    }

                    if (infraErrors.Count > 0 && string.IsNullOrEmpty(wwwIngressId))
                        throw new InvalidOperationException(string.Join(" || ", infraErrors));

                    // Ensure TLS ACME is enabled on www ingress
                    bool tlsAcmeEnabled = wwwIngressObj?["tls"]?["acme"]?.GetValue<bool>() == true;
                    if (!tlsAcmeEnabled)
                    {
                        byte[] patchTlsBytes = Encoding.UTF8.GetBytes("{\"acme\":true}");
                        var (tlsSt, tlsBody, _) = await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Patch,
                            $"/v2/ingresses/{wwwIngressId}/tls",
                            lease.ApiTokenUtf8,
                            ct,
                            jsonBodyUtf8: patchTlsBytes);
                        if (tlsSt is < 200 or >= 300)
                            throw new InvalidOperationException($"PATCH /v2/ingresses/{wwwIngressId}/tls fehlgeschlagen (HTTP {tlsSt}): {tlsBody}");
                        tlsAcmeEnabled = true;
                    }

                    // Check & link DNS Zone for www.mein-seelenfunke.de (managedBy.ingressId)
                    var (zListSt, zListBody, zListNode) = await SendMittwaldRequestAsync(
                        client,
                        HttpMethod.Get,
                        $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/dns-zones",
                        lease.ApiTokenUtf8,
                        ct);
                    if (zListSt is < 200 or >= 300 || zListNode is not JsonArray zonesArr)
                        throw new InvalidOperationException($"GET /v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/dns-zones fehlgeschlagen (HTTP {zListSt}): {zListBody}");

                    JsonObject? wwwZoneObj = null;
                    foreach (var z in zonesArr)
                    {
                        if (z is JsonObject zo &&
                            string.Equals(zo["domain"]?.GetValue<string>(), McpSecurityGuard.PinnedLiveWwwHostname, StringComparison.OrdinalIgnoreCase))
                        {
                            wwwZoneObj = zo;
                            break;
                        }
                    }

                    bool dnsZoneCreated = false;
                    bool dnsSetManagedCalled = false;
                    string wwwZoneId = "";

                    if (wwwZoneObj == null)
                    {
                        byte[] createZoneBytes = Encoding.UTF8.GetBytes($"{{\"name\":\"{McpSecurityGuard.PinnedLiveWwwHostname}\"}}");
                        var (czSt, czBody, czNode) = await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Post,
                            $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/dns-zones",
                            lease.ApiTokenUtf8,
                            ct,
                            jsonBodyUtf8: createZoneBytes);

                        if (czSt is < 200 or >= 300)
                        {
                            // Fallback to parentZoneId subzone creation if project endpoint rejected
                            byte[] fallbackZoneBytes = Encoding.UTF8.GetBytes($"{{\"name\":\"www\",\"parentZoneId\":\"{McpSecurityGuard.PinnedLiveRootZoneUuid}\"}}");
                            var (fbSt, fbBody, fbNode) = await SendMittwaldRequestAsync(
                                client,
                                HttpMethod.Post,
                                "/v2/dns-zones",
                                lease.ApiTokenUtf8,
                                ct,
                                jsonBodyUtf8: fallbackZoneBytes);
                            if (fbSt is < 200 or >= 300 || fbNode is not JsonObject fbObj)
                                throw new InvalidOperationException($"Anlegen der DNS-Subzone {McpSecurityGuard.PinnedLiveWwwHostname} fehlgeschlagen (HTTP {czSt}/{fbSt}): {czBody} | {fbBody}");

                            wwwZoneId = ValidateSafeMittwaldId(fbObj["id"]?.GetValue<string>(), "dnsZoneId");
                        }
                        else if (czNode is JsonObject czObj)
                        {
                            wwwZoneId = ValidateSafeMittwaldId(czObj["id"]?.GetValue<string>(), "dnsZoneId");
                        }

                        dnsZoneCreated = true;
                        if (!string.IsNullOrEmpty(wwwZoneId))
                        {
                            var (gzSt, _, gzNode) = await SendMittwaldRequestAsync(
                                client,
                                HttpMethod.Get,
                                $"/v2/dns-zones/{wwwZoneId}",
                                lease.ApiTokenUtf8,
                                ct);
                            if (gzSt is >= 200 and < 300 && gzNode is JsonObject gzObj)
                                wwwZoneObj = gzObj;
                        }
                    }
                    else
                    {
                        wwwZoneId = ValidateSafeMittwaldId(wwwZoneObj["id"]?.GetValue<string>(), "dnsZoneId");
                    }

                    string managedByIngressId = wwwZoneObj?["recordSet"]?["combinedARecords"]?["managedBy"]?["ingressId"]?.GetValue<string>() ?? "";
                    if (!string.IsNullOrEmpty(wwwZoneId) && !string.Equals(managedByIngressId, wwwIngressId, StringComparison.Ordinal))
                    {
                        byte[] emptyJsonObj = Encoding.UTF8.GetBytes("{}");
                        var (smSt, smBody, _) = await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Post,
                            $"/v2/dns-zones/{wwwZoneId}/record-sets/a/actions/set-managed",
                            lease.ApiTokenUtf8,
                            ct,
                            jsonBodyUtf8: emptyJsonObj);
                        if (smSt is < 200 or >= 300)
                            throw new InvalidOperationException($"POST /v2/dns-zones/{wwwZoneId}/record-sets/a/actions/set-managed fehlgeschlagen (HTTP {smSt}): {smBody}");

                        dnsSetManagedCalled = true;
                        var (gz2St, _, gz2Node) = await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Get,
                            $"/v2/dns-zones/{wwwZoneId}",
                            lease.ApiTokenUtf8,
                            ct);
                        if (gz2St is >= 200 and < 300 && gz2Node is JsonObject gz2Obj)
                            managedByIngressId = gz2Obj["recordSet"]?["combinedARecords"]?["managedBy"]?["ingressId"]?.GetValue<string>() ?? wwwIngressId;
                    }

                    // Trigger ownership verification & ACME issuance if not yet marked created
                    var (finIngSt, _, finIngNode) = await SendMittwaldRequestAsync(
                        client,
                        HttpMethod.Get,
                        $"/v2/ingresses/{wwwIngressId}",
                        lease.ApiTokenUtf8,
                        ct);
                    if (finIngSt is >= 200 and < 300 && finIngNode is JsonObject finIngObj)
                        wwwIngressObj = finIngObj;

                    bool ownershipVerified = wwwIngressObj?["ownership"]?["verified"]?.GetValue<bool>() == true;
                    if (!ownershipVerified)
                    {
                        await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Post,
                            $"/v2/ingresses/{wwwIngressId}/actions/verify-ownership",
                            lease.ApiTokenUtf8,
                            ct);
                    }

                    bool tlsIsCreated = wwwIngressObj?["tls"]?["isCreated"]?.GetValue<bool>() == true;
                    if (!tlsIsCreated)
                    {
                        await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Post,
                            $"/v2/ingresses/{wwwIngressId}/actions/request-acme-certificate-issuance",
                            lease.ApiTokenUtf8,
                            ct);
                    }

                    var (afterAcmeSt, _, afterAcmeNode) = await SendMittwaldRequestAsync(
                        client,
                        HttpMethod.Get,
                        $"/v2/ingresses/{wwwIngressId}",
                        lease.ApiTokenUtf8,
                        ct);
                    if (afterAcmeSt is >= 200 and < 300 && afterAcmeNode is JsonObject afterAcmeObj)
                        wwwIngressObj = afterAcmeObj;

                    infraObj["www_hostname"] = McpSecurityGuard.PinnedLiveWwwHostname;
                    infraObj["www_ingress_id"] = wwwIngressId;
                    infraObj["www_ingress_created"] = ingressCreated;
                    infraObj["tls_acme"] = wwwIngressObj?["tls"]?["acme"]?.GetValue<bool>() ?? tlsAcmeEnabled;
                    infraObj["tls_is_created"] = wwwIngressObj?["tls"]?["isCreated"]?.GetValue<bool>() ?? false;
                    infraObj["ownership_verified"] = wwwIngressObj?["ownership"]?["verified"]?.GetValue<bool>() ?? false;
                    infraObj["dns_zone_id"] = wwwZoneId;
                    infraObj["dns_zone_created"] = dnsZoneCreated;
                    infraObj["dns_set_managed_called"] = dnsSetManagedCalled;
                    infraObj["dns_managed_by_ingress_id"] = managedByIngressId;
                    summary["infra"] = infraObj;
                    if (infraErrors.Count > 0)
                        throw new InvalidOperationException(string.Join(" || ", infraErrors));

                    McpSecurityGuard.AppendAuditLog("mittwald_prepare_live", $"infra:ingress={wwwIngressId}:zone={wwwZoneId}", "ALLOW");
                }

                // =============================================================
                // STEP 3: ENV (Zero-Secret Live-.env + MySQL Password Provisioning)
                // =============================================================
                if (normStep is "all" or "env")
                {
                    byte[]? existingLiveEnv = null;
                    byte[]? stageEnv = null;
                    byte[]? builtLiveEnv = null;
                    char[] newPwdChars = new char[28];
                    byte[]? jsonPatchPwdBytes = null;

                    try
                    {
                        var readEnvFn = TestReadContainerEnvOverride ?? McpSecurityGuard.ReadPinnedContainerEnvBytesZeroSecretAsync;
                        var probePdoFn = TestProbeLiveMysqlPdoOverride ?? McpSecurityGuard.ProbePinnedLiveMysqlPdoZeroSecretAsync;
                        var writeEnvFn = TestWriteLiveEnvOverride ?? McpSecurityGuard.WritePinnedLiveEnvBytesZeroSecretAsync;

                        existingLiveEnv = await readEnvFn(McpSecurityGuard.PinnedLiveSshUser, McpSecurityGuard.PinnedLiveEnvRemotePath);
                        stageEnv = await readEnvFn(McpSecurityGuard.PinnedLiveSshUser, McpSecurityGuard.PinnedStageEnvRemotePath)
                                ?? await readEnvFn(McpSecurityGuard.PinnedStageWebSshUser, McpSecurityGuard.PinnedStageEnvRemotePath);

                        if ((stageEnv == null || stageEnv.Length == 0) && (existingLiveEnv == null || existingLiveEnv.Length == 0))
                            throw new InvalidOperationException("Weder Stage-.env noch bestehende Live-.env konnten im Container gelesen werden.");

                        bool preExistingPdoOk = false;
                        string initialPdoToken = "NO_ENV";
                        if (existingLiveEnv != null && existingLiveEnv.Length > 0)
                        {
                            (preExistingPdoOk, initialPdoToken) = await probePdoFn();
                        }

                        bool mysqlPasswordRotated = false;
                        if (!preExistingPdoOk)
                        {
                            GenerateMittwaldCompliantPasswordChars(newPwdChars);
                            jsonPatchPwdBytes = BuildJsonPasswordPatchBytesZeroSecret(newPwdChars);

                            var (pwSt, pwBody, _) = await SendMittwaldRequestAsync(
                                client,
                                HttpMethod.Patch,
                                $"/v2/mysql-users/{McpSecurityGuard.PinnedLiveMysqlUserUuid}/password",
                                lease.ApiTokenUtf8,
                                ct,
                                jsonBodyUtf8: jsonPatchPwdBytes,
                                extraSecretCharsToScrub: newPwdChars);

                            if (pwSt == 404)
                            {
                                (pwSt, pwBody, _) = await SendMittwaldRequestAsync(
                                    client,
                                    HttpMethod.Patch,
                                    $"/v2/mysql-users/{McpSecurityGuard.PinnedLiveMysqlUserUuid}",
                                    lease.ApiTokenUtf8,
                                    ct,
                                    jsonBodyUtf8: jsonPatchPwdBytes,
                                    extraSecretCharsToScrub: newPwdChars);
                            }

                            if (pwSt is < 200 or >= 300)
                                throw new InvalidOperationException($"PATCH MySQL-Passwort für {McpSecurityGuard.PinnedLiveMysqlUser} fehlgeschlagen (HTTP {pwSt}): {pwBody}");

                            mysqlPasswordRotated = true;
                        }

                        var (finalBytes, reusedAppKey, reusedDbPwd, serviceKeysCount) = BuildIsolatedSeelenfunkeLiveEnvZeroSecret(
                            stageEnv ?? Array.Empty<byte>(),
                            existingLiveEnv ?? Array.Empty<byte>(),
                            preserveExistingLiveDbPassword: preExistingPdoOk,
                            newDbPasswordChars: preExistingPdoOk ? ReadOnlySpan<char>.Empty : newPwdChars);
                        builtLiveEnv = finalBytes;

                        string fileMode = await writeEnvFn(builtLiveEnv);

                        // Verify PDO connection against mysql_gv2avu (up to 15s propagation retry loop)
                        bool finalPdoOk = false;
                        string finalPdoToken = "";
                        int attempts = 0;
                        int maxAttempts = mysqlPasswordRotated ? 10 : 2;
                        for (int attempt = 1; attempt <= maxAttempts; attempt++)
                        {
                            attempts = attempt;
                            (finalPdoOk, finalPdoToken) = await probePdoFn();
                            if (finalPdoOk)
                                break;
                            if (attempt < maxAttempts)
                                await Task.Delay(TestPdoRetryDelayMs, ct);
                        }

                        if (!finalPdoOk)
                            throw new InvalidOperationException($"PDO-Verbindungstest auf {McpSecurityGuard.PinnedLiveMysqlDatabase} nach {attempts} Versuchen fehlgeschlagen (Status={finalPdoToken}).");

                        summary["env"] = new JsonObject
                        {
                            ["live_env_path"] = McpSecurityGuard.PinnedLiveEnvRemotePath,
                            ["file_mode"] = fileMode,
                            ["mysql_database"] = McpSecurityGuard.PinnedLiveMysqlDatabase,
                            ["mysql_host"] = McpSecurityGuard.PinnedLiveMysqlHost,
                            ["mysql_user"] = McpSecurityGuard.PinnedLiveMysqlUser,
                            ["mysql_password_rotated"] = mysqlPasswordRotated,
                            ["reused_existing_app_key"] = reusedAppKey,
                            ["reused_existing_db_password"] = reusedDbPwd,
                            ["preserved_service_keys_count"] = serviceKeysCount,
                            ["initial_pdo_status"] = initialPdoToken,
                            ["final_pdo_status"] = finalPdoToken,
                            ["pdo_verify_attempts"] = attempts,
                            ["isolation_enforced"] = new JsonObject
                            {
                                ["APP_ENV"] = "production",
                                ["APP_DEBUG"] = false,
                                ["APP_URL"] = "https://www.mein-seelenfunke.de",
                                ["CACHE_STORE"] = "database",
                                ["SESSION_DRIVER"] = "database",
                                ["QUEUE_CONNECTION"] = "sync",
                                ["BROADCAST_CONNECTION"] = "log",
                                ["REDIS_ISOLATED"] = true
                            }
                        };

                        McpSecurityGuard.AppendAuditLog("mittwald_prepare_live", $"env:pdo={finalPdoToken}:pwRotated={mysqlPasswordRotated}", "ALLOW");
                    }
                    finally
                    {
                        Array.Clear(newPwdChars, 0, newPwdChars.Length);
                        if (jsonPatchPwdBytes != null) CryptographicOperations.ZeroMemory(jsonPatchPwdBytes);
                        if (existingLiveEnv != null) CryptographicOperations.ZeroMemory(existingLiveEnv);
                        if (stageEnv != null) CryptographicOperations.ZeroMemory(stageEnv);
                        if (builtLiveEnv != null) CryptographicOperations.ZeroMemory(builtLiveEnv);
                    }
                }

                // =============================================================
                // STEP 4: CRON (Seelenfunke Live - Scheduler)
                // =============================================================
                if (normStep is "all" or "cron")
                {
                    var (cListSt, cListBody, cListNode) = await SendMittwaldRequestAsync(
                        client,
                        HttpMethod.Get,
                        $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/cronjobs",
                        lease.ApiTokenUtf8,
                        ct);
                    if (cListSt is < 200 or >= 300 || cListNode is not JsonArray cronArr)
                        throw new InvalidOperationException($"GET /v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/cronjobs fehlgeschlagen (HTTP {cListSt}): {cListBody}");

                    JsonObject? existingLiveCron = null;
                    foreach (var cItem in cronArr)
                    {
                        if (cItem is not JsonObject co) continue;
                        string cApp1 = co["appInstallationId"]?.GetValue<string>() ?? "";
                        string cApp2 = co["appId"]?.GetValue<string>() ?? "";
                        string cApp3 = co["target"]?["appInstallationId"]?.GetValue<string>() ?? "";

                        if (string.Equals(cApp1, McpSecurityGuard.PinnedLiveAppUuid, StringComparison.Ordinal)
                            || string.Equals(cApp2, McpSecurityGuard.PinnedLiveAppUuid, StringComparison.Ordinal)
                            || string.Equals(cApp3, McpSecurityGuard.PinnedLiveAppUuid, StringComparison.Ordinal))
                        {
                            existingLiveCron = co;
                            break;
                        }
                    }

                    bool cronCreated = false;
                    string cronId;
                    string cronShortId = "";

                    if (existingLiveCron != null)
                    {
                        cronId = existingLiveCron["id"]?.GetValue<string>() ?? "";
                        cronShortId = existingLiveCron["shortId"]?.GetValue<string>() ?? "";
                    }
                    else
                    {
                        var createCronPayload = new JsonObject
                        {
                            ["description"] = "Seelenfunke Live - Scheduler",
                            ["interval"] = "* * * * *",
                            ["active"] = true,
                            ["timeout"] = 3600,
                            ["timeZone"] = "Europe/Berlin",
                            ["concurrencyPolicy"] = "forbid",
                            ["failedExecutionAlertThreshold"] = 1,
                            ["target"] = new JsonObject
                            {
                                ["appInstallationId"] = McpSecurityGuard.PinnedLiveAppUuid,
                                ["destination"] = new JsonObject
                                {
                                    ["interpreter"] = "/usr/bin/php",
                                    ["path"] = "/html/seelenfunke-live/artisan",
                                    ["parameters"] = "schedule:run"
                                }
                            }
                        };
                        byte[] createCronBytes = Encoding.UTF8.GetBytes(createCronPayload.ToJsonString());
                        var (ccSt, ccBody, ccNode) = await SendMittwaldRequestAsync(
                            client,
                            HttpMethod.Post,
                            $"/v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/cronjobs",
                            lease.ApiTokenUtf8,
                            ct,
                            jsonBodyUtf8: createCronBytes);
                        if (ccSt is < 200 or >= 300 || ccNode is not JsonObject ccObj)
                            throw new InvalidOperationException($"POST /v2/projects/{McpSecurityGuard.PinnedLiveProjectId}/cronjobs fehlgeschlagen (HTTP {ccSt}): {ccBody}");

                        cronId = ValidateSafeMittwaldId(ccObj["id"]?.GetValue<string>(), "cronjobId");
                        cronCreated = true;
                    }

                    summary["cron"] = new JsonObject
                    {
                        ["cronjob_id"] = cronId,
                        ["cronjob_short_id"] = cronShortId,
                        ["cronjob_created"] = cronCreated,
                        ["description"] = "Seelenfunke Live - Scheduler",
                        ["interval"] = "* * * * *",
                        ["path"] = "/html/seelenfunke-live/artisan",
                        ["parameters"] = "schedule:run",
                        ["time_zone"] = "Europe/Berlin",
                        ["concurrency_policy"] = "forbid",
                        ["timeout"] = 3600,
                        ["active"] = true
                    };

                    McpSecurityGuard.AppendAuditLog("mittwald_prepare_live", $"cron:id={cronId}:created={cronCreated}", "ALLOW");
                }

                return summary;
            }
            finally
            {
                if (ownsClient)
                    client.Dispose();
            }
        }
    }
}

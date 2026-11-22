using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.NetworkInformation;
using System.Net.Security;
using System.Net.Sockets;
using System.Security.Cryptography.X509Certificates;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using Funkenwerk;

namespace FunkenwerkSmartHome
{
    public sealed class TahomaShutterDevice
    {
        public string DeviceUrl { get; set; } = "";
        public string Label { get; set; } = "";
        public string ControllableName { get; set; } = "";
        public int ClosurePercent { get; set; } // 0 = komplett offen, 100 = komplett geschlossen
        public bool SupportsPosition { get; set; } = true;
        public bool IsAvailable { get; set; } = true;
    }

    public sealed class TahomaPersistedConfig
    {
        public string GatewayIp { get; set; } = "192.168.188.29";
        public string GatewayPin { get; set; } = "0813-5321-9574";
        public string GatewayModel { get; set; } = "Connexoon Window";
        public string ConnectionMode { get; set; } = "auto"; // auto | local | connexoon_cloud
        public string SomfyEmail { get; set; } = "";
        public string SomfyPasswordProtected { get; set; } = "";
        public string LocalBearerToken { get; set; } = "";
        public bool IsDarkMode { get; set; } = true;
        public List<TahomaShutterDevice> CachedShutters { get; set; } = new();
    }

    public sealed class TahomaGatewayProbeResult
    {
        public bool GatewayReachableOnLan { get; set; }
        public bool DeveloperApiPort8443Open { get; set; }
        public long PingRttMs { get; set; } = -1;
        public string DetectedGatewayPin { get; set; } = "";
        public string StatusSummary { get; set; } = "";
    }

    public static class SomfyTahomaLocalEngine
    {
        private const string VaultFileName = "tahoma_vault.json";
        public const string OverkizCloudBaseUrl = "https://ha101-1.overkiz.com/enduser-mobile-web/enduserAPI";
        private static readonly Regex PinInCnRegex = new(@"CN=([0-9]{4}-[0-9]{4}-[0-9]{4})\.local", RegexOptions.IgnoreCase | RegexOptions.Compiled);
        private static readonly object SessionSync = new();
        private static CookieContainer? _cachedCloudCookies;
        private static string _cachedCloudEmail = "";
        private static DateTime _cachedCloudAuthUtc = DateTime.MinValue;

        public static TahomaPersistedConfig LoadConfig()
        {
            try
            {
                string path = FunkenwerkSecurityVault.GetAppDataFilePath(VaultFileName);
                if (!File.Exists(path)) return new TahomaPersistedConfig();
                string raw = File.ReadAllText(path, Encoding.UTF8);
                string json = FunkenwerkSecurityVault.UnprotectSecret(raw);
                if (string.IsNullOrWhiteSpace(json)) return new TahomaPersistedConfig();
                return JsonSerializer.Deserialize<TahomaPersistedConfig>(json) ?? new TahomaPersistedConfig();
            }
            catch
            {
                return new TahomaPersistedConfig();
            }
        }

        public static void SaveConfig(TahomaPersistedConfig cfg)
        {
            try
            {
                string path = FunkenwerkSecurityVault.GetAppDataFilePath(VaultFileName);
                string json = JsonSerializer.Serialize(cfg, new JsonSerializerOptions { WriteIndented = true });
                string enc = FunkenwerkSecurityVault.ProtectSecret(json);
                FunkenwerkSecurityVault.WriteSecureFileText(path, enc);
            }
            catch { }
        }

        public static bool IsPrivateLanIp(string? ip)
        {
            if (string.IsNullOrWhiteSpace(ip) || !IPAddress.TryParse(ip.Trim(), out var parsed))
                return false;
            if (parsed.AddressFamily != AddressFamily.InterNetwork)
                return false;
            byte[] b = parsed.GetAddressBytes();
            return b[0] == 10 ||
                   (b[0] == 172 && b[1] >= 16 && b[1] <= 31) ||
                   (b[0] == 192 && b[1] == 168) ||
                   b[0] == 127;
        }

        public static bool VerifyOverkizCertificate(X509Certificate? cert, string? expectedPin)
        {
            if (cert == null) return false;
            string subject = cert.Subject ?? "";
            string issuer = cert.Issuer ?? "";
            if (!issuer.Contains("Overkiz", StringComparison.OrdinalIgnoreCase) &&
                !subject.Contains("Overkiz", StringComparison.OrdinalIgnoreCase))
            {
                return false;
            }
            if (!string.IsNullOrWhiteSpace(expectedPin))
            {
                var m = PinInCnRegex.Match(subject);
                if (m.Success && !string.Equals(m.Groups[1].Value, expectedPin.Trim(), StringComparison.OrdinalIgnoreCase))
                {
                    return false;
                }
            }
            return true;
        }

        private static HttpClient CreateLocalGatewayClient(string? expectedPin, int timeoutSec = 8)
        {
            var handler = new SocketsHttpHandler
            {
                ConnectTimeout = TimeSpan.FromSeconds(3),
                AllowAutoRedirect = false,
                SslOptions = new SslClientAuthenticationOptions
                {
                    RemoteCertificateValidationCallback = (_, cert, _, _) => VerifyOverkizCertificate(cert, expectedPin)
                }
            };
            return new HttpClient(handler)
            {
                Timeout = TimeSpan.FromSeconds(timeoutSec)
            };
        }

        public static async Task<TahomaGatewayProbeResult> ProbeGatewayAsync(string gatewayIp, string? knownPin = null)
        {
            var res = new TahomaGatewayProbeResult();
            if (!IsPrivateLanIp(gatewayIp))
            {
                res.StatusSummary = "Ungültige lokale IP-Adresse.";
                return res;
            }

            string ip = gatewayIp.Trim();

            // 1. Check port 8443 (Somfy / Connexoon Local API if enabled)
            res.DeveloperApiPort8443Open = await TryTcpPortAsync(ip, 8443, 550);
            if (res.DeveloperApiPort8443Open)
            {
                res.GatewayReachableOnLan = true;
                res.DetectedGatewayPin = await ExtractPinFromTlsCertAsync(ip, 8443) ?? (knownPin ?? "");
                res.StatusSummary = $"Connexoon Window lokal aktiv ({ip}:8443)";
                return res;
            }

            // 2. Check port 443
            bool port443 = await TryTcpPortAsync(ip, 443, 500);
            if (port443)
            {
                res.GatewayReachableOnLan = true;
                res.DetectedGatewayPin = await ExtractPinFromTlsCertAsync(ip, 443) ?? (knownPin ?? "");
                res.StatusSummary = $"Connexoon Window im WLAN ({ip})";
                return res;
            }

            // 3. Check ICMP Ping (Connexoon Window boxes initiate outbound Overkiz TLS and answer LAN Ping with 0 open inbound ports!)
            try
            {
                using var ping = new Ping();
                var reply = await ping.SendPingAsync(ip, 650);
                if (reply.Status == IPStatus.Success)
                {
                    res.GatewayReachableOnLan = true;
                    res.PingRttMs = reply.RoundtripTime;
                    res.DetectedGatewayPin = knownPin ?? "";
                    res.StatusSummary = $"Connexoon Window im WLAN online ({ip} · {reply.RoundtripTime} ms)";
                    return res;
                }
            }
            catch { }

            res.StatusSummary = $"Connexoon Window ({ip}) im WLAN nicht erreichbar";
            return res;
        }

        private static async Task<bool> TryTcpPortAsync(string ip, int port, int timeoutMs)
        {
            try
            {
                using var tcp = new TcpClient();
                using var cts = new CancellationTokenSource(timeoutMs);
                await tcp.ConnectAsync(ip, port, cts.Token);
                return tcp.Connected;
            }
            catch
            {
                return false;
            }
        }

        private static async Task<string?> ExtractPinFromTlsCertAsync(string ip, int port)
        {
            if (!IsPrivateLanIp(ip)) return null;
            try
            {
                using var tcp = new TcpClient();
                using var cts = new CancellationTokenSource(1200);
                await tcp.ConnectAsync(ip, port, cts.Token);
                using var ssl = new SslStream(tcp.GetStream(), false, (_, _, _, _) => true);
                await ssl.AuthenticateAsClientAsync(new SslClientAuthenticationOptions { TargetHost = ip }, cts.Token);
                if (ssl.RemoteCertificate != null)
                {
                    var m = PinInCnRegex.Match(ssl.RemoteCertificate.Subject ?? "");
                    if (m.Success) return m.Groups[1].Value;
                }
            }
            catch { }
            return null;
        }

        private static async Task<(bool Ok, CookieContainer? Cookies, string Error)> AuthenticateOverkizCloudSessionAsync(
            string somfyEmail,
            string somfyPassword,
            bool forceReauth = false)
        {
            if (string.IsNullOrWhiteSpace(somfyEmail) || string.IsNullOrWhiteSpace(somfyPassword))
                return (false, null, "Bitte Connexoon / Somfy E-Mail und Passwort hinterlegen.");

            string cleanEmail = somfyEmail.Trim();
            lock (SessionSync)
            {
                if (!forceReauth &&
                    _cachedCloudCookies != null &&
                    string.Equals(_cachedCloudEmail, cleanEmail, StringComparison.OrdinalIgnoreCase) &&
                    (DateTime.UtcNow - _cachedCloudAuthUtc).TotalMinutes < 25)
                {
                    return (true, _cachedCloudCookies, "");
                }
            }

            try
            {
                var cookies = new CookieContainer();
                using var handler = new HttpClientHandler
                {
                    CookieContainer = cookies,
                    UseCookies = true,
                    AllowAutoRedirect = false
                };
                using var client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(12) };
                client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                var loginForm = new FormUrlEncodedContent(new[]
                {
                    new KeyValuePair<string, string>("userId", cleanEmail),
                    new KeyValuePair<string, string>("userPassword", somfyPassword)
                });
                using var loginResp = await client.PostAsync($"{OverkizCloudBaseUrl}/login", loginForm);
                if (!loginResp.IsSuccessStatusCode)
                {
                    return (false, null, $"Connexoon / Somfy Anmeldung abgelehnt (HTTP {(int)loginResp.StatusCode}). Bitte E-Mail & Passwort prüfen.");
                }

                lock (SessionSync)
                {
                    _cachedCloudCookies = cookies;
                    _cachedCloudEmail = cleanEmail;
                    _cachedCloudAuthUtc = DateTime.UtcNow;
                }
                return (true, cookies, "");
            }
            catch (Exception ex)
            {
                return (false, null, $"Verbindung zu Somfy Connexoon fehlgeschlagen: {ex.Message}");
            }
        }

        /// <summary>
        /// Verbindet Somfy Connexoon Window (io / RTS) oder TaHoma automatisch:
        /// 1. Authentifiziert gegen Somfy Overkiz (ha101-1.overkiz.com).
        /// 2. Nutzt bevorzugt lokales Port-8443-Token, sofern auf der Box offen.
        /// 3. Fällt bei Connexoon Window (wo Port 8443 meist geschlossen ist) nahtlos auf die direkte
        ///    Connexoon-Overkiz-Steuerung zurück und speichert Zugangsdaten DPAPI-verschlüsselt.
        /// </summary>
        public static async Task<(bool Ok, string Mode, string LocalToken, List<TahomaShutterDevice> Shutters, string Message)> ConnectConnexoonOrLocalAsync(
            string somfyEmail,
            string somfyPasswordOrToken,
            string gatewayPin,
            string gatewayIp)
        {
            string cleanPin = string.IsNullOrWhiteSpace(gatewayPin) ? "0813-5321-9574" : gatewayPin.Trim();
            string cleanIp = string.IsNullOrWhiteSpace(gatewayIp) ? "192.168.188.29" : gatewayIp.Trim();

            // Fall A: Reines lokales Developer-Token ohne E-Mail
            if (string.IsNullOrWhiteSpace(somfyEmail) && !string.IsNullOrWhiteSpace(somfyPasswordOrToken))
            {
                var (locOk, locShutters, locErr) = await FetchShuttersAsync(cleanIp, somfyPasswordOrToken.Trim(), cleanPin);
                if (locOk)
                {
                    return (true, "local", somfyPasswordOrToken.Trim(), locShutters, $"Connexoon lokal verbunden ({locShutters.Count} Antriebe).");
                }
                return (false, "", "", new List<TahomaShutterDevice>(), locErr);
            }

            if (string.IsNullOrWhiteSpace(somfyEmail) || string.IsNullOrWhiteSpace(somfyPasswordOrToken))
                return (false, "", "", new List<TahomaShutterDevice>(), "Bitte Connexoon / Somfy E-Mail und Passwort eingeben.");

            // 1. Authentifiziere Somfy / Connexoon Window Konto
            var (authOk, cookies, authErr) = await AuthenticateOverkizCloudSessionAsync(somfyEmail, somfyPasswordOrToken, forceReauth: true);
            if (!authOk || cookies == null)
                return (false, "", "", new List<TahomaShutterDevice>(), authErr);

            // 2. Prüfe optional, ob lokaler Port 8443 offen ist und ein lokales Token unterstützt wird
            bool port8443 = IsPrivateLanIp(cleanIp) && await TryTcpPortAsync(cleanIp, 8443, 550);
            if (port8443 && Regex.IsMatch(cleanPin, @"^[0-9]{4}-[0-9]{4}-[0-9]{4}$"))
            {
                try
                {
                    using var handler = new HttpClientHandler { CookieContainer = cookies, UseCookies = true, AllowAutoRedirect = false };
                    using var client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(10) };
                    client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                    using var genResp = await client.GetAsync($"{OverkizCloudBaseUrl}/config/{cleanPin}/local/tokens/generate");
                    if (genResp.IsSuccessStatusCode)
                    {
                        string genBody = await genResp.Content.ReadAsStringAsync();
                        using var genDoc = JsonDocument.Parse(genBody);
                        string token = genDoc.RootElement.TryGetProperty("token", out var tokEl) && tokEl.ValueKind == JsonValueKind.String
                            ? (tokEl.GetString() ?? "").Trim()
                            : "";
                        if (!string.IsNullOrEmpty(token))
                        {
                            string activatePayload = JsonSerializer.Serialize(new { label = "Funkenwerk SmartHome", token, scope = "devmode" });
                            using var actContent = new StringContent(activatePayload, Encoding.UTF8, "application/json");
                            using var actResp = await client.PostAsync($"{OverkizCloudBaseUrl}/config/{cleanPin}/local/tokens", actContent);
                            if (actResp.IsSuccessStatusCode)
                            {
                                var (locOk, locShutters, _) = await FetchShuttersAsync(cleanIp, token, cleanPin);
                                if (locOk)
                                {
                                    return (true, "local", token, locShutters, $"Connexoon Window lokal verbunden ({locShutters.Count} Rollläden/Fenster).");
                                }
                            }
                        }
                    }
                }
                catch { }
            }

            // 3. Standardweg für Somfy Connexoon Window (ohne Port 8443): Direkter Gerätescan über Overkiz Connexoon API
            var (cloudOk, cloudShutters, cloudErr) = await FetchShuttersViaCloudCookiesAsync(cookies);
            if (!cloudOk)
                return (false, "", "", new List<TahomaShutterDevice>(), cloudErr);

            return (true, "connexoon_cloud", "", cloudShutters, $"Connexoon Window verbunden ({cloudShutters.Count} Rollläden/Fenster).");
        }

        public static async Task<(bool Ok, string LocalToken, string Message)> PairLocalDeveloperTokenAsync(
            string somfyEmail,
            string somfyPassword,
            string gatewayPin)
        {
            var res = await ConnectConnexoonOrLocalAsync(somfyEmail, somfyPassword, gatewayPin, "192.168.188.29");
            return (res.Ok, res.LocalToken, res.Message);
        }

        private static async Task<(bool Ok, List<TahomaShutterDevice> Shutters, string Error)> FetchShuttersViaCloudCookiesAsync(CookieContainer cookies)
        {
            try
            {
                using var handler = new HttpClientHandler { CookieContainer = cookies, UseCookies = true, AllowAutoRedirect = false };
                using var client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(10) };
                client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                using var resp = await client.GetAsync($"{OverkizCloudBaseUrl}/setup/devices");
                if (!resp.IsSuccessStatusCode)
                {
                    return (false, new List<TahomaShutterDevice>(), $"Connexoon antwortete mit HTTP {(int)resp.StatusCode}.");
                }
                string body = await resp.Content.ReadAsStringAsync();
                return (true, ParseDevicesJson(body), "");
            }
            catch (Exception ex)
            {
                return (false, new List<TahomaShutterDevice>(), $"Connexoon-Abruf fehlgeschlagen: {ex.Message}");
            }
        }

        public static bool HasStoredCredentials(TahomaPersistedConfig cfg)
        {
            if (!string.IsNullOrWhiteSpace(cfg.LocalBearerToken)) return true;
            string pwd = FunkenwerkSecurityVault.UnprotectSecret(cfg.SomfyPasswordProtected);
            return !string.IsNullOrWhiteSpace(cfg.SomfyEmail) && !string.IsNullOrWhiteSpace(pwd);
        }

        public static async Task<(bool Ok, string ModeUsed, List<TahomaShutterDevice> Shutters, string Error)> FetchShuttersAutoAsync(
            TahomaPersistedConfig cfg,
            bool port8443Open)
        {
            // 1. Lokale 8443-API falls offen & Token vorhanden
            if (port8443Open && !string.IsNullOrWhiteSpace(cfg.LocalBearerToken))
            {
                var loc = await FetchShuttersAsync(cfg.GatewayIp, cfg.LocalBearerToken, cfg.GatewayPin);
                if (loc.Ok) return (true, "local", loc.Shutters, "");
            }

            // 2. Somfy Connexoon Window (Overkiz API mit verschlüsseltem Konto)
            string pwd = FunkenwerkSecurityVault.UnprotectSecret(cfg.SomfyPasswordProtected);
            if (!string.IsNullOrWhiteSpace(cfg.SomfyEmail) && !string.IsNullOrWhiteSpace(pwd))
            {
                var (authOk, cookies, authErr) = await AuthenticateOverkizCloudSessionAsync(cfg.SomfyEmail, pwd, forceReauth: false);
                if (!authOk || cookies == null)
                    return (false, "connexoon_cloud", new List<TahomaShutterDevice>(), authErr);

                var res = await FetchShuttersViaCloudCookiesAsync(cookies);
                if (!res.Ok && res.Error.Contains("401", StringComparison.Ordinal))
                {
                    var retryAuth = await AuthenticateOverkizCloudSessionAsync(cfg.SomfyEmail, pwd, forceReauth: true);
                    if (retryAuth.Ok && retryAuth.Cookies != null)
                        res = await FetchShuttersViaCloudCookiesAsync(retryAuth.Cookies);
                }
                return (res.Ok, "connexoon_cloud", res.Shutters, res.Error);
            }

            return (false, "", new List<TahomaShutterDevice>(), "Noch nicht mit Connexoon Window gekoppelt.");
        }

        public static async Task<(bool Ok, List<TahomaShutterDevice> Shutters, string Error)> FetchShuttersAsync(
            string gatewayIp,
            string localToken,
            string? expectedPin = null)
        {
            if (!IsPrivateLanIp(gatewayIp))
                return (false, new List<TahomaShutterDevice>(), "Ungültige lokale Gateway-IP.");
            if (string.IsNullOrWhiteSpace(localToken))
                return (false, new List<TahomaShutterDevice>(), "Kein lokales Token hinterlegt.");

            try
            {
                using var client = CreateLocalGatewayClient(expectedPin, 8);
                string url = $"https://{gatewayIp.Trim()}:8443/enduser-mobile-web/1/enduserAPI/setup/devices";
                using var req = new HttpRequestMessage(HttpMethod.Get, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", localToken.Trim());
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                using var resp = await client.SendAsync(req);
                string body = await resp.Content.ReadAsStringAsync();
                if (!resp.IsSuccessStatusCode)
                {
                    return (false, new List<TahomaShutterDevice>(), $"Connexoon Lokal antwortete mit HTTP {(int)resp.StatusCode}.");
                }

                return (true, ParseDevicesJson(body), "");
            }
            catch (Exception ex)
            {
                return (false, new List<TahomaShutterDevice>(), $"Lokale Verbindung zu {gatewayIp}:8443 fehlgeschlagen ({ex.GetType().Name}).");
            }
        }

        public static List<TahomaShutterDevice> ParseDevicesJson(string json)
        {
            var list = new List<TahomaShutterDevice>();
            if (string.IsNullOrWhiteSpace(json)) return list;

            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.ValueKind != JsonValueKind.Array) return list;

            foreach (var dev in doc.RootElement.EnumerateArray())
            {
                string deviceUrl = dev.TryGetProperty("deviceURL", out var u) && u.ValueKind == JsonValueKind.String ? (u.GetString() ?? "") : "";
                string label = dev.TryGetProperty("label", out var l) && l.ValueKind == JsonValueKind.String ? (l.GetString() ?? "") : "";
                string controllable = dev.TryGetProperty("controllableName", out var c) && c.ValueKind == JsonValueKind.String ? (c.GetString() ?? "") : "";
                string uiClass = "";
                if (dev.TryGetProperty("definition", out var def) && def.ValueKind == JsonValueKind.Object &&
                    def.TryGetProperty("uiClass", out var uiEl) && uiEl.ValueKind == JsonValueKind.String)
                {
                    uiClass = uiEl.GetString() ?? "";
                }

                // Exclude pure sensors / gateway pods (e.g. WindowHandle / ContactSensor / SunSensor / ProtocolGateway)
                bool isSensorOrPod =
                    uiClass.Contains("Sensor", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Contains("Gateway", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Equals("Pod", StringComparison.OrdinalIgnoreCase) ||
                    controllable.Contains("Sensor", StringComparison.OrdinalIgnoreCase) ||
                    controllable.Contains("Pod", StringComparison.OrdinalIgnoreCase);

                // Connexoon Window io & RTS actuators: RollerShutter, ExteriorVenetianBlind, Window/RoofWindow, Screen, Awning, Curtain, Pergola
                bool isShutterOrWindowActuator = !isSensorOrPod && (
                    uiClass.Contains("Shutter", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Contains("Window", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Contains("Screen", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Contains("Blind", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Contains("Awning", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Contains("Curtain", StringComparison.OrdinalIgnoreCase) ||
                    uiClass.Contains("Pergola", StringComparison.OrdinalIgnoreCase) ||
                    controllable.Contains("Shutter", StringComparison.OrdinalIgnoreCase) ||
                    controllable.Contains("WindowOpener", StringComparison.OrdinalIgnoreCase) ||
                    controllable.Contains("Blind", StringComparison.OrdinalIgnoreCase) ||
                    controllable.Contains("ExteriorScreen", StringComparison.OrdinalIgnoreCase) ||
                    controllable.Contains("Awning", StringComparison.OrdinalIgnoreCase));

                if (!isShutterOrWindowActuator || string.IsNullOrWhiteSpace(deviceUrl))
                    continue;

                bool supportsPosition = !deviceUrl.StartsWith("rts://", StringComparison.OrdinalIgnoreCase);
                int closure = 0;
                bool hasNumericClosure = false;
                bool available = (dev.TryGetProperty("available", out var av) && av.ValueKind == JsonValueKind.True) || !dev.TryGetProperty("available", out _);

                if (dev.TryGetProperty("states", out var states) && states.ValueKind == JsonValueKind.Array)
                {
                    foreach (var st in states.EnumerateArray())
                    {
                        string stName = st.TryGetProperty("name", out var sn) && sn.ValueKind == JsonValueKind.String ? (sn.GetString() ?? "") : "";
                        if ((stName.Equals("core:ClosureState", StringComparison.OrdinalIgnoreCase) ||
                             stName.Equals("core:TargetClosureState", StringComparison.OrdinalIgnoreCase) ||
                             stName.Equals("core:DeploymentState", StringComparison.OrdinalIgnoreCase)) &&
                            st.TryGetProperty("value", out var sv) && sv.ValueKind == JsonValueKind.Number &&
                            sv.TryGetInt32(out int pct))
                        {
                            if (!hasNumericClosure || stName.Equals("core:ClosureState", StringComparison.OrdinalIgnoreCase))
                            {
                                closure = Math.Clamp(pct, 0, 100);
                                hasNumericClosure = true;
                            }
                        }
                        else if (!hasNumericClosure &&
                                 stName.Equals("core:OpenClosedState", StringComparison.OrdinalIgnoreCase) &&
                                 st.TryGetProperty("value", out var ocs) && ocs.ValueKind == JsonValueKind.String)
                        {
                            closure = string.Equals(ocs.GetString(), "closed", StringComparison.OrdinalIgnoreCase) ? 100 : 0;
                        }
                    }
                }

                list.Add(new TahomaShutterDevice
                {
                    DeviceUrl = deviceUrl,
                    Label = string.IsNullOrWhiteSpace(label) ? "Rollladen" : label.Trim(),
                    ControllableName = controllable,
                    ClosurePercent = closure,
                    SupportsPosition = supportsPosition,
                    IsAvailable = available
                });
            }

            return list.OrderBy(s => s.Label, StringComparer.OrdinalIgnoreCase).ToList();
        }

        private static string BuildApplyPayload(List<TahomaShutterDevice> targetList, string actionCommand, int? closurePercent, out string resolvedCmd)
        {
            string cmd = actionCommand.Trim().ToLowerInvariant() switch
            {
                "open" or "auf" or "up" => "open",
                "close" or "zu" or "down" => "close",
                "stop" or "stopp" => "stop",
                "my" or "fav" => "my",
                "position" or "setclosure" => "setClosure",
                _ => "stop"
            };
            resolvedCmd = cmd;

            object[] parameters = (cmd == "setClosure" && closurePercent.HasValue)
                ? new object[] { Math.Clamp(closurePercent.Value, 0, 100) }
                : Array.Empty<object>();

            var actions = targetList.Select(t => new
            {
                deviceURL = t.DeviceUrl,
                commands = new[]
                {
                    new
                    {
                        name = (cmd == "setClosure" && !t.SupportsPosition)
                            ? (closurePercent.GetValueOrDefault(50) >= 50 ? "close" : "open")
                            : cmd,
                        parameters = (cmd == "setClosure" && !t.SupportsPosition) ? Array.Empty<object>() : parameters
                    }
                }
            }).ToArray();

            return JsonSerializer.Serialize(new
            {
                label = $"Funkenwerk Connexoon ({cmd})",
                actions
            });
        }

        public static async Task<(bool Ok, string Message)> ExecuteShutterActionAutoAsync(
            TahomaPersistedConfig cfg,
            IEnumerable<TahomaShutterDevice> targets,
            string actionCommand,
            int? closurePercent = null)
        {
            var targetList = targets.Where(t => !string.IsNullOrWhiteSpace(t.DeviceUrl)).ToList();
            if (targetList.Count == 0)
                return (false, "Kein Rollladen ausgewählt.");

            // 1. Lokal über :8443 falls lokales Token vorhanden und Modus nicht explizit Cloud
            if (!string.IsNullOrWhiteSpace(cfg.LocalBearerToken) &&
                !string.Equals(cfg.ConnectionMode, "connexoon_cloud", StringComparison.OrdinalIgnoreCase))
            {
                var locRes = await ExecuteShutterActionAsync(cfg.GatewayIp, cfg.LocalBearerToken, targetList, actionCommand, closurePercent, cfg.GatewayPin);
                if (locRes.Ok) return locRes;
            }

            // 2. Somfy Connexoon Window (Overkiz API)
            string pwd = FunkenwerkSecurityVault.UnprotectSecret(cfg.SomfyPasswordProtected);
            if (!string.IsNullOrWhiteSpace(cfg.SomfyEmail) && !string.IsNullOrWhiteSpace(pwd))
            {
                string payload = BuildApplyPayload(targetList, actionCommand, closurePercent, out string cmd);
                for (int attempt = 0; attempt < 2; attempt++)
                {
                    var (authOk, cookies, authErr) = await AuthenticateOverkizCloudSessionAsync(cfg.SomfyEmail, pwd, forceReauth: attempt > 0);
                    if (!authOk || cookies == null)
                        return (false, authErr);

                    try
                    {
                        using var handler = new HttpClientHandler { CookieContainer = cookies, UseCookies = true, AllowAutoRedirect = false };
                        using var client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(10) };
                        client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                        using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                        using var resp = await client.PostAsync($"{OverkizCloudBaseUrl}/exec/apply", content);
                        if ((int)resp.StatusCode == 401 && attempt == 0)
                            continue;
                        if (!resp.IsSuccessStatusCode)
                            return (false, $"Connexoon lehnte den Befehl ab (HTTP {(int)resp.StatusCode}).");

                        string names = string.Join(", ", targetList.Select(t => t.Label));
                        return (true, $"{names}: {cmd}" + (closurePercent.HasValue && cmd == "setClosure" ? $" ({closurePercent.Value} %)" : ""));
                    }
                    catch (Exception ex)
                    {
                        return (false, $"Connexoon-Befehl fehlgeschlagen: {ex.Message}");
                    }
                }
            }

            return (false, "Bitte zuerst über 'Connexoon verbinden' anmelden.");
        }

        public static async Task<(bool Ok, string Message)> ExecuteShutterActionAsync(
            string gatewayIp,
            string localToken,
            IEnumerable<TahomaShutterDevice> targets,
            string actionCommand,
            int? closurePercent = null,
            string? expectedPin = null)
        {
            var targetList = targets.Where(t => !string.IsNullOrWhiteSpace(t.DeviceUrl)).ToList();
            if (targetList.Count == 0)
                return (false, "Kein Rollladen ausgewählt.");
            if (!IsPrivateLanIp(gatewayIp))
                return (false, "Ungültige lokale Gateway-IP.");
            if (string.IsNullOrWhiteSpace(localToken))
                return (false, "Kein lokales Token hinterlegt.");

            string payload = BuildApplyPayload(targetList, actionCommand, closurePercent, out string cmd);

            try
            {
                using var client = CreateLocalGatewayClient(expectedPin, 8);
                string url = $"https://{gatewayIp.Trim()}:8443/enduser-mobile-web/1/enduserAPI/exec/apply";
                using var req = new HttpRequestMessage(HttpMethod.Post, url)
                {
                    Content = new StringContent(payload, Encoding.UTF8, "application/json")
                };
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", localToken.Trim());
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                using var resp = await client.SendAsync(req);
                if (!resp.IsSuccessStatusCode)
                {
                    return (false, $"Connexoon lehnte den Befehl ab (HTTP {(int)resp.StatusCode}).");
                }

                string names = string.Join(", ", targetList.Select(t => t.Label));
                return (true, $"{names}: {cmd}" + (closurePercent.HasValue && cmd == "setClosure" ? $" ({closurePercent.Value} %)" : ""));
            }
            catch (Exception ex)
            {
                return (false, $"Connexoon-Befehl fehlgeschlagen: {ex.Message}");
            }
        }
    }

    public static class SmartHomeSelfTest
    {
        public static async Task<bool> RunAsync()
        {
            await Task.Yield();
            if (!SomfyTahomaLocalEngine.IsPrivateLanIp("192.168.188.29"))
            {
                Console.Error.WriteLine("FAIL: 192.168.188.29 must be recognized as private LAN IP.");
                return false;
            }
            if (SomfyTahomaLocalEngine.IsPrivateLanIp("8.8.8.8"))
            {
                Console.Error.WriteLine("FAIL: Public IP must be rejected by LAN guard.");
                return false;
            }

            const string sampleDevicesJson = @"[
                {
                    ""deviceURL"": ""io://0813-5321-9574/1234567"",
                    ""label"": ""Büro Rollladen"",
                    ""controllableName"": ""io:RollerShutterGenericIOComponent"",
                    ""available"": true,
                    ""definition"": { ""uiClass"": ""RollerShutter"" },
                    ""states"": [ { ""name"": ""core:ClosureState"", ""value"": 35 } ]
                },
                {
                    ""deviceURL"": ""io://0813-5321-9574/2345678"",
                    ""label"": ""Dachfenster Atelier"",
                    ""controllableName"": ""io:WindowOpenerVeluxIOComponent"",
                    ""available"": true,
                    ""definition"": { ""uiClass"": ""Window"" },
                    ""states"": [ { ""name"": ""core:TargetClosureState"", ""value"": 100 } ]
                },
                {
                    ""deviceURL"": ""rts://0813-5321-9574/3456789"",
                    ""label"": ""Terrasse Markise"",
                    ""controllableName"": ""rts:RollerShutterRTSComponent"",
                    ""available"": true,
                    ""definition"": { ""uiClass"": ""Awning"" },
                    ""states"": [ { ""name"": ""core:OpenClosedState"", ""value"": ""closed"" } ]
                },
                {
                    ""deviceURL"": ""io://0813-5321-9574/8888888"",
                    ""label"": ""Fenstergriff Sensor"",
                    ""controllableName"": ""io:ThreeWayWindowHandleIOSystemSensor"",
                    ""definition"": { ""uiClass"": ""WindowHandle"" }
                },
                {
                    ""deviceURL"": ""io://0813-5321-9574/9999999"",
                    ""label"": ""Temperatursensor"",
                    ""controllableName"": ""io:TemperatureIOSystemSensor"",
                    ""definition"": { ""uiClass"": ""TemperatureSensor"" }
                }
            ]";

            var parsed = SomfyTahomaLocalEngine.ParseDevicesJson(sampleDevicesJson);
            if (parsed.Count != 3 ||
                parsed[0].Label != "Büro Rollladen" || parsed[0].ClosurePercent != 35 || !parsed[0].SupportsPosition ||
                parsed[1].Label != "Dachfenster Atelier" || parsed[1].ClosurePercent != 100 || !parsed[1].SupportsPosition ||
                parsed[2].Label != "Terrasse Markise" || parsed[2].ClosurePercent != 100 || parsed[2].SupportsPosition)
            {
                Console.Error.WriteLine("FAIL: ParseDevicesJson did not extract Connexoon Window devices accurately.");
                return false;
            }

            var ranked = CollectionModuleRegistry.FilterAndRankModules("connexoon");
            var rankedTypo = CollectionModuleRegistry.FilterAndRankModules("connecoon");
            if (ranked.Count == 0 || ranked[0].Key != "smarthome" ||
                rankedTypo.Count == 0 || rankedTypo[0].Key != "smarthome")
            {
                Console.Error.WriteLine("FAIL: CollectionModuleRegistry must rank SmartHome #1 for 'connexoon' / 'connecoon'.");
                return false;
            }

            var win = new MainWindow();
            var groupAndCamTestOk = win.RunGroupResolutionSelfTest();
            win.ApplyThemeForSelfTest(false);
            bool lightOk = !win.IsDarkModeActive &&
                           win.Background is System.Windows.Media.SolidColorBrush lBg && lBg.Color.R > 220;
            win.ApplyThemeForSelfTest(true);
            bool darkOk = win.IsDarkModeActive &&
                          win.Background is System.Windows.Media.SolidColorBrush dBg && dBg.Color.R < 35;

            bool wanRtspBlocked = !FunkenwerkSecurityVault.IsSafeRtspUrl("rtsp://admin:pass@8.8.8.8:554/stream1")
                               && !OnvifPtzClient.IsSafeCustomOnvifServiceUrl("http://8.8.8.8:2020/onvif/device_service")
                               && !OnvifPtzClient.IsSafeCustomOnvifServiceUrl("http://admin:pass@192.168.188.140:2020/onvif/device_service")
                               && FunkenwerkSecurityVault.IsSafeRtspUrl("rtsp://admin:pass@192.168.188.140:554/stream2")
                               && OnvifPtzClient.IsSafeCustomOnvifServiceUrl("http://192.168.188.140:2020/onvif/device_service");

            win.Close();
            if (!lightOk || !darkOk || !groupAndCamTestOk || !wanRtspBlocked)
            {
                Console.Error.WriteLine($"FAIL: SmartHome UI/Group/Camera check failed (Light={lightOk}, Dark={darkOk}, GroupAndCam={groupAndCamTestOk}, WanBlocked={wanRtspBlocked}).");
                return false;
            }

            var (secAuditOk, secReport) = FunkenwerkSecurityVault.RunSecuritySelfAudit(
                FunkenwerkSecurityVault.GetAppDataFilePath("settings.json", migrateFromBaseDir: false),
                FunkenwerkSecurityVault.GetAppDataFilePath("smarthome.json", migrateFromBaseDir: false));
            if (!secAuditOk)
            {
                Console.Error.WriteLine("FAIL: Security vault audit failed:\n" + secReport);
                return false;
            }

            Console.WriteLine("PASS: FunkenwerkSmartHome v1.5.0 (Build 13) Connexoon Window + Groups + Live Camera Surveillance + Security self-test (10/10 checks passed).");
            return true;
        }
    }
}

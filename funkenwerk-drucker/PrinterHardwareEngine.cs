using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Security;
using System.Net.Sockets;
using System.Printing;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows.Media.Imaging;
using System.Xml;
using System.Xml.Linq;
using Microsoft.Win32;

namespace FunkenwerkDrucker
{
    public static class PrinterHardwareEngine
    {
        public const int MaxControlPayloadBytes = 512 * 1024;           // 512 KB cap for XML / IPP responses
        public const int MaxScanImagePayloadBytes = 64 * 1024 * 1024;   // 64 MB cap per scanned image
        public const int MaxXmlCharacters = 262_144;                    // 256K char cap for XmlReader

        private static readonly Regex HostTokenRegex = new(@"\b([A-Za-z]{2}[0-9A-Fa-f]{6}|NPI[0-9A-Fa-f]{6}|BRW[0-9A-Fa-f]{6,12}|EPSON[0-9A-Fa-f]{6})\b", RegexOptions.Compiled);
        private static readonly Regex SafeLanHostnameRegex = new(@"^[A-Za-z0-9\-]{2,63}(\.(local|fritz\.box))?$", RegexOptions.Compiled | RegexOptions.IgnoreCase | RegexOptions.CultureInvariant);
        private static readonly Regex SafeEsclJobPathRegex = new(@"^/eSCL/ScanJobs/[A-Za-z0-9._\-]+$", RegexOptions.Compiled | RegexOptions.CultureInvariant);

        /// <summary>
        /// Strictly allows ONLY RFC1918 private LAN IPv4 host addresses (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16).
        /// Rejects loopback (127.0.0.0/8, ::1), link-local/metadata (169.254.0.0/16), CGNAT (100.64.0.0/10),
        /// 0.0.0.0, .0 network / .255 broadcast addresses, and all public Internet / IPv6 addresses.
        /// </summary>
        public static bool IsPrivateLanIp(IPAddress? ip)
        {
            if (ip == null || ip.AddressFamily != AddressFamily.InterNetwork)
                return false;

            byte[] b = ip.GetAddressBytes();
            if (b.Length != 4)
                return false;

            // Reject .0 network and .255 broadcast octets (including 0.0.0.0 and 255.255.255.255)
            if (b[3] == 0 || b[3] == 255)
                return false;

            if (b[0] == 10)
                return true;

            if (b[0] == 172 && b[1] >= 16 && b[1] <= 31)
                return true;

            if (b[0] == 192 && b[1] == 168)
                return true;

            return false;
        }

        internal static bool IsAllowedTargetIp(IPAddress? ip, bool allowLoopbackForSelfTest = false)
        {
            if (ip == null) return false;
            if (IsPrivateLanIp(ip)) return true;
            if (allowLoopbackForSelfTest && ip.AddressFamily == AddressFamily.InterNetwork && IPAddress.IsLoopback(ip))
                return true;
            return false;
        }

        public static bool TryValidateCustomPrinterTarget(string? rawInput, out string cleaned, out string errorMessage)
        {
            cleaned = "";
            errorMessage = "";

            string trimmed = (rawInput ?? "").Trim();
            if (string.IsNullOrEmpty(trimmed))
                return true;

            if (trimmed.Any(c => char.IsControl(c) || char.IsWhiteSpace(c)) ||
                trimmed.Contains('/') || trimmed.Contains('\\') || trimmed.Contains('@'))
            {
                errorMessage = "Ungültige Eingabe. Bitte nur eine private Heimnetz-IPv4 (z. B. 192.168.188.26) eingeben.";
                return false;
            }

            // Any IPv6 or numeric IPv4 candidate must parse as a strict RFC1918 private LAN IPv4
            if (trimmed.Contains(':') || trimmed.All(c => char.IsDigit(c) || c == '.'))
            {
                if (IPAddress.TryParse(trimmed, out var parsedIp) && IsPrivateLanIp(parsedIp))
                {
                    cleaned = parsedIp.ToString();
                    return true;
                }

                errorMessage = "Nur private Heimnetz-IPv4-Adressen erlaubt (192.168.x.x, 10.x.x.x, 172.16-31.x.x).";
                return false;
            }

            if (IPAddress.TryParse(trimmed, out var anyIp))
            {
                if (IsPrivateLanIp(anyIp))
                {
                    cleaned = anyIp.ToString();
                    return true;
                }
                errorMessage = "Nur private Heimnetz-IPv4-Adressen erlaubt.";
                return false;
            }

            if (SafeLanHostnameRegex.IsMatch(trimmed))
            {
                cleaned = trimmed;
                return true;
            }

            errorMessage = "Nur private Heimnetz-IPv4-Adressen oder lokale Druckernamen (.local / .fritz.box) erlaubt.";
            return false;
        }

        internal static HttpClient CreateLocalLanHttpClient(TimeSpan timeout, IPAddress pinnedTargetIp, bool allowLoopbackForSelfTest = false)
        {
            if (!IsAllowedTargetIp(pinnedTargetIp, allowLoopbackForSelfTest))
                throw new InvalidOperationException("Ziel-IP ist keine zulässige private Heimnetz-Adresse.");

            var handler = new SocketsHttpHandler
            {
                AllowAutoRedirect = false,
                UseProxy = false,
                ConnectTimeout = TimeSpan.FromMilliseconds(1400),
                SslOptions =
                {
                    // Self-signed printer TLS certificates are accepted ONLY when the TLS target host matches the verified private LAN IP
                    RemoteCertificateValidationCallback = (sender, cert, chain, errors) =>
                    {
                        if (errors == SslPolicyErrors.None)
                            return true;

                        if (!IsAllowedTargetIp(pinnedTargetIp, allowLoopbackForSelfTest))
                            return false;

                        if (sender is SslStream ssl)
                        {
                            string targetHost = ssl.TargetHostName ?? "";
                            return IPAddress.TryParse(targetHost, out var certHostIp) &&
                                   certHostIp.Equals(pinnedTargetIp) &&
                                   IsAllowedTargetIp(certHostIp, allowLoopbackForSelfTest);
                        }

                        if (sender is HttpRequestMessage req && req.RequestUri != null)
                        {
                            return IPAddress.TryParse(req.RequestUri.Host, out var reqIp) &&
                                   reqIp.Equals(pinnedTargetIp) &&
                                   IsAllowedTargetIp(reqIp, allowLoopbackForSelfTest);
                        }

                        return false;
                    }
                }
            };
            return new HttpClient(handler) { Timeout = timeout };
        }

        internal static async Task<HttpResponseMessage> SendLanGetAsync(
            HttpClient http,
            Uri requestUri,
            IPAddress expectedTargetIp,
            bool allowLoopbackForSelfTest = false,
            CancellationToken ct = default)
        {
            if (!IsAllowedTargetIp(expectedTargetIp, allowLoopbackForSelfTest) ||
                !IPAddress.TryParse(requestUri.Host, out var initialIp) ||
                !initialIp.Equals(expectedTargetIp))
            {
                throw new InvalidOperationException("Netzwerkzugriff außerhalb des privaten Heimnetzes blockiert.");
            }

            var resp = await http.GetAsync(requestUri, HttpCompletionOption.ResponseHeadersRead, ct);
            int code = (int)resp.StatusCode;
            if (code is 301 or 302 or 303 or 307 or 308)
            {
                Uri? loc = resp.Headers.Location;
                resp.Dispose();

                if (loc == null)
                    throw new InvalidOperationException("Ungültige Weiterleitung ohne Zieladresse.");

                Uri redirectUri = loc.IsAbsoluteUri ? loc : new Uri(requestUri, loc);

                // Strictly ONLY allow same-IP HTTP -> HTTPS upgrade after re-verifying IsAllowedTargetIp!
                bool isSameIpHttpsUpgrade =
                    string.Equals(requestUri.Scheme, "http", StringComparison.OrdinalIgnoreCase) &&
                    string.Equals(redirectUri.Scheme, "https", StringComparison.OrdinalIgnoreCase) &&
                    IPAddress.TryParse(redirectUri.Host, out var redirIp) &&
                    redirIp.Equals(expectedTargetIp) &&
                    IsAllowedTargetIp(redirIp, allowLoopbackForSelfTest);

                if (!isSameIpHttpsUpgrade)
                    throw new InvalidOperationException("Weiterleitung auf externe oder unzulässige Adresse blockiert.");

                return await http.GetAsync(redirectUri, HttpCompletionOption.ResponseHeadersRead, ct);
            }

            return resp;
        }

        internal static async Task<byte[]> ReadBoundedResponseBytesAsync(
            HttpResponseMessage resp,
            int maxBytes,
            CancellationToken ct = default)
        {
            long? declaredLen = resp.Content.Headers.ContentLength;
            if (declaredLen.HasValue && declaredLen.Value > maxBytes)
                throw new InvalidOperationException($"Antwort überschreitet das Sicherheitslimit ({maxBytes} Bytes).");

            await using var stream = await resp.Content.ReadAsStreamAsync(ct);
            using var ms = new MemoryStream(declaredLen.HasValue && declaredLen.Value > 0
                ? (int)Math.Min(declaredLen.Value, maxBytes)
                : 8192);

            byte[] buffer = new byte[16 * 1024];
            int total = 0;
            while (true)
            {
                int read = await stream.ReadAsync(buffer.AsMemory(0, buffer.Length), ct);
                if (read <= 0) break;
                total += read;
                if (total > maxBytes)
                    throw new InvalidOperationException($"Antwortstrom überschreitet das Sicherheitslimit ({maxBytes} Bytes).");
                ms.Write(buffer, 0, read);
            }
            return ms.ToArray();
        }

        internal static async Task<string> ReadBoundedResponseStringAsync(
            HttpResponseMessage resp,
            int maxBytes,
            CancellationToken ct = default)
        {
            byte[] bytes = await ReadBoundedResponseBytesAsync(resp, maxBytes, ct);
            return Encoding.UTF8.GetString(bytes);
        }

        internal static XDocument ParseSafeXmlDocument(string xml)
        {
            if (string.IsNullOrWhiteSpace(xml))
                throw new XmlException("Leeres XML-Dokument.");

            var settings = new XmlReaderSettings
            {
                DtdProcessing = DtdProcessing.Prohibit,
                XmlResolver = null,
                MaxCharactersInDocument = MaxXmlCharacters,
                MaxCharactersFromEntities = 1024,
                IgnoreComments = true,
                IgnoreProcessingInstructions = true
            };

            using var sr = new StringReader(xml);
            using var xr = XmlReader.Create(sr, settings);
            return XDocument.Load(xr, LoadOptions.None);
        }

        public static async Task<List<DiscoveredPrinterInfo>> DiscoverPrintersAsync(string? customIpOverride = null)
        {
            var rawList = await Task.Run(() =>
            {
                var list = new List<DiscoveredPrinterInfo>();
                string defaultQueueName = "";
                try
                {
                    using var localSrv = new LocalPrintServer();
                    defaultQueueName = localSrv.DefaultPrintQueue?.FullName ?? "";
                }
                catch { }

                try
                {
                    using var server = new PrintServer();
                    var flags = new[] { EnumeratedPrintQueueTypes.Local, EnumeratedPrintQueueTypes.Connections };
                    foreach (var q in server.GetPrintQueues(flags))
                    {
                        try
                        {
                            q.Refresh();
                            string name = q.FullName ?? q.Name ?? "";
                            if (string.IsNullOrWhiteSpace(name)) continue;

                            string driver = q.QueueDriver?.Name ?? "";
                            string port = q.QueuePort?.Name ?? "";
                            bool isVirtual = IsVirtualQueue(name, driver, port);
                            bool isWsd = port.StartsWith("WSD", StringComparison.OrdinalIgnoreCase);

                            bool offline = q.IsOffline ||
                                           (q.QueueStatus & PrintQueueStatus.Offline) != 0 ||
                                           (q.QueueStatus & PrintQueueStatus.NotAvailable) != 0;

                            string statusTxt = "Bereit";
                            if ((q.QueueStatus & PrintQueueStatus.PaperOut) != 0) statusTxt = "Papier leer";
                            else if ((q.QueueStatus & PrintQueueStatus.PaperJam) != 0) statusTxt = "Papierstau";
                            else if ((q.QueueStatus & PrintQueueStatus.Error) != 0) statusTxt = "Fehler";
                            else if (offline) statusTxt = "Offline";
                            else if (q.NumberOfJobs > 0) statusTxt = $"{q.NumberOfJobs} Auftrag/Aufträge";

                            list.Add(new DiscoveredPrinterInfo
                            {
                                QueueName = name,
                                DisplayName = CleanDisplayName(name),
                                DriverName = driver,
                                PortName = port,
                                IsWsdPort = isWsd,
                                IsVirtualPrinter = isVirtual,
                                IsDefault = string.Equals(name, defaultQueueName, StringComparison.OrdinalIgnoreCase),
                                SpoolerReportsOffline = offline,
                                SpoolerStatusText = statusTxt,
                                ActiveJobCount = Math.Max(0, q.NumberOfJobs)
                            });
                        }
                        catch { }
                        finally
                        {
                            try { q.Dispose(); } catch { }
                        }
                    }
                }
                catch { }

                return list
                    .OrderBy(p => p.IsVirtualPrinter ? 1 : 0)
                    .ThenByDescending(p => p.QueueName.Contains("HP", StringComparison.OrdinalIgnoreCase) ? 1 : 0)
                    .ThenByDescending(p => p.IsDefault ? 1 : 0)
                    .ThenBy(p => p.DisplayName)
                    .ToList();
            });

            var probeTasks = rawList
                .Where(p => !p.IsVirtualPrinter)
                .Select(p => EnrichPrinterNetworkStateAsync(p, customIpOverride))
                .ToArray();

            if (probeTasks.Length > 0)
            {
                await Task.WhenAll(probeTasks);
            }

            return rawList;
        }

        private static bool IsVirtualQueue(string name, string driver, string port)
        {
            string n = name.ToLowerInvariant();
            string p = port.ToLowerInvariant();
            return n.Contains("print to pdf") ||
                   n.Contains("xps document") ||
                   n.Contains("onenote") ||
                   n.Contains("fax") ||
                   p == "portprompt:" ||
                   p == "nul:";
        }

        private static string CleanDisplayName(string raw)
        {
            var m = Regex.Match(raw, @"^([A-Z0-9]{6,12})\s*\((.+?)\)$");
            if (m.Success)
            {
                string host = m.Groups[1].Value;
                string model = m.Groups[2].Value.Replace(" series", "", StringComparison.OrdinalIgnoreCase).Trim();
                return $"{model} ({host})";
            }
            return raw;
        }

        public static async Task EnrichPrinterNetworkStateAsync(DiscoveredPrinterInfo printer, string? customIpOverride = null)
        {
            string candidateHost = "";
            if (!string.IsNullOrWhiteSpace(customIpOverride) &&
                TryValidateCustomPrinterTarget(customIpOverride, out string validatedTarget, out _))
            {
                candidateHost = validatedTarget;
            }
            else
            {
                candidateHost = TryGetTcpPortHostFromRegistry(printer.PortName);
                if (string.IsNullOrWhiteSpace(candidateHost))
                {
                    var m = HostTokenRegex.Match(printer.QueueName);
                    if (m.Success)
                    {
                        candidateHost = m.Groups[1].Value;
                    }
                }
            }

            if (string.IsNullOrWhiteSpace(candidateHost))
                return;

            printer.ResolvedHostOrIp = candidateHost;
            IPAddress? resolvedIp = await ResolvePrivateLanIpAsync(candidateHost);
            if (resolvedIp == null)
                return;

            string ipStr = resolvedIp.ToString();
            printer.ResolvedIpAddress = ipStr;

            Task<bool> t80 = CheckTcpPortAsync(ipStr, 80, 750);
            Task<bool> t443 = CheckTcpPortAsync(ipStr, 443, 750);
            Task<bool> t631 = CheckTcpPortAsync(ipStr, 631, 750);
            Task<bool> t9100 = CheckTcpPortAsync(ipStr, 9100, 750);

            await Task.WhenAll(t80, t443, t631, t9100);

            printer.LanHttpOnline = t80.Result || t443.Result;
            printer.LanIppOnline = t631.Result;
            printer.LanRaw9100Online = t9100.Result;

            if (printer.LanHttpOnline)
            {
                await QueryHpLedmAndEsclTelemetryAsync(printer, resolvedIp, preferHttps: !t80.Result && t443.Result);
            }

            if ((printer.BlackInkPercent == null && printer.ColorInkPercent == null) && printer.LanIppOnline)
            {
                await QueryIppAttributesFallbackAsync(printer, resolvedIp);
            }
        }

        private static string TryGetTcpPortHostFromRegistry(string portName)
        {
            if (string.IsNullOrWhiteSpace(portName) || portName.IndexOfAny(new[] { '\\', '/' }) >= 0)
                return "";

            try
            {
                using var key = Registry.LocalMachine.OpenSubKey($@"SYSTEM\CurrentControlSet\Control\Print\Monitors\Standard TCP/IP Port\Ports\{portName}");
                string? host = key?.GetValue("HostName")?.ToString();
                if (!string.IsNullOrWhiteSpace(host)) return host;
                string? ip = key?.GetValue("IPAddress")?.ToString();
                if (!string.IsNullOrWhiteSpace(ip)) return ip;
            }
            catch { }

            if (portName.StartsWith("IP_", StringComparison.OrdinalIgnoreCase))
                return portName.Substring(3);

            return "";
        }

        public static async Task<IPAddress?> ResolvePrivateLanIpAsync(string hostOrIp)
        {
            if (string.IsNullOrWhiteSpace(hostOrIp)) return null;
            string trimmed = hostOrIp.Trim();

            if (IPAddress.TryParse(trimmed, out var directIp))
            {
                return IsPrivateLanIp(directIp) ? directIp : null;
            }

            if (!SafeLanHostnameRegex.IsMatch(trimmed))
                return null;

            string[] candidates = trimmed.Contains('.')
                ? new[] { trimmed }
                : new[] { $"{trimmed}.fritz.box", $"{trimmed}.local", trimmed };

            foreach (string name in candidates)
            {
                try
                {
                    var addresses = await Dns.GetHostAddressesAsync(name);
                    var ipv4 = addresses.FirstOrDefault(IsPrivateLanIp);
                    if (ipv4 != null) return ipv4;
                }
                catch { }
            }
            return null;
        }

        private static async Task<bool> CheckTcpPortAsync(string ip, int port, int timeoutMs)
        {
            if (!IPAddress.TryParse(ip, out var parsed) || !IsPrivateLanIp(parsed))
                return false;

            try
            {
                using var tcp = new TcpClient();
                using var cts = new CancellationTokenSource(timeoutMs);
                await tcp.ConnectAsync(parsed, port, cts.Token);
                return tcp.Connected;
            }
            catch
            {
                return false;
            }
        }

        // =====================================================================
        // ABOFREIE TINTENSTAND- & GERÄTESTATUS-ABFRAGE (HP LEDM XML + eSCL)
        // =====================================================================
        private static async Task QueryHpLedmAndEsclTelemetryAsync(DiscoveredPrinterInfo printer, IPAddress targetIp, bool preferHttps)
        {
            if (!IsPrivateLanIp(targetIp)) return;

            string scheme = preferHttps ? "https" : "http";
            string ip = targetIp.ToString();
            using var http = CreateLocalLanHttpClient(TimeSpan.FromMilliseconds(2200), targetIp);

            try
            {
                using var resp = await SendLanGetAsync(http, new Uri($"{scheme}://{ip}/DevMgmt/ConsumableConfigDyn.xml"), targetIp);
                if (resp.IsSuccessStatusCode)
                {
                    string xml = await ReadBoundedResponseStringAsync(resp, MaxControlPayloadBytes);
                    var (black, color) = ParseHpConsumableXml(xml);
                    if (black.HasValue) printer.BlackInkPercent = black;
                    if (color.HasValue) printer.ColorInkPercent = color;
                }
            }
            catch { }

            try
            {
                using var resp = await SendLanGetAsync(http, new Uri($"{scheme}://{ip}/DevMgmt/ProductStatusDyn.xml"), targetIp);
                if (resp.IsSuccessStatusCode)
                {
                    string xml = await ReadBoundedResponseStringAsync(resp, MaxControlPayloadBytes);
                    string state = ParseHpStatusXml(xml);
                    if (!string.IsNullOrWhiteSpace(state))
                        printer.LiveDeviceState = state;
                }
            }
            catch { }

            try
            {
                using var esclResp = await SendLanGetAsync(http, new Uri($"{scheme}://{ip}/eSCL/ScannerStatus"), targetIp);
                if (esclResp.IsSuccessStatusCode)
                {
                    _ = await ReadBoundedResponseBytesAsync(esclResp, MaxControlPayloadBytes);
                    printer.LanEsclOnline = true;
                }
            }
            catch { }
        }

        public static (int? BlackPercent, int? ColorPercent) ParseHpConsumableXml(string xml)
        {
            if (string.IsNullOrWhiteSpace(xml)) return (null, null);

            // Parse via XmlReader with DtdProcessing.Prohibit & MaxCharactersInDocument = 262_144
            var doc = ParseSafeXmlDocument(xml);

            int? black = null;
            var colorValues = new List<int>();

            foreach (var info in doc.Descendants().Where(e => e.Name.LocalName == "ConsumableInfo"))
            {
                string state = info.Descendants().FirstOrDefault(e => e.Name.LocalName == "ConsumableState")?.Value ?? "";
                if (string.Equals(state, "missing", StringComparison.OrdinalIgnoreCase)) continue;

                string label = info.Descendants().FirstOrDefault(e => e.Name.LocalName == "ConsumableLabelCode")?.Value?.Trim().ToUpperInvariant() ?? "";
                string pctRaw = info.Descendants().FirstOrDefault(e => e.Name.LocalName == "ConsumablePercentageLevelRemaining")?.Value?.Trim() ?? "";

                if (!int.TryParse(pctRaw, out int pct)) continue;
                pct = Math.Clamp(pct, 0, 100);

                if (label == "K" || label.Contains("BLACK"))
                {
                    black = pct;
                }
                else if (label == "CMY" || label == "C" || label == "M" || label == "Y" || label.Contains("COLOR") || label.Contains("CYAN") || label.Contains("MAGENTA") || label.Contains("YELLOW"))
                {
                    colorValues.Add(pct);
                }
            }

            int? color = colorValues.Count > 0 ? (int)Math.Round(colorValues.Average()) : null;
            return (black, color);
        }

        public static string ParseHpStatusXml(string xml)
        {
            if (string.IsNullOrWhiteSpace(xml)) return "";

            // Parse via XmlReader with DtdProcessing.Prohibit & MaxCharactersInDocument = 262_144
            var doc = ParseSafeXmlDocument(xml);
            string cat = doc.Descendants().FirstOrDefault(e => e.Name.LocalName == "StatusCategory")?.Value?.Trim().ToLowerInvariant() ?? "";
            return cat switch
            {
                "ready" => "Bereit",
                "inpowersave" or "powersave" or "sleep" => "Ruhemodus",
                "processing" or "printing" => "Druckt",
                "scanning" => "Scannt",
                "outofpaper" or "paperout" => "Papier nachlegen",
                "dooropen" => "Klappe offen",
                "jam" or "paperjam" => "Papierstau",
                _ => ""
            };
        }

        public static (int MaxWidthUnits, int MaxHeightUnits) ParseEsclScannerCapabilitiesXml(string xml)
        {
            int maxWidthUnits = 2480;  // A4 in 1/300th inch (8.27 * 300)
            int maxHeightUnits = 3508; // A4 in 1/300th inch (11.69 * 300)

            var doc = ParseSafeXmlDocument(xml);
            string? mw = doc.Descendants().FirstOrDefault(e => e.Name.LocalName == "MaxWidth")?.Value;
            string? mh = doc.Descendants().FirstOrDefault(e => e.Name.LocalName == "MaxHeight")?.Value;
            if (int.TryParse(mw, out int parsedW) && parsedW >= 1200)
                maxWidthUnits = Math.Min(2480, parsedW);
            if (int.TryParse(mh, out int parsedH) && parsedH >= 1500)
                maxHeightUnits = Math.Min(3508, parsedH);

            return (maxWidthUnits, maxHeightUnits);
        }

        private static async Task QueryIppAttributesFallbackAsync(DiscoveredPrinterInfo printer, IPAddress targetIp)
        {
            if (!IsPrivateLanIp(targetIp)) return;

            try
            {
                string ip = targetIp.ToString();
                using var http = CreateLocalLanHttpClient(TimeSpan.FromMilliseconds(2000), targetIp);
                string printerUri = $"ipp://{ip}:631/ipp/print";
                byte[] reqBytes = BuildIppGetAttributesPayload(printerUri);
                using var content = new ByteArrayContent(reqBytes);
                content.Headers.Remove("Content-Type");
                content.Headers.TryAddWithoutValidation("Content-Type", "application/ipp");

                using var resp = await http.PostAsync($"http://{ip}:631/ipp/print", content);
                if (!resp.IsSuccessStatusCode) return;

                byte[] respBytes = await ReadBoundedResponseBytesAsync(resp, MaxControlPayloadBytes);
                var levels = ParseIppMarkerLevels(respBytes);
                if (levels.Count >= 1 && printer.BlackInkPercent == null)
                    printer.BlackInkPercent = Math.Clamp(levels[0], 0, 100);
                if (levels.Count >= 2 && printer.ColorInkPercent == null)
                    printer.ColorInkPercent = Math.Clamp((int)Math.Round(levels.Skip(1).Where(v => v >= 0).DefaultIfEmpty(levels[1]).Average()), 0, 100);
            }
            catch { }
        }

        private static byte[] BuildIppGetAttributesPayload(string printerUri)
        {
            using var ms = new MemoryStream();
            using var bw = new BinaryWriter(ms, Encoding.UTF8);
            bw.Write((byte)0x02); // IPP 2.0
            bw.Write((byte)0x00);
            bw.Write((byte)0x00); // Operation 0x000B = Get-Printer-Attributes
            bw.Write((byte)0x0B);
            bw.Write(new byte[] { 0x00, 0x00, 0x00, 0x01 }); // request-id = 1
            bw.Write((byte)0x01); // operation-attributes-tag

            DocumentAndPrintEngine.WriteIppStr(bw, 0x47, "attributes-charset", "utf-8");
            DocumentAndPrintEngine.WriteIppStr(bw, 0x48, "attributes-natural-language", "de");
            DocumentAndPrintEngine.WriteIppStr(bw, 0x45, "printer-uri", printerUri);
            DocumentAndPrintEngine.WriteIppStr(bw, 0x44, "requested-attributes", "marker-levels");
            DocumentAndPrintEngine.WriteIppStr(bw, 0x44, "", "printer-state");

            bw.Write((byte)0x03); // end-of-attributes-tag
            bw.Flush();
            return ms.ToArray();
        }

        private static List<int> ParseIppMarkerLevels(byte[] data)
        {
            var result = new List<int>();
            byte[] needle = Encoding.ASCII.GetBytes("marker-levels");
            for (int i = 8; i + needle.Length + 6 < data.Length; i++)
            {
                bool match = true;
                for (int k = 0; k < needle.Length; k++)
                {
                    if (data[i + k] != needle[k]) { match = false; break; }
                }
                if (!match) continue;

                int pos = i + needle.Length;
                if (pos + 6 <= data.Length)
                {
                    int vLen = (data[pos] << 8) | data[pos + 1];
                    pos += 2;
                    if (vLen == 4 && pos + 4 <= data.Length)
                    {
                        int val = (data[pos] << 24) | (data[pos + 1] << 16) | (data[pos + 2] << 8) | data[pos + 3];
                        if (val >= 0 && val <= 100) result.Add(val);
                        pos += 4;
                        while (pos + 9 <= data.Length && data[pos] == 0x21 && data[pos + 1] == 0 && data[pos + 2] == 0)
                        {
                            int l2 = (data[pos + 3] << 8) | data[pos + 4];
                            if (l2 != 4) break;
                            int v2 = (data[pos + 5] << 24) | (data[pos + 6] << 16) | (data[pos + 7] << 8) | data[pos + 8];
                            if (v2 >= 0 && v2 <= 100) result.Add(v2);
                            pos += 9;
                        }
                    }
                }
                break;
            }
            return result;
        }

        // =====================================================================
        // DRUCK-WARTESCHLANGE VERWALTEN & WSD-HÄNGER LÖSEN
        // =====================================================================
        public static Task<List<PrintQueueJobItem>> GetQueueJobsAsync(string queueName)
        {
            return Task.Run(() =>
            {
                var items = new List<PrintQueueJobItem>();
                if (string.IsNullOrWhiteSpace(queueName)) return items;
                try
                {
                    using var server = new PrintServer();
                    using var queue = server.GetPrintQueue(queueName);
                    queue.Refresh();
                    var jobs = queue.GetPrintJobInfoCollection();
                    foreach (var job in jobs)
                    {
                        try
                        {
                            string st = "In Warteschlange";
                            if ((job.JobStatus & PrintJobStatus.Printing) != 0) st = "Druckt gerade";
                            else if ((job.JobStatus & PrintJobStatus.Error) != 0) st = "Fehler";
                            else if ((job.JobStatus & PrintJobStatus.Offline) != 0) st = "Wartet auf Drucker";
                            else if ((job.JobStatus & PrintJobStatus.Paused) != 0) st = "Pausiert";

                            items.Add(new PrintQueueJobItem
                            {
                                JobId = job.JobIdentifier,
                                DocumentName = string.IsNullOrWhiteSpace(job.Name) ? $"Auftrag #{job.JobIdentifier}" : job.Name,
                                StatusText = st,
                                PagesText = job.NumberOfPages > 0 ? $"{job.NumberOfPages} S." : "1+ S.",
                                SubmittedText = job.TimeJobSubmitted.ToLocalTime().ToString("HH:mm:ss")
                            });
                        }
                        catch { }
                    }
                }
                catch { }
                return items;
            });
        }

        public static Task<bool> CancelSingleJobAsync(string queueName, int jobId)
        {
            return Task.Run(() =>
            {
                try
                {
                    using var server = new PrintServer();
                    using var queue = server.GetPrintQueue(queueName);
                    queue.Refresh();
                    foreach (var job in queue.GetPrintJobInfoCollection())
                    {
                        if (job.JobIdentifier == jobId)
                        {
                            job.Cancel();
                            return true;
                        }
                    }
                }
                catch { }
                return false;
            });
        }

        public static Task<int> PurgeAllQueueJobsAsync(string queueName)
        {
            return Task.Run(() =>
            {
                int count = 0;
                try
                {
                    using var server = new PrintServer();
                    using var queue = server.GetPrintQueue(queueName);
                    queue.Refresh();
                    try
                    {
                        foreach (var job in queue.GetPrintJobInfoCollection())
                        {
                            try { job.Cancel(); count++; } catch { }
                        }
                    }
                    catch { }
                    try { queue.Purge(); } catch { }
                }
                catch { }
                return count;
            });
        }

        // =====================================================================
        // DUAL-STACK ABOFREIER SCANNER (eSCL AirScan LAN + Windows WIA 2.0)
        // =====================================================================
        public static async Task<(List<BitmapSource> Pages, string UsedEngine)> ScanPagesAsync(
            DiscoveredPrinterInfo? printer,
            ScanSourceMode source,
            int dpi,
            ScanColorMode colorMode,
            ScanProtocolMode protocol,
            Action<string>? onProgress = null,
            CancellationToken ct = default)
        {
            ct.ThrowIfCancellationRequested();

            string? ip = printer?.ResolvedIpAddress;
            if (string.IsNullOrWhiteSpace(ip) && !string.IsNullOrWhiteSpace(printer?.ResolvedHostOrIp))
            {
                var resolved = await ResolvePrivateLanIpAsync(printer.ResolvedHostOrIp);
                ip = resolved?.ToString();
            }

            Exception? esclError = null;

            // 1. Try Driverless LAN eSCL / AirScan first (100% independent of HP Smart & Windows WSD!)
            if (protocol != ScanProtocolMode.WiaLocal &&
                !string.IsNullOrWhiteSpace(ip) &&
                IPAddress.TryParse(ip, out var parsedLanIp) &&
                IsPrivateLanIp(parsedLanIp))
            {
                try
                {
                    onProgress?.Invoke($"Verbinde direkt per LAN (eSCL) mit {parsedLanIp} …");
                    var esclPages = await ScanViaEsclAsync(parsedLanIp, source, dpi, colorMode, onProgress, ct);
                    if (esclPages.Count > 0)
                        return (esclPages, $"LAN Direkt (eSCL · {parsedLanIp})");
                }
                catch (OperationCanceledException)
                {
                    throw;
                }
                catch (Exception ex)
                {
                    esclError = ex;
                    if (protocol == ScanProtocolMode.EsclLan)
                        throw new InvalidOperationException($"LAN-Scanner ({parsedLanIp}) antwortet nicht: {ex.Message}");
                }
            }

            ct.ThrowIfCancellationRequested();

            // 2. Fallback or explicit Windows WIA 2.0 (USB / WSD)
            if (protocol != ScanProtocolMode.EsclLan)
            {
                try
                {
                    onProgress?.Invoke("Starte lokalen Windows WIA-Scan …");
                    var wiaPages = await ScanViaWiaAsync(printer?.QueueName, source, dpi, colorMode, onProgress, ct);
                    if (wiaPages.Count > 0)
                        return (wiaPages, "Windows WIA Direkt");
                }
                catch (OperationCanceledException)
                {
                    throw;
                }
                catch (Exception wiaEx)
                {
                    if (esclError != null)
                    {
                        throw new InvalidOperationException(
                            $"Scanner ist aktuell ausgeschaltet oder im Tiefschlaf ({ip ?? "kein LAN"} / WIA). Bitte Drucker kurz einschalten und erneut klicken. ({wiaEx.Message})");
                    }
                    throw;
                }
            }

            throw new InvalidOperationException("Kein aktives Scanner-Gerät gefunden. Bitte prüfen, ob der Drucker eingeschaltet ist.");
        }

        internal static Uri ValidateEsclScanJobLocation(
            Uri baseEsclUri,
            IPAddress targetScannerIp,
            Uri? locationHeader,
            bool allowLoopbackForSelfTest = false)
        {
            if (!IsAllowedTargetIp(targetScannerIp, allowLoopbackForSelfTest))
                throw new InvalidOperationException("Ziel-Scanner-IP ist keine zulässige private Heimnetz-Adresse.");

            if (locationHeader == null)
                throw new InvalidOperationException("Scanner hat keine ScanJob-Adresse zurückgegeben.");

            string raw = locationHeader.OriginalString ?? "";
            if (string.IsNullOrWhiteSpace(raw) ||
                raw.Any(char.IsControl) ||
                raw.Contains("..", StringComparison.Ordinal) ||
                raw.Contains('\\') ||
                raw.Contains('%') ||
                raw.Contains('?') ||
                raw.Contains('#') ||
                (!locationHeader.IsAbsoluteUri && raw.StartsWith("//", StringComparison.Ordinal)))
            {
                throw new InvalidOperationException("Scanner hat eine unsichere ScanJob-Location zurückgegeben.");
            }

            string scheme = baseEsclUri.Scheme;
            int port = baseEsclUri.Port;
            string cleanPath;

            if (locationHeader.IsAbsoluteUri)
            {
                if (!string.Equals(locationHeader.Scheme, "http", StringComparison.OrdinalIgnoreCase) &&
                    !string.Equals(locationHeader.Scheme, "https", StringComparison.OrdinalIgnoreCase))
                {
                    throw new InvalidOperationException("Ungültiges Protokoll in ScanJob-Location.");
                }

                if (!string.IsNullOrEmpty(locationHeader.UserInfo) ||
                    !string.IsNullOrEmpty(locationHeader.Query) ||
                    !string.IsNullOrEmpty(locationHeader.Fragment))
                {
                    throw new InvalidOperationException("Unzulässige Query- oder Fragment-Anteile in ScanJob-Location.");
                }

                if (!IPAddress.TryParse(locationHeader.Host, out var locIp) ||
                    !locIp.Equals(targetScannerIp) ||
                    !IsAllowedTargetIp(locIp, allowLoopbackForSelfTest))
                {
                    throw new InvalidOperationException("ScanJob-Location verweist auf einen abweichenden Host.");
                }

                scheme = locationHeader.Scheme.ToLowerInvariant();
                port = locationHeader.Port;
                cleanPath = locationHeader.AbsolutePath.TrimEnd('/');
            }
            else
            {
                string rel = raw.StartsWith('/') ? raw : "/" + raw;
                cleanPath = rel.TrimEnd('/');
            }

            if (!SafeEsclJobPathRegex.IsMatch(cleanPath))
                throw new InvalidOperationException("Ungültiger eSCL-ScanJob-Pfad blockiert.");

            bool isDefaultPort = (scheme == "http" && port == 80) || (scheme == "https" && port == 443);
            string portPart = isDefaultPort ? "" : $":{port}";
            return new Uri($"{scheme}://{targetScannerIp}{portPart}{cleanPath}");
        }

        private static Task<List<BitmapSource>> ScanViaEsclAsync(
            IPAddress targetIp,
            ScanSourceMode source,
            int dpi,
            ScanColorMode colorMode,
            Action<string>? onProgress,
            CancellationToken ct)
        {
            if (!IsPrivateLanIp(targetIp))
                throw new InvalidOperationException("LAN-Scan ist nur im privaten Heimnetz zulässig.");

            return ScanViaEsclCoreAsync(
                targetIp,
                explicitBaseEsclUri: null,
                source,
                dpi,
                colorMode,
                onProgress,
                allowLoopbackForSelfTest: false,
                ct);
        }

        internal static Task<List<BitmapSource>> ScanViaEsclForSelfTestAsync(
            Uri mockBaseEsclUri,
            ScanSourceMode source,
            int dpi,
            ScanColorMode colorMode,
            CancellationToken ct = default)
        {
            if (!IPAddress.TryParse(mockBaseEsclUri.Host, out var ip) || !IsAllowedTargetIp(ip, allowLoopbackForSelfTest: true))
                throw new InvalidOperationException("Ungültige Test-Adresse.");

            return ScanViaEsclCoreAsync(
                ip,
                explicitBaseEsclUri: mockBaseEsclUri,
                source,
                dpi,
                colorMode,
                onProgress: null,
                allowLoopbackForSelfTest: true,
                ct);
        }

        private static async Task<List<BitmapSource>> ScanViaEsclCoreAsync(
            IPAddress targetIp,
            Uri? explicitBaseEsclUri,
            ScanSourceMode source,
            int dpi,
            ScanColorMode colorMode,
            Action<string>? onProgress,
            bool allowLoopbackForSelfTest,
            CancellationToken ct)
        {
            ct.ThrowIfCancellationRequested();

            using var http = CreateLocalLanHttpClient(TimeSpan.FromSeconds(75), targetIp, allowLoopbackForSelfTest);

            Uri baseEsclUri = explicitBaseEsclUri ?? new Uri($"http://{targetIp}/eSCL");
            HttpResponseMessage? capResp = null;

            try
            {
                capResp = await SendLanGetAsync(http, new Uri($"{baseEsclUri.AbsoluteUri.TrimEnd('/')}/ScannerCapabilities"), targetIp, allowLoopbackForSelfTest, ct);
            }
            catch (OperationCanceledException)
            {
                throw;
            }
            catch
            {
                if (explicitBaseEsclUri != null) throw;
            }

            if ((capResp == null || !capResp.IsSuccessStatusCode) && explicitBaseEsclUri == null)
            {
                capResp?.Dispose();
                baseEsclUri = new Uri($"https://{targetIp}/eSCL");
                capResp = await SendLanGetAsync(http, new Uri($"{baseEsclUri.AbsoluteUri.TrimEnd('/')}/ScannerCapabilities"), targetIp, allowLoopbackForSelfTest, ct);
            }

            if (capResp == null)
                throw new InvalidOperationException("Scanner hat keine Fähigkeiten zurückgegeben.");

            string capXml;
            using (capResp)
            {
                capResp.EnsureSuccessStatusCode();
                if (capResp.RequestMessage?.RequestUri != null &&
                    string.Equals(capResp.RequestMessage.RequestUri.Scheme, "https", StringComparison.OrdinalIgnoreCase) &&
                    explicitBaseEsclUri == null)
                {
                    baseEsclUri = new Uri($"https://{targetIp}/eSCL");
                }
                capXml = await ReadBoundedResponseStringAsync(capResp, MaxControlPayloadBytes, ct);
            }

            var (maxWidthUnits, maxHeightUnits) = ParseEsclScannerCapabilitiesXml(capXml);

            string inputSource = source == ScanSourceMode.Flatbed ? "Platen" : "Feeder";
            string duplexXml = source == ScanSourceMode.AdfDuplex ? "<scan:Duplex>true</scan:Duplex>" : "<scan:Duplex>false</scan:Duplex>";
            string esclColor = colorMode == ScanColorMode.Color ? "RGB24" : "Grayscale8";
            int clampedDpi = (dpi == 150 || dpi == 300 || dpi == 600) ? dpi : 300;

            string scanSettingsXml = $"""
                <?xml version="1.0" encoding="UTF-8"?>
                <scan:ScanSettings xmlns:pwg="http://www.pwg.org/schemas/2010/12/sm" xmlns:scan="http://schemas.hp.com/imaging/escl/2011/05/03">
                  <pwg:Version>2.0</pwg:Version>
                  <pwg:ScanRegions>
                    <pwg:ScanRegion>
                      <pwg:ContentRegionUnits>escl:ThreeHundredthsOfInches</pwg:ContentRegionUnits>
                      <pwg:XOffset>0</pwg:XOffset>
                      <pwg:YOffset>0</pwg:YOffset>
                      <pwg:Width>{maxWidthUnits}</pwg:Width>
                      <pwg:Height>{maxHeightUnits}</pwg:Height>
                    </pwg:ScanRegion>
                  </pwg:ScanRegions>
                  <pwg:InputSource>{inputSource}</pwg:InputSource>
                  {duplexXml}
                  <scan:ColorMode>{esclColor}</scan:ColorMode>
                  <pwg:DocumentFormat>image/jpeg</pwg:DocumentFormat>
                  <scan:XResolution>{clampedDpi}</scan:XResolution>
                  <scan:YResolution>{clampedDpi}</scan:YResolution>
                </scan:ScanSettings>
                """;

            Uri validatedJobUri;
            using (var postContent = new StringContent(scanSettingsXml, Encoding.UTF8, "text/xml"))
            using (var jobResp = await http.PostAsync($"{baseEsclUri.AbsoluteUri.TrimEnd('/')}/ScanJobs", postContent, ct))
            {
                if (!jobResp.IsSuccessStatusCode)
                {
                    throw new InvalidOperationException($"Scanner meldet Status {(int)jobResp.StatusCode} ({(source == ScanSourceMode.Flatbed ? "Flachbett" : "Dokumenteneinzug prüfen")}).");
                }

                // Strictly validate Location BEFORE entering try/finally so invalid/traversal paths send ZERO GET and ZERO DELETE!
                validatedJobUri = ValidateEsclScanJobLocation(baseEsclUri, targetIp, jobResp.Headers.Location, allowLoopbackForSelfTest);
            }

            string jobUrl = validatedJobUri.AbsoluteUri.TrimEnd('/');
            var pages = new List<BitmapSource>();
            try
            {
                int pageNum = 1;
                while (true)
                {
                    ct.ThrowIfCancellationRequested();
                    onProgress?.Invoke($"Scanne Seite {pageNum} ({clampedDpi} DPI) …");

                    HttpResponseMessage? nextResp = null;
                    for (int retry = 0; retry < 20; retry++)
                    {
                        ct.ThrowIfCancellationRequested();
                        nextResp = await http.GetAsync($"{jobUrl}/NextDocument", HttpCompletionOption.ResponseHeadersRead, ct);
                        if (nextResp.StatusCode == HttpStatusCode.ServiceUnavailable)
                        {
                            nextResp.Dispose();
                            nextResp = null;
                            await Task.Delay(550, ct);
                            continue;
                        }
                        break;
                    }

                    if (nextResp == null) break;
                    using (nextResp)
                    {
                        if (nextResp.StatusCode == HttpStatusCode.NotFound)
                        {
                            break;
                        }
                        if (!nextResp.IsSuccessStatusCode)
                        {
                            if (pages.Count > 0) break;
                            throw new InvalidOperationException($"Fehler beim Abrufen des Scans (HTTP {(int)nextResp.StatusCode}).");
                        }

                        byte[] imgBytes = await ReadBoundedResponseBytesAsync(nextResp, MaxScanImagePayloadBytes, ct);
                        if (imgBytes.Length > 128)
                        {
                            var bmp = DecodeBytesToFrozenBitmap(imgBytes);
                            pages.Add(bmp);
                            pageNum++;
                        }
                        else
                        {
                            break;
                        }

                        if (source == ScanSourceMode.Flatbed)
                            break;
                    }
                }
            }
            finally
            {
                try { await http.DeleteAsync(jobUrl, CancellationToken.None); } catch { }
            }

            return pages;
        }

        public static int SelectPreferredWiaDeviceIndex(IReadOnlyList<string> scannerDeviceNames, string? preferredName)
        {
            if (scannerDeviceNames == null || scannerDeviceNames.Count == 0)
                return -1;

            if (!string.IsNullOrWhiteSpace(preferredName))
            {
                string pref = preferredName.Trim();

                // 1. Exact or direct substring match in either direction
                for (int i = 0; i < scannerDeviceNames.Count; i++)
                {
                    string dev = scannerDeviceNames[i] ?? "";
                    if (string.IsNullOrWhiteSpace(dev)) continue;
                    if (dev.Contains(pref, StringComparison.OrdinalIgnoreCase) ||
                        pref.Contains(dev, StringComparison.OrdinalIgnoreCase))
                    {
                        return i;
                    }
                }

                // 2. Match distinctive tokens (>= 3 chars) from preferredName (e.g. "Brother", "5200", "Epson")
                var tokens = Regex.Split(pref, @"[^A-Za-z0-9]+")
                    .Where(t => t.Length >= 3 && !string.Equals(t, "series", StringComparison.OrdinalIgnoreCase))
                    .ToArray();

                if (tokens.Length > 0)
                {
                    int bestIdx = -1;
                    int bestHits = 0;
                    for (int i = 0; i < scannerDeviceNames.Count; i++)
                    {
                        string dev = scannerDeviceNames[i] ?? "";
                        int hits = tokens.Count(tok => dev.Contains(tok, StringComparison.OrdinalIgnoreCase));
                        if (hits > bestHits)
                        {
                            bestHits = hits;
                            bestIdx = i;
                        }
                    }
                    if (bestIdx >= 0)
                        return bestIdx;
                }
            }

            return 0;
        }

        private static Task<List<BitmapSource>> ScanViaWiaAsync(
            string? preferredName,
            ScanSourceMode source,
            int dpi,
            ScanColorMode colorMode,
            Action<string>? onProgress,
            CancellationToken ct)
        {
            ct.ThrowIfCancellationRequested();
            var tcs = new TaskCompletionSource<List<BitmapSource>>(TaskCreationOptions.RunContinuationsAsynchronously);

            var staThread = new Thread(() =>
            {
                try
                {
                    ct.ThrowIfCancellationRequested();

                    Type? dmType = Type.GetTypeFromProgID("WIA.DeviceManager");
                    if (dmType == null)
                        throw new InvalidOperationException("Windows WIA-Dienst ist nicht verfügbar.");

                    dynamic manager = Activator.CreateInstance(dmType)!;
                    dynamic infos = manager.DeviceInfos;
                    int count = (int)infos.Count;

                    var candidateInfos = new List<dynamic>();
                    var candidateNames = new List<string>();

                    for (int i = 1; i <= count; i++)
                    {
                        dynamic info = infos[i];
                        int devType = (int)info.Type; // 1 = ScannerDeviceType
                        if (devType != 1) continue;

                        string devName = "";
                        try { devName = info.Properties["Name"].Value?.ToString() ?? ""; } catch { }

                        candidateInfos.Add(info);
                        candidateNames.Add(devName);
                    }

                    int chosenIdx = SelectPreferredWiaDeviceIndex(candidateNames, preferredName);
                    if (chosenIdx < 0 || chosenIdx >= candidateInfos.Count)
                        throw new InvalidOperationException("Kein lokaler WIA-Scanner gefunden (Gerät ausgeschaltet oder nicht verbunden).");

                    dynamic chosenInfo = candidateInfos[chosenIdx];
                    dynamic device = chosenInfo.Connect();
                    var scanned = new List<BitmapSource>();

                    if (source != ScanSourceMode.Flatbed)
                    {
                        int handlingVal = source == ScanSourceMode.AdfDuplex ? (1 | 4) : 1;
                        TrySetWiaProperty(device.Properties, 3088, handlingVal);
                        TrySetWiaProperty(device.Properties, 3096, 1);
                    }
                    else
                    {
                        TrySetWiaProperty(device.Properties, 3088, 2);
                    }

                    int targetDpi = (dpi == 150 || dpi == 300 || dpi == 600) ? dpi : 300;
                    int maxPages = source == ScanSourceMode.Flatbed ? 1 : 50;

                    for (int page = 1; page <= maxPages; page++)
                    {
                        ct.ThrowIfCancellationRequested();
                        onProgress?.Invoke($"WIA-Scan Seite {page} ({targetDpi} DPI) …");

                        dynamic item = device.Items[1];
                        int wiaColor = colorMode == ScanColorMode.Color ? 1 : (colorMode == ScanColorMode.ReceiptBw ? 4 : 2);
                        TrySetWiaProperty(item.Properties, 6146, wiaColor);  // WIA_IPA_DATATYPE
                        TrySetWiaProperty(item.Properties, 6147, targetDpi); // Horizontal Resolution
                        TrySetWiaProperty(item.Properties, 6148, targetDpi); // Vertical Resolution
                        TrySetWiaProperty(item.Properties, 6149, 0);         // Horizontal Start Position
                        TrySetWiaProperty(item.Properties, 6150, 0);         // Vertical Start Position

                        // Update pixel extents (6151 = Horizontal Extent, 6152 = Vertical Extent) for new DPI (full A4 bed)
                        int desiredExtentX = (int)Math.Round(8.27 * targetDpi);
                        int desiredExtentY = (int)Math.Round(11.69 * targetDpi);
                        TrySetWiaClampedExtent(item.Properties, 6151, desiredExtentX);
                        TrySetWiaClampedExtent(item.Properties, 6152, desiredExtentY);

                        try
                        {
                            dynamic imgFile = item.Transfer("{B96B3CAF-0728-11D3-9D7B-0000F81EF32E}");
                            ct.ThrowIfCancellationRequested();

                            dynamic vec = imgFile.FileData;
                            byte[] bytes = (byte[])vec.BinaryData;
                            if (bytes != null && bytes.Length > 64 && bytes.Length <= MaxScanImagePayloadBytes)
                            {
                                scanned.Add(DecodeBytesToFrozenBitmap(bytes));
                            }
                        }
                        catch (OperationCanceledException)
                        {
                            throw;
                        }
                        catch
                        {
                            if (scanned.Count > 0) break;
                            throw;
                        }
                    }

                    tcs.TrySetResult(scanned);
                }
                catch (OperationCanceledException oce)
                {
                    tcs.TrySetCanceled(oce.CancellationToken.CanBeCanceled ? oce.CancellationToken : ct);
                }
                catch (Exception ex)
                {
                    tcs.TrySetException(ex);
                }
            });

            staThread.IsBackground = true;
            staThread.SetApartmentState(ApartmentState.STA);
            staThread.Start();

            return tcs.Task;
        }

        private static void TrySetWiaProperty(dynamic properties, int propId, object value)
        {
            try
            {
                int count = (int)properties.Count;
                for (int i = 1; i <= count; i++)
                {
                    dynamic p = properties[i];
                    if ((int)p.PropertyID == propId)
                    {
                        p.Value = value;
                        return;
                    }
                }
            }
            catch { }
        }

        private static void TrySetWiaClampedExtent(dynamic properties, int propId, int desiredPixels)
        {
            try
            {
                int count = (int)properties.Count;
                for (int i = 1; i <= count; i++)
                {
                    dynamic p = properties[i];
                    if ((int)p.PropertyID == propId)
                    {
                        int val = desiredPixels;
                        try
                        {
                            // SubType == 1 corresponds to WiaSubType.RangeSubType
                            if ((int)p.SubType == 1)
                            {
                                int min = (int)p.SubTypeMin;
                                int max = (int)p.SubTypeMax;
                                if (max >= min && max > 0)
                                    val = Math.Clamp(desiredPixels, min, max);
                            }
                        }
                        catch { }

                        p.Value = val;
                        return;
                    }
                }
            }
            catch { }
        }

        public static BitmapSource DecodeBytesToFrozenBitmap(byte[] bytes)
        {
            using var ms = new MemoryStream(bytes);
            var bi = new BitmapImage();
            bi.BeginInit();
            bi.CacheOption = BitmapCacheOption.OnLoad;
            bi.StreamSource = ms;
            bi.EndInit();
            bi.Freeze();
            return bi;
        }

        public static bool IsHpSmartStoreAppInstalled()
        {
            try
            {
                string pkgDir = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Packages",
                    "AD2F1837.HPPrinterControl_v10z8vjag6ke6");
                return Directory.Exists(pkgDir);
            }
            catch
            {
                return false;
            }
        }
    }
}

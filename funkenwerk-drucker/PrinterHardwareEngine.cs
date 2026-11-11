using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Sockets;
using System.Printing;
using System.Reflection;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows.Media.Imaging;
using System.Xml.Linq;
using Microsoft.Win32;

namespace FunkenwerkDrucker
{
    public static class PrinterHardwareEngine
    {
        private static readonly Regex HostTokenRegex = new(@"\b([A-Za-z]{2}[0-9A-Fa-f]{6}|NPI[0-9A-Fa-f]{6}|BRW[0-9A-Fa-f]{6,12}|EPSON[0-9A-Fa-f]{6})\b", RegexOptions.Compiled);
        private static readonly Regex SafeIpOrHostRegex = new(@"^[A-Za-z0-9.\-]+$", RegexOptions.Compiled);

        // Strictly allow direct printer HTTP/IPP/eSCL calls ONLY to private LAN IPv4 addresses (RFC1918 / Link-Local)
        public static bool IsPrivateLanIp(IPAddress ip)
        {
            if (ip.AddressFamily != AddressFamily.InterNetwork) return false;
            byte[] b = ip.GetAddressBytes();
            if (b[0] == 10) return true;
            if (b[0] == 172 && b[1] >= 16 && b[1] <= 31) return true;
            if (b[0] == 192 && b[1] == 168) return true;
            if (b[0] == 169 && b[1] == 254) return true;
            if (IPAddress.IsLoopback(ip)) return true;
            return false;
        }

        private static HttpClient CreateLocalLanHttpClient(TimeSpan timeout)
        {
            var handler = new SocketsHttpHandler
            {
                AllowAutoRedirect = true,
                MaxAutomaticRedirections = 3,
                UseProxy = false,
                ConnectTimeout = TimeSpan.FromMilliseconds(1400),
                SslOptions =
                {
                    // Local printers (HP OfficeJet / Brother / Epson) use self-signed device certs for HTTPS eSCL on LAN
                    RemoteCertificateValidationCallback = (sender, cert, chain, errors) => true
                }
            };
            return new HttpClient(handler) { Timeout = timeout };
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

            // Probe network telemetry in parallel for real printers
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
            // Keep clean readable name like "HP OfficeJet 5200 (HPAA51AF)" if formatted "HPAA51AF (HP OfficeJet 5200 series)"
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
            if (!string.IsNullOrWhiteSpace(customIpOverride) && SafeIpOrHostRegex.IsMatch(customIpOverride.Trim()))
            {
                candidateHost = customIpOverride.Trim();
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

            // Parallel fast TCP port check (80 HTTP/LEDM, 443 HTTPS/eSCL, 631 IPP, 9100 JetDirect)
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
                await QueryHpLedmAndEsclTelemetryAsync(printer, ipStr, preferHttps: !t80.Result && t443.Result);
            }

            if ((printer.BlackInkPercent == null && printer.ColorInkPercent == null) && printer.LanIppOnline)
            {
                await QueryIppAttributesFallbackAsync(printer, ipStr);
            }
        }

        private static string TryGetTcpPortHostFromRegistry(string portName)
        {
            if (string.IsNullOrWhiteSpace(portName)) return "";
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
            if (IPAddress.TryParse(hostOrIp, out var directIp))
            {
                return IsPrivateLanIp(directIp) ? directIp : null;
            }

            string[] candidates = hostOrIp.Contains('.')
                ? new[] { hostOrIp }
                : new[] { $"{hostOrIp}.fritz.box", $"{hostOrIp}.local", hostOrIp };

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

        // =====================================================================
        // ABOFREIE TINTENSTAND- & GERÄTESTATUS-ABFRAGE (HP LEDM XML + eSCL)
        // =====================================================================
        private static async Task QueryHpLedmAndEsclTelemetryAsync(DiscoveredPrinterInfo printer, string ip, bool preferHttps)
        {
            string scheme = preferHttps ? "https" : "http";
            using var http = CreateLocalLanHttpClient(TimeSpan.FromMilliseconds(2200));

            try
            {
                var resp = await http.GetAsync($"{scheme}://{ip}/DevMgmt/ConsumableConfigDyn.xml");
                if (resp.IsSuccessStatusCode)
                {
                    string xml = await resp.Content.ReadAsStringAsync();
                    var (black, color) = ParseHpConsumableXml(xml);
                    if (black.HasValue) printer.BlackInkPercent = black;
                    if (color.HasValue) printer.ColorInkPercent = color;
                }
            }
            catch { }

            try
            {
                var resp = await http.GetAsync($"{scheme}://{ip}/DevMgmt/ProductStatusDyn.xml");
                if (resp.IsSuccessStatusCode)
                {
                    string xml = await resp.Content.ReadAsStringAsync();
                    string state = ParseHpStatusXml(xml);
                    if (!string.IsNullOrWhiteSpace(state))
                        printer.LiveDeviceState = state;
                }
            }
            catch { }

            try
            {
                var esclResp = await http.GetAsync($"{scheme}://{ip}/eSCL/ScannerStatus");
                if (esclResp.IsSuccessStatusCode)
                {
                    printer.LanEsclOnline = true;
                }
            }
            catch { }
        }

        public static (int? BlackPercent, int? ColorPercent) ParseHpConsumableXml(string xml)
        {
            if (string.IsNullOrWhiteSpace(xml)) return (null, null);
            try
            {
                var doc = XDocument.Parse(xml);
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
            catch
            {
                return (null, null);
            }
        }

        public static string ParseHpStatusXml(string xml)
        {
            if (string.IsNullOrWhiteSpace(xml)) return "";
            try
            {
                var doc = XDocument.Parse(xml);
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
            catch
            {
                return "";
            }
        }

        // Generic IPP 2.0 Get-Printer-Attributes fallback (works for Brother, Epson, Canon, HP)
        private static async Task QueryIppAttributesFallbackAsync(DiscoveredPrinterInfo printer, string ip)
        {
            try
            {
                using var http = CreateLocalLanHttpClient(TimeSpan.FromMilliseconds(2000));
                string printerUri = $"ipp://{ip}:631/ipp/print";
                byte[] reqBytes = BuildIppGetAttributesPayload(printerUri);
                using var content = new ByteArrayContent(reqBytes);
                content.Headers.Remove("Content-Type");
                content.Headers.TryAddWithoutValidation("Content-Type", "application/ipp");

                var resp = await http.PostAsync($"http://{ip}:631/ipp/print", content);
                if (!resp.IsSuccessStatusCode) return;

                byte[] respBytes = await resp.Content.ReadAsByteArrayAsync();
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

            WriteIppStringAttr(bw, 0x47, "attributes-charset", "utf-8");
            WriteIppStringAttr(bw, 0x48, "attributes-natural-language", "de");
            WriteIppStringAttr(bw, 0x45, "printer-uri", printerUri);
            WriteIppStringAttr(bw, 0x44, "requested-attributes", "marker-levels");
            WriteIppStringAttr(bw, 0x44, "", "printer-state");

            bw.Write((byte)0x03); // end-of-attributes-tag
            bw.Flush();
            return ms.ToArray();
        }

        private static void WriteIppStringAttr(BinaryWriter bw, byte valueTag, string name, string val)
        {
            bw.Write(valueTag);
            byte[] nb = Encoding.UTF8.GetBytes(name);
            bw.Write((byte)((nb.Length >> 8) & 0xFF));
            bw.Write((byte)(nb.Length & 0xFF));
            bw.Write(nb);
            byte[] vb = Encoding.UTF8.GetBytes(val);
            bw.Write((byte)((vb.Length >> 8) & 0xFF));
            bw.Write((byte)(vb.Length & 0xFF));
            bw.Write(vb);
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
                        // Read additional 1setOf integer values (name-length == 0)
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
            string? ip = printer?.ResolvedIpAddress;
            if (string.IsNullOrWhiteSpace(ip) && !string.IsNullOrWhiteSpace(printer?.ResolvedHostOrIp))
            {
                var resolved = await ResolvePrivateLanIpAsync(printer.ResolvedHostOrIp);
                ip = resolved?.ToString();
            }

            Exception? esclError = null;

            // 1. Try Driverless LAN eSCL / AirScan first (100% independent of HP Smart & Windows WSD!)
            if (protocol != ScanProtocolMode.WiaLocal && !string.IsNullOrWhiteSpace(ip))
            {
                try
                {
                    onProgress?.Invoke($"Verbinde direkt per LAN (eSCL) mit {ip} …");
                    var esclPages = await ScanViaEsclAsync(ip!, source, dpi, colorMode, onProgress, ct);
                    if (esclPages.Count > 0)
                        return (esclPages, $"LAN Direkt (eSCL · {ip})");
                }
                catch (Exception ex)
                {
                    esclError = ex;
                    if (protocol == ScanProtocolMode.EsclLan)
                        throw new InvalidOperationException($"LAN-Scanner ({ip}) antwortet nicht: {ex.Message}");
                }
            }

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

        private static async Task<List<BitmapSource>> ScanViaEsclAsync(
            string ip,
            ScanSourceMode source,
            int dpi,
            ScanColorMode colorMode,
            Action<string>? onProgress,
            CancellationToken ct)
        {
            using var http = CreateLocalLanHttpClient(TimeSpan.FromSeconds(75));

            // Discover HTTP vs HTTPS eSCL endpoint & max scan region
            string baseUrl = $"http://{ip}/eSCL";
            int maxWidthUnits = 2480;  // A4 in 1/300th inch (8.27 * 300)
            int maxHeightUnits = 3508; // A4 in 1/300th inch (11.69 * 300)

            HttpResponseMessage? capResp = null;
            try
            {
                capResp = await http.GetAsync($"{baseUrl}/ScannerCapabilities", ct);
            }
            catch { }

            if (capResp == null || !capResp.IsSuccessStatusCode)
            {
                baseUrl = $"https://{ip}/eSCL";
                capResp = await http.GetAsync($"{baseUrl}/ScannerCapabilities", ct);
            }

            capResp.EnsureSuccessStatusCode();
            string capXml = await capResp.Content.ReadAsStringAsync(ct);
            try
            {
                var doc = XDocument.Parse(capXml);
                string? mw = doc.Descendants().FirstOrDefault(e => e.Name.LocalName == "MaxWidth")?.Value;
                string? mh = doc.Descendants().FirstOrDefault(e => e.Name.LocalName == "MaxHeight")?.Value;
                if (int.TryParse(mw, out int parsedW) && parsedW >= 1200)
                    maxWidthUnits = Math.Min(2480, parsedW);
                if (int.TryParse(mh, out int parsedH) && parsedH >= 1500)
                    maxHeightUnits = Math.Min(3508, parsedH);
            }
            catch { }

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

            using var postContent = new StringContent(scanSettingsXml, Encoding.UTF8, "text/xml");
            var jobResp = await http.PostAsync($"{baseUrl}/ScanJobs", postContent, ct);
            if (!jobResp.IsSuccessStatusCode)
            {
                throw new InvalidOperationException($"Scanner meldet Status {(int)jobResp.StatusCode} ({ (source == ScanSourceMode.Flatbed ? "Flachbett" : "Dokumenteneinzug prüfen") }).");
            }

            Uri? jobUri = jobResp.Headers.Location;
            if (jobUri == null)
                throw new InvalidOperationException("Scanner hat keine ScanJob-Adresse zurückgegeben.");

            string jobUrl = jobUri.IsAbsoluteUri
                ? $"{ (baseUrl.StartsWith("https") ? "https" : "http") }://{ip}{jobUri.PathAndQuery}"
                : $"{ (baseUrl.StartsWith("https") ? "https" : "http") }://{ip}{(jobUri.OriginalString.StartsWith("/") ? "" : "/")}{jobUri.OriginalString}";

            var pages = new List<BitmapSource>();
            try
            {
                int pageNum = 1;
                while (!ct.IsCancellationRequested)
                {
                    onProgress?.Invoke($"Scanne Seite {pageNum} ({clampedDpi} DPI) …");

                    HttpResponseMessage? nextResp = null;
                    for (int retry = 0; retry < 20; retry++)
                    {
                        nextResp = await http.GetAsync($"{jobUrl.TrimEnd('/')}/NextDocument", ct);
                        if (nextResp.StatusCode == HttpStatusCode.ServiceUnavailable)
                        {
                            nextResp.Dispose();
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
                            // Normal eSCL end-of-job signal after single platen scan or last ADF sheet
                            break;
                        }
                        if (!nextResp.IsSuccessStatusCode)
                        {
                            if (pages.Count > 0) break;
                            throw new InvalidOperationException($"Fehler beim Abrufen des Scans (HTTP {(int)nextResp.StatusCode}).");
                        }

                        byte[] imgBytes = await nextResp.Content.ReadAsByteArrayAsync(ct);
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

                        // Platen only scans 1 sheet per ScanJob
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

        private static Task<List<BitmapSource>> ScanViaWiaAsync(
            string? preferredName,
            ScanSourceMode source,
            int dpi,
            ScanColorMode colorMode,
            Action<string>? onProgress,
            CancellationToken ct)
        {
            var tcs = new TaskCompletionSource<List<BitmapSource>>();

            var staThread = new Thread(() =>
            {
                try
                {
                    Type? dmType = Type.GetTypeFromProgID("WIA.DeviceManager");
                    if (dmType == null)
                        throw new InvalidOperationException("Windows WIA-Dienst ist nicht verfügbar.");

                    dynamic manager = Activator.CreateInstance(dmType)!;
                    dynamic infos = manager.DeviceInfos;
                    int count = (int)infos.Count;

                    dynamic? chosenInfo = null;
                    for (int i = 1; i <= count; i++)
                    {
                        dynamic info = infos[i];
                        int devType = (int)info.Type; // 1 = ScannerDeviceType
                        if (devType != 1) continue;

                        string devName = "";
                        try { devName = info.Properties["Name"].Value?.ToString() ?? ""; } catch { }

                        if (chosenInfo == null)
                            chosenInfo = info;

                        if (!string.IsNullOrWhiteSpace(preferredName) &&
                            (devName.Contains("HP", StringComparison.OrdinalIgnoreCase) ||
                             preferredName.Contains(devName, StringComparison.OrdinalIgnoreCase)))
                        {
                            chosenInfo = info;
                            break;
                        }
                    }

                    if (chosenInfo == null)
                        throw new InvalidOperationException("Kein lokaler WIA-Scanner gefunden (Gerät ausgeschaltet oder nicht verbunden).");

                    dynamic device = chosenInfo.Connect();
                    var scanned = new List<BitmapSource>();

                    // Set Feeder / Flatbed on device level (3088 = WIA_DPS_DOCUMENT_HANDLING_SELECT: 1=Feeder, 2=Flatbed, 4=Duplex)
                    if (source != ScanSourceMode.Flatbed)
                    {
                        int handlingVal = source == ScanSourceMode.AdfDuplex ? (1 | 4) : 1;
                        TrySetWiaProperty(device.Properties, 3088, handlingVal);
                        TrySetWiaProperty(device.Properties, 3096, 1); // Pages = 1 per Transfer iteration
                    }
                    else
                    {
                        TrySetWiaProperty(device.Properties, 3088, 2);
                    }

                    int maxPages = source == ScanSourceMode.Flatbed ? 1 : 50;
                    for (int page = 1; page <= maxPages; page++)
                    {
                        if (ct.IsCancellationRequested) break;
                        onProgress?.Invoke($"WIA-Scan Seite {page} ({dpi} DPI) …");

                        dynamic item = device.Items[1];
                        int wiaColor = colorMode == ScanColorMode.Color ? 1 : (colorMode == ScanColorMode.ReceiptBw ? 4 : 2);
                        TrySetWiaProperty(item.Properties, 6146, wiaColor); // WIA_IPA_DATATYPE / Current Intent
                        TrySetWiaProperty(item.Properties, 6147, dpi);      // Horizontal Resolution
                        TrySetWiaProperty(item.Properties, 6148, dpi);      // Vertical Resolution

                        try
                        {
                            // "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}" = JPEG, "{B96B3CAF-...}" = PNG, "{B96B3CAB-...}" = BMP
                            dynamic imgFile = item.Transfer("{B96B3CAF-0728-11D3-9D7B-0000F81EF32E}");
                            dynamic vec = imgFile.FileData;
                            byte[] bytes = (byte[])vec.BinaryData;
                            if (bytes != null && bytes.Length > 64)
                            {
                                scanned.Add(DecodeBytesToFrozenBitmap(bytes));
                            }
                        }
                        catch
                        {
                            // Paper empty in ADF after >=1 page is normal completion
                            if (scanned.Count > 0) break;
                            throw;
                        }
                    }

                    tcs.TrySetResult(scanned);
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

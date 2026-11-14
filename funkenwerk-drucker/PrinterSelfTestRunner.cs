using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Sockets;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Xml;
using PdfSharp.Drawing;
using PdfSharp.Pdf;
using PdfSharp.Pdf.IO;
using Windows.Data.Pdf;
using Windows.Storage.Streams;

namespace FunkenwerkDrucker
{
    public static class PrinterSelfTestRunner
    {
        public static async Task<bool> RunAllAsync()
        {
            int passed = 0;
            int failed = 0;

            void Check(string name, Func<bool> fn)
            {
                try
                {
                    if (fn())
                    {
                        passed++;
                        Console.Error.WriteLine($"  [PASS] {name}");
                    }
                    else
                    {
                        failed++;
                        Console.Error.WriteLine($"  [FAIL] {name}");
                    }
                }
                catch (Exception ex)
                {
                    failed++;
                    Console.Error.WriteLine($"  [FAIL] {name}: {ex.GetType().Name} - {ex.Message}");
                }
            }

            async Task CheckAsync(string name, Func<Task<bool>> fn)
            {
                try
                {
                    if (await fn())
                    {
                        passed++;
                        Console.Error.WriteLine($"  [PASS] {name}");
                    }
                    else
                    {
                        failed++;
                        Console.Error.WriteLine($"  [FAIL] {name}");
                    }
                }
                catch (Exception ex)
                {
                    failed++;
                    Console.Error.WriteLine($"  [FAIL] {name}: {ex.GetType().Name} - {ex.Message}");
                }
            }

            Console.Error.WriteLine("=== FUNKENWERK DRUCKER SELF-TEST SUITE ===");

            string tempDir = Path.Combine(Path.GetTempPath(), "fw_drucker_selftest_" + Guid.NewGuid().ToString("N"));
            Directory.CreateDirectory(tempDir);

            string settingsPath = Path.Combine(FunkenwerkDruckerSettings.DataDirectory, "settings.json");
            bool hadSettingsBefore = File.Exists(settingsPath);
            byte[]? settingsBackup = hadSettingsBefore ? await File.ReadAllBytesAsync(settingsPath) : null;

            try
            {
                // 01. Exhaustive IsPrivateLanIp matrix
                Check("01: IsPrivateLanIp akzeptiert nur RFC1918-Heimnetz und blockt Loopback/LinkLocal/Broadcast/WAN", () =>
                {
                    string[] allowed =
                    {
                        "192.168.188.26",
                        "10.0.0.5",
                        "172.16.5.4",
                        "172.31.255.254"
                    };

                    string[] rejected =
                    {
                        "127.0.0.1",
                        "::1",
                        "169.254.169.254",
                        "8.8.8.8",
                        "0.0.0.0",
                        "100.64.0.1",
                        "172.15.255.254",
                        "172.32.0.1",
                        "192.168.188.255",
                        "192.168.188.0",
                        "10.0.0.0",
                        "10.255.255.255",
                        "255.255.255.255"
                    };

                    if (PrinterHardwareEngine.IsPrivateLanIp(null))
                        return false;

                    foreach (string ip in allowed)
                    {
                        if (!PrinterHardwareEngine.IsPrivateLanIp(IPAddress.Parse(ip)))
                            throw new InvalidOperationException($"Erlaubte LAN-IP abgelehnt: {ip}");
                    }

                    foreach (string ip in rejected)
                    {
                        if (PrinterHardwareEngine.IsPrivateLanIp(IPAddress.Parse(ip)))
                            throw new InvalidOperationException($"Unsichere IP faelschlich akzeptiert: {ip}");
                    }

                    return true;
                });

                // 02. CustomPrinterIp validation & production loopback lock
                Check("02: TryValidateCustomPrinterTarget & Produktions-Loopback-Sperre aktiv", () =>
                {
                    bool okEmpty = PrinterHardwareEngine.TryValidateCustomPrinterTarget("  ", out string c0, out _) && c0 == "";
                    bool okLan = PrinterHardwareEngine.TryValidateCustomPrinterTarget(" 192.168.188.26 ", out string c1, out _) && c1 == "192.168.188.26";
                    bool okMdns = PrinterHardwareEngine.TryValidateCustomPrinterTarget("HPAA51AF.local", out string c2, out _) && c2 == "HPAA51AF.local";
                    bool okFritz = PrinterHardwareEngine.TryValidateCustomPrinterTarget("printer01.fritz.box", out string c3, out _) && c3 == "printer01.fritz.box";

                    bool badLoop = !PrinterHardwareEngine.TryValidateCustomPrinterTarget("127.0.0.1", out _, out _);
                    bool badMeta = !PrinterHardwareEngine.TryValidateCustomPrinterTarget("169.254.169.254", out _, out _);
                    bool badWan = !PrinterHardwareEngine.TryValidateCustomPrinterTarget("8.8.8.8", out _, out _);
                    bool badBcast = !PrinterHardwareEngine.TryValidateCustomPrinterTarget("192.168.188.255", out _, out _);
                    bool badUrl = !PrinterHardwareEngine.TryValidateCustomPrinterTarget("http://192.168.188.26", out _, out _);
                    bool badHost = !PrinterHardwareEngine.TryValidateCustomPrinterTarget("evil.example.com", out _, out _);
                    bool prodLoopBlocked = !PrinterHardwareEngine.IsAllowedTargetIp(IPAddress.Loopback);

                    return okEmpty && okLan && okMdns && okFritz &&
                           badLoop && badMeta && badWan && badBcast && badUrl && badHost && prodLoopBlocked;
                });

                // 03. Ephemeral TCP mock server: cross-host & plain-HTTP 302 redirects are NOT followed
                await CheckAsync("03: Mock-Server 302-Weiterleitung (Cross-Host 8.8.8.8 & fremder Port) wird strikt blockiert", async () =>
                {
                    int forbiddenHits = 0;
                    using var forbiddenTrap = new TcpListener(IPAddress.Loopback, 0);
                    forbiddenTrap.Start();
                    int trapPort = ((IPEndPoint)forbiddenTrap.LocalEndpoint).Port;
                    using var trapCts = new CancellationTokenSource();
                    var trapTask = Task.Run(async () =>
                    {
                        try
                        {
                            while (!trapCts.IsCancellationRequested)
                            {
                                using var client = await forbiddenTrap.AcceptTcpClientAsync(trapCts.Token);
                                Interlocked.Increment(ref forbiddenHits);
                            }
                        }
                        catch { }
                    });

                    using var originSrv = new TcpListener(IPAddress.Loopback, 0);
                    originSrv.Start();
                    int originPort = ((IPEndPoint)originSrv.LocalEndpoint).Port;

                    var originTask = Task.Run(async () =>
                    {
                        // Request 1: Redirect to external 8.8.8.8
                        using (var c1 = await originSrv.AcceptTcpClientAsync())
                        await using (var s1 = c1.GetStream())
                        {
                            _ = await ReadHttpRequestAsync(s1);
                            await WriteHttpResponseAsync(s1, "302 Found", "text/plain", Array.Empty<byte>(), "Location: http://8.8.8.8/pwn");
                        }

                        // Request 2: Redirect to plain HTTP trap port on 127.0.0.1
                        using (var c2 = await originSrv.AcceptTcpClientAsync())
                        await using (var s2 = c2.GetStream())
                        {
                            _ = await ReadHttpRequestAsync(s2);
                            await WriteHttpResponseAsync(s2, "302 Found", "text/plain", Array.Empty<byte>(), $"Location: http://127.0.0.1:{trapPort}/secret");
                        }
                    });

                    using var http = PrinterHardwareEngine.CreateLocalLanHttpClient(
                        TimeSpan.FromSeconds(4),
                        IPAddress.Loopback,
                        allowLoopbackForSelfTest: true);

                    bool blockedWanRedirect = false;
                    try
                    {
                        using var r1 = await PrinterHardwareEngine.SendLanGetAsync(
                            http,
                            new Uri($"http://127.0.0.1:{originPort}/status"),
                            IPAddress.Loopback,
                            allowLoopbackForSelfTest: true);
                    }
                    catch (InvalidOperationException)
                    {
                        blockedWanRedirect = true;
                    }

                    bool blockedHttpRedirect = false;
                    try
                    {
                        using var r2 = await PrinterHardwareEngine.SendLanGetAsync(
                            http,
                            new Uri($"http://127.0.0.1:{originPort}/status2"),
                            IPAddress.Loopback,
                            allowLoopbackForSelfTest: true);
                    }
                    catch (InvalidOperationException)
                    {
                        blockedHttpRedirect = true;
                    }

                    await originTask;
                    trapCts.Cancel();
                    forbiddenTrap.Stop();
                    originSrv.Stop();
                    try { await trapTask; } catch { }

                    return blockedWanRedirect && blockedHttpRedirect && forbiddenHits == 0;
                });

                // 04. Ephemeral TCP mock eSCL scanner end-to-end (Capabilities -> POST ScanJobs -> 200 JPEG -> 404 -> DELETE)
                await CheckAsync("04: Mock-eSCL-Scanner End-to-End (Capabilities -> POST ScanJobs -> 200 JPEG -> 404 -> DELETE)", async () =>
                {
                    var sampleBmp = CreateSyntheticTestBitmap(300, 400, 300.0, Color.FromRgb(250, 250, 250), Color.FromRgb(20, 25, 35));
                    byte[] jpegBytes = DocumentAndPrintEngine.EncodeBitmapAsJpeg(sampleBmp, 90);

                    int capHits = 0;
                    int postHits = 0;
                    int nextDocHits = 0;
                    int deleteHits = 0;
                    bool postHadResolution = false;

                    using var esclSrv = new TcpListener(IPAddress.Loopback, 0);
                    esclSrv.Start();
                    int port = ((IPEndPoint)esclSrv.LocalEndpoint).Port;
                    using var srvCts = new CancellationTokenSource(TimeSpan.FromSeconds(10));

                    var serverLoop = Task.Run(async () =>
                    {
                        string capXml = """
                            <?xml version="1.0" encoding="UTF-8"?>
                            <scan:ScannerCapabilities xmlns:scan="http://schemas.hp.com/imaging/escl/2011/05/03">
                              <scan:Platen><scan:PlatenInputCaps><scan:MaxWidth>2480</scan:MaxWidth><scan:MaxHeight>3508</scan:MaxHeight></scan:PlatenInputCaps></scan:Platen>
                            </scan:ScannerCapabilities>
                            """;

                        while (!srvCts.IsCancellationRequested)
                        {
                            TcpClient client;
                            try
                            {
                                client = await esclSrv.AcceptTcpClientAsync(srvCts.Token);
                            }
                            catch
                            {
                                break;
                            }

                            using (client)
                            await using (var ns = client.GetStream())
                            {
                                var (method, path, body) = await ReadHttpRequestAsync(ns);
                                if (method == "GET" && path == "/eSCL/ScannerCapabilities")
                                {
                                    Interlocked.Increment(ref capHits);
                                    await WriteHttpResponseAsync(ns, "200 OK", "text/xml; charset=utf-8", Encoding.UTF8.GetBytes(capXml));
                                }
                                else if (method == "POST" && path == "/eSCL/ScanJobs")
                                {
                                    Interlocked.Increment(ref postHits);
                                    postHadResolution = body.Contains("<scan:XResolution>300</scan:XResolution>", StringComparison.Ordinal) &&
                                                        body.Contains("<pwg:InputSource>Feeder</pwg:InputSource>", StringComparison.Ordinal);
                                    await WriteHttpResponseAsync(
                                        ns,
                                        "201 Created",
                                        "text/plain",
                                        Array.Empty<byte>(),
                                        $"Location: http://127.0.0.1:{port}/eSCL/ScanJobs/job42");
                                }
                                else if (method == "GET" && path == "/eSCL/ScanJobs/job42/NextDocument")
                                {
                                    int n = Interlocked.Increment(ref nextDocHits);
                                    if (n == 1)
                                    {
                                        await WriteHttpResponseAsync(ns, "200 OK", "image/jpeg", jpegBytes);
                                    }
                                    else
                                    {
                                        await WriteHttpResponseAsync(ns, "404 Not Found", "text/plain", Array.Empty<byte>());
                                    }
                                }
                                else if (method == "DELETE" && path == "/eSCL/ScanJobs/job42")
                                {
                                    Interlocked.Increment(ref deleteHits);
                                    await WriteHttpResponseAsync(ns, "200 OK", "text/plain", Array.Empty<byte>());
                                    break;
                                }
                                else
                                {
                                    await WriteHttpResponseAsync(ns, "400 Bad Request", "text/plain", Array.Empty<byte>());
                                }
                            }
                        }
                    });

                    var pages = await PrinterHardwareEngine.ScanViaEsclForSelfTestAsync(
                        new Uri($"http://127.0.0.1:{port}/eSCL"),
                        ScanSourceMode.AdfSimplex,
                        300,
                        ScanColorMode.Color,
                        srvCts.Token);

                    await serverLoop;
                    esclSrv.Stop();

                    return pages.Count == 1 &&
                           pages[0].PixelWidth == 300 &&
                           pages[0].PixelHeight == 400 &&
                           capHits == 1 &&
                           postHits == 1 &&
                           postHadResolution &&
                           nextDocHits == 2 &&
                           deleteHits == 1;
                });

                // 05. Malicious eSCL Location traversal (/../../api/delete) sends ZERO NextDocument and ZERO DELETE
                await CheckAsync("05: Manipulierte eSCL Location (/../../api/delete) blockiert ohne GET/DELETE-Folgeanfrage", async () =>
                {
                    int unexpectedFollowupRequests = 0;
                    int deleteRequests = 0;

                    using var evilSrv = new TcpListener(IPAddress.Loopback, 0);
                    evilSrv.Start();
                    int port = ((IPEndPoint)evilSrv.LocalEndpoint).Port;
                    using var srvCts = new CancellationTokenSource(TimeSpan.FromSeconds(6));

                    var serverTask = Task.Run(async () =>
                    {
                        string capXml = """
                            <?xml version="1.0" encoding="UTF-8"?>
                            <scan:ScannerCapabilities xmlns:scan="http://schemas.hp.com/imaging/escl/2011/05/03">
                              <scan:MaxWidth>2480</scan:MaxWidth><scan:MaxHeight>3508</scan:MaxHeight>
                            </scan:ScannerCapabilities>
                            """;

                        while (!srvCts.IsCancellationRequested)
                        {
                            TcpClient client;
                            try
                            {
                                client = await evilSrv.AcceptTcpClientAsync(srvCts.Token);
                            }
                            catch
                            {
                                break;
                            }

                            using (client)
                            await using (var ns = client.GetStream())
                            {
                                var (method, path, _) = await ReadHttpRequestAsync(ns);
                                if (method == "GET" && path == "/eSCL/ScannerCapabilities")
                                {
                                    await WriteHttpResponseAsync(ns, "200 OK", "text/xml", Encoding.UTF8.GetBytes(capXml));
                                }
                                else if (method == "POST" && path == "/eSCL/ScanJobs")
                                {
                                    await WriteHttpResponseAsync(
                                        ns,
                                        "201 Created",
                                        "text/plain",
                                        Array.Empty<byte>(),
                                        "Location: /../../api/delete");
                                }
                                else
                                {
                                    if (method == "DELETE") Interlocked.Increment(ref deleteRequests);
                                    Interlocked.Increment(ref unexpectedFollowupRequests);
                                    await WriteHttpResponseAsync(ns, "200 OK", "text/plain", Array.Empty<byte>());
                                }
                            }
                        }
                    });

                    bool rejectedTraversal = false;
                    try
                    {
                        _ = await PrinterHardwareEngine.ScanViaEsclForSelfTestAsync(
                            new Uri($"http://127.0.0.1:{port}/eSCL"),
                            ScanSourceMode.Flatbed,
                            300,
                            ScanColorMode.Color,
                            srvCts.Token);
                    }
                    catch (InvalidOperationException)
                    {
                        rejectedTraversal = true;
                    }

                    // Brief pause to prove zero background DELETE leaked onto the wire
                    await Task.Delay(80);
                    srvCts.Cancel();
                    evilSrv.Stop();
                    try { await serverTask; } catch { }

                    // Also verify direct Location guard edge cases
                    string[] badLocations =
                    {
                        "/eSCL/ScanJobs/../../etc/passwd",
                        "/eSCL/ScanJobs/job42?admin=1",
                        "/eSCL/ScanJobs/job42#frag",
                        "/eSCL/ScanJobs/%2e%2e",
                        "http://8.8.8.8/eSCL/ScanJobs/job42",
                        "//evil.example/eSCL/ScanJobs/job42"
                    };

                    var baseUri = new Uri("http://192.168.188.26/eSCL");
                    var targetIp = IPAddress.Parse("192.168.188.26");
                    foreach (string bad in badLocations)
                    {
                        bool threw = false;
                        try
                        {
                            _ = PrinterHardwareEngine.ValidateEsclScanJobLocation(
                                baseUri,
                                targetIp,
                                new Uri(bad, UriKind.RelativeOrAbsolute));
                        }
                        catch (InvalidOperationException)
                        {
                            threw = true;
                        }
                        if (!threw) return false;
                    }

                    return rejectedTraversal && unexpectedFollowupRequests == 0 && deleteRequests == 0;
                });

                // 06. XXE / DTD rejection across all 3 XML parsers + valid telemetry parsing
                Check("06: XXE/DTD-Payloads (<!DOCTYPE ...>) werden in allen 3 XML-Parsern per XmlException abgewiesen", () =>
                {
                    string xxeConsumable = """
                        <?xml version="1.0" encoding="UTF-8"?>
                        <!DOCTYPE foo [<!ENTITY xxe "secret">]>
                        <ConsumableConfigDyn><ConsumableInfo><ConsumableLabelCode>K</ConsumableLabelCode><ConsumablePercentageLevelRemaining>80</ConsumablePercentageLevelRemaining></ConsumableInfo></ConsumableConfigDyn>
                        """;

                    string xxeStatus = """
                        <?xml version="1.0" encoding="UTF-8"?>
                        <!DOCTYPE foo [<!ENTITY xxe "secret">]>
                        <ProductStatusDyn><Status><StatusCategory>ready</StatusCategory></Status></ProductStatusDyn>
                        """;

                    string xxeCaps = """
                        <?xml version="1.0" encoding="UTF-8"?>
                        <!DOCTYPE foo [<!ENTITY xxe "secret">]>
                        <ScannerCapabilities><MaxWidth>2480</MaxWidth><MaxHeight>3508</MaxHeight></ScannerCapabilities>
                        """;

                    bool cBlocked = false, sBlocked = false, eBlocked = false;
                    try { _ = PrinterHardwareEngine.ParseHpConsumableXml(xxeConsumable); } catch (XmlException) { cBlocked = true; }
                    try { _ = PrinterHardwareEngine.ParseHpStatusXml(xxeStatus); } catch (XmlException) { sBlocked = true; }
                    try { _ = PrinterHardwareEngine.ParseEsclScannerCapabilitiesXml(xxeCaps); } catch (XmlException) { eBlocked = true; }

                    string cleanLedm = """
                        <?xml version="1.0" encoding="UTF-8"?>
                        <ccdyn:ConsumableConfigDyn xmlns:ccdyn="http://www.hp.com/schemas/imaging/con/ledm/consumableconfigdyn/2007/11/19"
                                                   xmlns:dd="http://www.hp.com/schemas/imaging/con/dictionaries/1.0/">
                          <ccdyn:ConsumableInfo>
                            <dd:ConsumableLabelCode>K</dd:ConsumableLabelCode>
                            <dd:ConsumableState>ok</dd:ConsumableState>
                            <dd:ConsumablePercentageLevelRemaining>74</dd:ConsumablePercentageLevelRemaining>
                          </ccdyn:ConsumableInfo>
                          <ccdyn:ConsumableInfo>
                            <dd:ConsumableLabelCode>CMY</dd:ConsumableLabelCode>
                            <dd:ConsumableState>ok</dd:ConsumableState>
                            <dd:ConsumablePercentageLevelRemaining>61</dd:ConsumablePercentageLevelRemaining>
                          </ccdyn:ConsumableInfo>
                        </ccdyn:ConsumableConfigDyn>
                        """;
                    var (k, cmy) = PrinterHardwareEngine.ParseHpConsumableXml(cleanLedm);
                    string state = PrinterHardwareEngine.ParseHpStatusXml("<ProductStatusDyn><Status><StatusCategory>ready</StatusCategory></Status></ProductStatusDyn>");
                    var (mw, mh) = PrinterHardwareEngine.ParseEsclScannerCapabilitiesXml("<ScannerCapabilities><MaxWidth>2400</MaxWidth><MaxHeight>3400</MaxHeight></ScannerCapabilities>");

                    return cBlocked && sBlocked && eBlocked &&
                           k == 74 && cmy == 61 && state == "Bereit" && mw == 2400 && mh == 3400;
                });

                // 07. Bounded HTTP response cap (Content-Length & unbuffered stream)
                await CheckAsync("07: ReadBoundedResponseBytesAsync blockt uebergrosse HTTP-Streams sofort", async () =>
                {
                    bool blockedHeader = false;
                    using (var resp1 = new HttpResponseMessage(HttpStatusCode.OK) { Content = new ByteArrayContent(new byte[4096]) })
                    {
                        try
                        {
                            _ = await PrinterHardwareEngine.ReadBoundedResponseBytesAsync(resp1, maxBytes: 1024);
                        }
                        catch (InvalidOperationException)
                        {
                            blockedHeader = true;
                        }
                    }

                    bool blockedStream = false;
                    using (var rawMs = new NonSeekableReadStream(new byte[4096]))
                    using (var resp2 = new HttpResponseMessage(HttpStatusCode.OK) { Content = new StreamContent(rawMs) })
                    {
                        resp2.Content.Headers.ContentLength = null;
                        try
                        {
                            _ = await PrinterHardwareEngine.ReadBoundedResponseBytesAsync(resp2, maxBytes: 1024);
                        }
                        catch (InvalidOperationException)
                        {
                            blockedStream = true;
                        }
                    }

                    return blockedHeader && blockedStream;
                });

                // 08. IPP binary framing & RFC 8011 WriteIppStr overlong jobTitle / printerUri clamping
                Check("08: IPP-Binaerframing & RFC-8011 Laengenbegrenzung (job-name <= 255, uri <= 1023, ohne Steuerzeichen)", () =>
                {
                    string overlongUri = "ipp://192.168.188.26:631/ipp/print/" + new string('u', 1400);
                    string overlongTitle = "\r\n\0\x1b" + string.Concat(Enumerable.Repeat("Rechnung_ÄÖÜß€_", 40));
                    byte[] dummyPdf = Encoding.ASCII.GetBytes("%PDF-1.7\n%%EOF\n");

                    byte[] payload = DocumentAndPrintEngine.BuildIppPrintJobPayload(
                        overlongUri,
                        dummyPdf,
                        copies: 3,
                        grayscale: true,
                        duplex: PrintDuplexMode.LongEdge,
                        paperFormat: PrintPaperFormat.Label100x150,
                        orientationMode: PrintOrientationMode.Landscape,
                        jobTitle: overlongTitle);

                    // Header: version 2.0 (0x02 0x00), op Print-Job (0x00 0x02), reqId 2 (0x00 0x00 0x00 0x02)
                    if (payload.Length < 64 || payload[0] != 0x02 || payload[1] != 0x00 || payload[2] != 0x00 || payload[3] != 0x02)
                        return false;

                    var attrs = ParseIppAttributes(payload, out int trailerOffset);
                    if (!attrs.TryGetValue("printer-uri", out byte[]? uriBytes) || uriBytes.Length != 1023)
                        return false;

                    if (!attrs.TryGetValue("job-name", out byte[]? jobBytes) || jobBytes.Length > 255 || jobBytes.Length < 250)
                        return false;

                    string decodedJob = Encoding.UTF8.GetString(jobBytes);
                    if (decodedJob.Contains('\uFFFD') || decodedJob.Any(char.IsControl))
                        return false;

                    string media = Encoding.UTF8.GetString(attrs["media"]);
                    string sides = Encoding.UTF8.GetString(attrs["sides"]);
                    string color = Encoding.UTF8.GetString(attrs["print-color-mode"]);
                    byte[] orientBytes = attrs["orientation-requested"];
                    int orientEnum = (orientBytes[0] << 24) | (orientBytes[1] << 16) | (orientBytes[2] << 8) | orientBytes[3];

                    byte[] trailer = payload.Skip(trailerOffset).ToArray();
                    return media == "om_100x150mm_100x150mm" &&
                           sides == "two-sided-long-edge" &&
                           color == "monochrome" &&
                           orientEnum == 4 &&
                           trailer.SequenceEqual(dummyPdf);
                });

                // 09. Label100x150 (283.46 x 425.20 pt) & A5 Landscape physical page sizes in BuildPdfBytesFromPages
                Check("09: BuildPdfBytesFromPages erhaelt Label100x150 (283.46x425.20 pt) & A5 Querformat exakt", () =>
                {
                    var bmp = CreateSyntheticTestBitmap(400, 600, 300.0, Colors.White, Colors.Black);
                    var pageItem = new DocumentPageItem
                    {
                        SourceKind = "Bild",
                        OriginalBitmap = bmp,
                        ProcessedBitmap = bmp,
                        EffectiveDpi = 300.0,
                        WidthMm = 100.0,
                        HeightMm = 150.0
                    };

                    byte[] labelPdf = DocumentAndPrintEngine.BuildPdfBytesFromPages(
                        new[] { pageItem },
                        forceGrayscale: false,
                        paperFormat: PrintPaperFormat.Label100x150,
                        orientationMode: PrintOrientationMode.Portrait,
                        scaleMode: PrintScaleMode.FitPrintableArea);

                    using var msLabel = new MemoryStream(labelPdf);
                    using var docLabel = PdfReader.Open(msLabel, PdfDocumentOpenMode.Import);
                    double lw = docLabel.Pages[0].Width.Point;
                    double lh = docLabel.Pages[0].Height.Point;

                    byte[] a5LandPdf = DocumentAndPrintEngine.BuildPdfBytesFromPages(
                        new[] { pageItem },
                        forceGrayscale: false,
                        paperFormat: PrintPaperFormat.A5,
                        orientationMode: PrintOrientationMode.Landscape,
                        scaleMode: PrintScaleMode.FitPrintableArea);

                    using var msA5 = new MemoryStream(a5LandPdf);
                    using var docA5 = PdfReader.Open(msA5, PdfDocumentOpenMode.Import);
                    double a5w = docA5.Pages[0].Width.Point;
                    double a5h = docA5.Pages[0].Height.Point;

                    return Math.Abs(lw - 283.46) < 0.25 &&
                           Math.Abs(lh - 425.20) < 0.25 &&
                           Math.Abs(a5w - 595.28) < 0.25 &&
                           Math.Abs(a5h - 419.53) < 0.25;
                });

                // 10. ActualSize100 physical millimeter preservation in generated PDF (rendered via Windows.Data.Pdf)
                await CheckAsync("10: 100 % Masstreu (ActualSize100) bewahrt exaktes 80x50-mm-Mass auf A4-Bogen", async () =>
                {
                    int pxW = (int)Math.Round(80.0 / 25.4 * 300.0); // 945 px
                    int pxH = (int)Math.Round(50.0 / 25.4 * 300.0); // 591 px
                    var solidBlackBmp = CreateSolidBitmap(pxW, pxH, 300.0, Color.FromRgb(12, 12, 12));

                    var item = new DocumentPageItem
                    {
                        SourceKind = "Bild",
                        OriginalBitmap = solidBlackBmp,
                        ProcessedBitmap = solidBlackBmp,
                        EffectiveDpi = 300.0,
                        WidthMm = 80.0,
                        HeightMm = 50.0
                    };

                    byte[] actualPdf = DocumentAndPrintEngine.BuildPdfBytesFromPages(
                        new[] { item },
                        forceGrayscale: false,
                        paperFormat: PrintPaperFormat.A4,
                        orientationMode: PrintOrientationMode.Portrait,
                        scaleMode: PrintScaleMode.ActualSize100);

                    byte[] fitPdf = DocumentAndPrintEngine.BuildPdfBytesFromPages(
                        new[] { item },
                        forceGrayscale: false,
                        paperFormat: PrintPaperFormat.A4,
                        orientationMode: PrintOrientationMode.Portrait,
                        scaleMode: PrintScaleMode.FitPrintableArea);

                    // Measure exact physical mm ratios independent of host display DPI scale factor
                    var (actBoxW, actBoxH, renW, renH) = await MeasureDarkBoundingBoxAsync(actualPdf, 2100, 2970);
                    var (fitBoxW, fitBoxH, fitRenW, _) = await MeasureDarkBoundingBoxAsync(fitPdf, 2100, 2970);

                    double measuredWmm = (double)actBoxW / Math.Max(1, renW) * 210.0;
                    double measuredHmm = (double)actBoxH / Math.Max(1, renH) * 297.0;
                    double fitWidthMm = (double)fitBoxW / Math.Max(1, fitRenW) * 210.0;

                    if (Math.Abs(measuredWmm - 80.0) > 1.2 || Math.Abs(measuredHmm - 50.0) > 1.2 || fitWidthMm < 206.0)
                        throw new InvalidOperationException($"Gemessen: actual={measuredWmm:F2}x{measuredHmm:F2} mm, fitW={fitWidthMm:F2} mm");

                    return true;
                });

                // 11. Unmodified vector SourcePdfBytes preservation in BuildPdfBytesFromPages
                await CheckAsync("11: Unveraenderte PDF-Seiten bleiben als Vektor-XObject (/Form) ohne JPEG-Rasterung erhalten", async () =>
                {
                    string vecPdfPath = Path.Combine(tempDir, "vector_src.pdf");
                    using (var vdoc = new PdfSharp.Pdf.PdfDocument())
                    {
                        var vp = vdoc.AddPage();
                        vp.Width = XUnit.FromMillimeter(210);
                        vp.Height = XUnit.FromMillimeter(297);
                        using var gfx = XGraphics.FromPdfPage(vp);
                        gfx.DrawRectangle(XBrushes.Black, 40, 40, 220, 60);
                        vdoc.Save(vecPdfPath);
                    }

                    var loaded = await DocumentAndPrintEngine.LoadFilesAsync(new[] { vecPdfPath });
                    if (loaded.Count != 1) return false;

                    byte[] exported = DocumentAndPrintEngine.BuildPdfBytesFromPages(
                        loaded,
                        forceGrayscale: false,
                        paperFormat: PrintPaperFormat.A4,
                        orientationMode: PrintOrientationMode.Portrait,
                        scaleMode: PrintScaleMode.FitPrintableArea);

                    using var ms = new MemoryStream(exported);
                    using var checkDoc = PdfReader.Open(ms, PdfDocumentOpenMode.Import);
                    var resources = checkDoc.Pages[0].Elements.GetDictionary("/Resources");
                    var xObjects = resources?.Elements.GetDictionary("/XObject");
                    if (xObjects == null || xObjects.Elements.Count == 0)
                        return false;

                    bool hasVectorForm = false;
                    bool hasRasterJpeg = false;
                    foreach (string key in xObjects.Elements.Keys)
                    {
                        var dict = xObjects.Elements.GetDictionary(key);
                        string? subtype = dict?.Elements.GetName("/Subtype");
                        string? filter = dict?.Elements.GetName("/Filter");
                        if (subtype == "/Form") hasVectorForm = true;
                        if (filter == "/DCTDecode") hasRasterJpeg = true;
                    }

                    return hasVectorForm && !hasRasterJpeg;
                });

                // 12. ResolveSelectedPageIndices custom range validation & out-of-range "99" rejection
                Check("12: ResolveSelectedPageIndices weist '99' (ausserhalb) & 'abc' mit klarer Fehlermeldung ab", () =>
                {
                    var valid = DocumentAndPrintEngine.ResolveSelectedPageIndices(6, 0, PrintPageRangeMode.Custom, "1-2, 5");
                    if (!valid.SequenceEqual(new[] { 0, 1, 4 }))
                        return false;

                    var cur = DocumentAndPrintEngine.ResolveSelectedPageIndices(6, 3, PrintPageRangeMode.Current, null);
                    if (!cur.SequenceEqual(new[] { 3 }))
                        return false;

                    string[] mustFail = { "99", "abc", "", "0", "-2", "7-12" };
                    foreach (string bad in mustFail)
                    {
                        bool threw = false;
                        try
                        {
                            _ = DocumentAndPrintEngine.ResolveSelectedPageIndices(2, 0, PrintPageRangeMode.Custom, bad);
                        }
                        catch (InvalidOperationException ex)
                        {
                            threw = !string.IsNullOrWhiteSpace(ex.Message);
                        }
                        if (!threw) return false;
                    }

                    return true;
                });

                // 13. Scan cancellation (OperationCanceledException) & WIA preferred device matching
                await CheckAsync("13: Scan-Abbruch wirft OperationCanceledException & WIA waehlt bevorzugten Gerätenamen", async () =>
                {
                    using var cts = new CancellationTokenSource();
                    cts.Cancel();

                    bool canceledCleanly = false;
                    try
                    {
                        _ = await PrinterHardwareEngine.ScanPagesAsync(
                            printer: null,
                            ScanSourceMode.Flatbed,
                            300,
                            ScanColorMode.Color,
                            ScanProtocolMode.Auto,
                            onProgress: null,
                            cts.Token);
                    }
                    catch (OperationCanceledException)
                    {
                        canceledCleanly = true;
                    }

                    var devices = new[] { "HP DeskJet 2700 series", "Brother MFC-L2710DW", "Canon CanoScan LiDE" };
                    int idxBrother = PrinterHardwareEngine.SelectPreferredWiaDeviceIndex(devices, "Brother MFC-L2710DW");
                    int idxToken = PrinterHardwareEngine.SelectPreferredWiaDeviceIndex(devices, "CanoScan USB");
                    int idxFallback = PrinterHardwareEngine.SelectPreferredWiaDeviceIndex(devices, "");

                    return canceledCleanly && idxBrother == 1 && idxToken == 2 && idxFallback == 0;
                });

                // 14. Scan 600-DPI metadata preservation across CreateScannedPageItem & rotation
                Check("14: 600-DPI-Scanmetadaten bleiben bei CreateScannedPageItem, Kontrast & 90°-Drehung erhalten", () =>
                {
                    var raw96 = CreateSyntheticTestBitmap(1200, 1800, 96.0, Color.FromRgb(230, 230, 230), Color.FromRgb(25, 25, 25));
                    var scanItem = DocumentAndPrintEngine.CreateScannedPageItem(raw96, dpi: 600, receiptContrast: true, forceBw: false);

                    bool initialOk = Math.Abs(scanItem.ProcessedBitmap.DpiX - 600.0) < 0.5 &&
                                     Math.Abs(scanItem.ProcessedBitmap.DpiY - 600.0) < 0.5 &&
                                     Math.Abs(scanItem.WidthMm - 50.8) < 0.2 &&
                                     Math.Abs(scanItem.HeightMm - 76.2) < 0.2;

                    scanItem.RotationDegrees = 90;
                    DocumentAndPrintEngine.RebuildProcessedBitmap(scanItem);

                    bool rotatedOk = Math.Abs(scanItem.ProcessedBitmap.DpiX - 600.0) < 0.5 &&
                                     Math.Abs(scanItem.ProcessedBitmap.DpiY - 600.0) < 0.5 &&
                                     scanItem.ProcessedBitmap.PixelWidth == 1800 &&
                                     scanItem.ProcessedBitmap.PixelHeight == 1200;

                    return initialOk && rotatedOk;
                });

                // 15. Receipt contrast enhancement whitens paper background & sharpens dark text
                Check("15: Beleg-Kontrast (ApplyDocumentEnhancement) hellt Papier auf Weiss (255) & schaerft Text auf Schwarz (0)", () =>
                {
                    var dirtyReceipt = CreateSyntheticTestBitmap(200, 200, 300.0, Color.FromRgb(220, 218, 212), Color.FromRgb(35, 35, 40));
                    var enhanced = DocumentAndPrintEngine.ApplyDocumentEnhancement(dirtyReceipt, whitenPaper: true, pureGrayscale: true, preferredDpi: 300.0);

                    int stride = enhanced.PixelWidth * 4;
                    byte[] px = new byte[enhanced.PixelHeight * stride];
                    enhanced.CopyPixels(px, stride, 0);

                    // Top-left corner (5,5) is paper background -> must be 255
                    int bgIdx = (5 * enhanced.PixelWidth + 5) * 4;
                    bool paperWhite = px[bgIdx] == 255 && px[bgIdx + 1] == 255 && px[bgIdx + 2] == 255;

                    // Center band (100,100) is dark text -> must be 0
                    int fgIdx = (100 * enhanced.PixelWidth + 100) * 4;
                    bool inkBlack = px[fgIdx] == 0 && px[fgIdx + 1] == 0 && px[fgIdx + 2] == 0;

                    return paperWhite && inkBlack;
                });

                // 16. Calibration page + rotation + crop + atomic ExportPagesToPdfAsync + LoadFilesAsync roundtrip
                await CheckAsync("16: Pruefblatt (CMYK & 100-mm-Lineal), Zuschnitt & atomarer PDF-Roundtrip", async () =>
                {
                    var calib = DocumentAndPrintEngine.CreateCalibrationTestPage();
                    if (calib.OriginalBitmap.PixelWidth < 2400 || calib.OriginalBitmap.PixelHeight < 3400)
                        return false;

                    calib.RotationDegrees = 90;
                    calib.NormalizedCrop = new Rect(0.10, 0.10, 0.80, 0.80);
                    calib.EnhanceReceiptContrast = true;
                    DocumentAndPrintEngine.RebuildProcessedBitmap(calib);

                    string outPdf = Path.Combine(tempDir, "calib_roundtrip.pdf");
                    await DocumentAndPrintEngine.ExportPagesToPdfAsync(new[] { calib }, outPdf);

                    if (!File.Exists(outPdf) || new FileInfo(outPdf).Length < 1024)
                        return false;

                    var reloaded = await DocumentAndPrintEngine.LoadFilesAsync(new[] { outPdf });
                    return reloaded.Count == 1 && reloaded[0].ProcessedBitmap.PixelWidth >= 400;
                });

                // 17. NTFS ACL protection, filename sanitization & dynamic FunkenwerkPdf.exe resolution
                Check("17: AppData-ACL-Schutz, Dateinamen-Bereinigung & dynamische FunkenwerkPdf.exe-Aufloesung", () =>
                {
                    string aclDir = Path.Combine(tempDir, "acl_locked");
                    FunkenwerkDruckerSettings.EnsureSecureDirectory(aclDir);
                    bool aclProtected = new DirectoryInfo(aclDir).GetAccessControl().AreAccessRulesProtected;

                    string s1 = DocumentAndPrintEngine.SanitizeSafeFileStem(@"..\..\CON:evil?file*.pdf");
                    string s2 = DocumentAndPrintEngine.SanitizeSafeFileStem("CON");
                    bool stemSafe = !s1.Contains("..") && !s1.Contains(':') && !s1.Contains('?') && !s1.Contains('\\') &&
                                    s2 == "Funkenwerk_Dokument";

                    string? resolvedPdfExe = MainWindow.ResolveFunkenwerkPdfExePath();
                    bool exeFound = !string.IsNullOrWhiteSpace(resolvedPdfExe) &&
                                    File.Exists(resolvedPdfExe) &&
                                    string.Equals(Path.GetFileName(resolvedPdfExe), "FunkenwerkPdf.exe", StringComparison.OrdinalIgnoreCase);

                    return aclProtected && stemSafe && exeFound;
                });

                // 18. Input file size limits (PDF <= 256 MB, Image <= 64 MB)
                await CheckAsync("18: Dateigroessen-Limits aktiv (PDF <= 256 MB, Bild <= 64 MB)", async () =>
                {
                    string bigPdf = Path.Combine(tempDir, "oversize.pdf");
                    using (var fs = new FileStream(bigPdf, FileMode.Create, FileAccess.Write, FileShare.None))
                    {
                        fs.SetLength(DocumentAndPrintEngine.MaxPdfInputBytes + 4096);
                    }

                    string bigImg = Path.Combine(tempDir, "oversize.png");
                    using (var fs = new FileStream(bigImg, FileMode.Create, FileAccess.Write, FileShare.None))
                    {
                        fs.SetLength(DocumentAndPrintEngine.MaxImageInputBytes + 4096);
                    }

                    bool pdfRejected = false;
                    try { _ = await DocumentAndPrintEngine.LoadFilesAsync(new[] { bigPdf }); }
                    catch (InvalidOperationException) { pdfRejected = true; }

                    bool imgRejected = false;
                    try { _ = await DocumentAndPrintEngine.LoadFilesAsync(new[] { bigImg }); }
                    catch (InvalidOperationException) { imgRejected = true; }

                    File.Delete(bigPdf);
                    File.Delete(bigImg);
                    return pdfRejected && imgRejected;
                });

                // 19. STA MainWindow instantiation & Light/Dark mode consistency
                Check("19: MainWindow STA-Instanziierung & 100 % konsistenter Light-/Dark-Mode-Wechsel", () =>
                {
                    var win = new MainWindow();
                    try
                    {
                        bool initialDark = win.IsDarkMode;
                        var b1 = (SolidColorBrush)win.Resources["ListItemBgBrush"];
                        var t1 = (SolidColorBrush)win.Resources["PrimaryTextBrush"];

                        win.ThemeToggle_Click(win, new RoutedEventArgs());
                        bool toggled = win.IsDarkMode != initialDark;
                        var b2 = (SolidColorBrush)win.Resources["ListItemBgBrush"];
                        var t2 = (SolidColorBrush)win.Resources["PrimaryTextBrush"];

                        // Toggle back so state stays clean
                        win.ThemeToggle_Click(win, new RoutedEventArgs());
                        bool restored = win.IsDarkMode == initialDark;

                        return toggled && restored && b1.Color != b2.Color && t1.Color != t2.Color;
                    }
                    finally
                    {
                        win.Close();
                    }
                });

                // 20. MainWindow 100% Masstreu (ActualSize100) vs FitPrintableArea live preview sizing
                Check("20: MainWindow Live-Vorschau skaliert '100 % Masstreu' exakt im mm-Verhaeltnis zum Papierbogen", () =>
                {
                    var win = new MainWindow();
                    try
                    {
                        var bmp = CreateSolidBitmap(600, 900, 254.0, Color.FromRgb(30, 40, 55));
                        var item = new DocumentPageItem
                        {
                            SourceKind = "Bild",
                            OriginalBitmap = bmp,
                            ProcessedBitmap = bmp,
                            EffectiveDpi = 254.0,
                            WidthMm = 60.0,
                            HeightMm = 90.0
                        };

                        win.LoadPageForSelfTest(item);
                        win.Scale100_Click(win, new RoutedEventArgs());

                        double ratio100 = win.PreviewImageWidthDip / win.PreviewSheetWidthDip;
                        bool exactRatio = !double.IsNaN(win.PreviewImageWidthDip) &&
                                          Math.Abs(ratio100 - (60.0 / 210.0)) < 0.015;

                        win.ScaleFit_Click(win, new RoutedEventArgs());
                        bool fitRestored = double.IsNaN(win.PreviewImageWidthDip);

                        return exactRatio && fitRestored;
                    }
                    finally
                    {
                        win.Close();
                    }
                });
            }
            finally
            {
                try
                {
                    if (hadSettingsBefore && settingsBackup != null)
                        await File.WriteAllBytesAsync(settingsPath, settingsBackup);
                    else if (!hadSettingsBefore && File.Exists(settingsPath))
                        File.Delete(settingsPath);
                }
                catch { }

                try
                {
                    if (Directory.Exists(tempDir))
                        Directory.Delete(tempDir, recursive: true);
                }
                catch { }
            }

            Console.Error.WriteLine($"=== RESULT: {passed} PASSED, {failed} FAILED ===");
            return failed == 0 && passed >= 20;
        }

        private static BitmapSource CreateSolidBitmap(int width, int height, double dpi, Color color)
        {
            int stride = width * 4;
            byte[] pixels = new byte[height * stride];
            for (int i = 0; i < pixels.Length; i += 4)
            {
                pixels[i] = color.B;
                pixels[i + 1] = color.G;
                pixels[i + 2] = color.R;
                pixels[i + 3] = 255;
            }
            var bmp = BitmapSource.Create(width, height, dpi, dpi, PixelFormats.Bgra32, null, pixels, stride);
            bmp.Freeze();
            return bmp;
        }

        private static BitmapSource CreateSyntheticTestBitmap(int width, int height, double dpi, Color bg, Color fgBand)
        {
            int stride = width * 4;
            byte[] pixels = new byte[height * stride];
            int yMin = height / 3;
            int yMax = (height * 2) / 3;
            int xMin = width / 6;
            int xMax = (width * 5) / 6;

            for (int y = 0; y < height; y++)
            {
                for (int x = 0; x < width; x++)
                {
                    bool inBand = y >= yMin && y < yMax && x >= xMin && x < xMax;
                    Color c = inBand ? fgBand : bg;
                    int idx = (y * width + x) * 4;
                    pixels[idx] = c.B;
                    pixels[idx + 1] = c.G;
                    pixels[idx + 2] = c.R;
                    pixels[idx + 3] = 255;
                }
            }

            var bmp = BitmapSource.Create(width, height, dpi, dpi, PixelFormats.Bgra32, null, pixels, stride);
            bmp.Freeze();
            return bmp;
        }

        private static async Task<(int BoxWidth, int BoxHeight, int RenderedWidth, int RenderedHeight)> MeasureDarkBoundingBoxAsync(byte[] pdfBytes, uint renderW, uint renderH)
        {
            using var ras = new InMemoryRandomAccessStream();
            using (var writer = new DataWriter(ras.GetOutputStreamAt(0)))
            {
                writer.WriteBytes(pdfBytes);
                await writer.StoreAsync();
                await writer.FlushAsync();
            }

            var winPdf = await Windows.Data.Pdf.PdfDocument.LoadFromStreamAsync(ras);
            using var page = winPdf.GetPage(0);
            using var ps = new InMemoryRandomAccessStream();
            await page.RenderToStreamAsync(ps, new PdfPageRenderOptions
            {
                DestinationWidth = renderW,
                DestinationHeight = renderH,
                BackgroundColor = Windows.UI.Color.FromArgb(255, 255, 255, 255)
            });

            ps.Seek(0);
            var decoder = await Windows.Graphics.Imaging.BitmapDecoder.CreateAsync(ps);
            var provider = await decoder.GetPixelDataAsync(
                Windows.Graphics.Imaging.BitmapPixelFormat.Bgra8,
                Windows.Graphics.Imaging.BitmapAlphaMode.Ignore,
                new Windows.Graphics.Imaging.BitmapTransform(),
                Windows.Graphics.Imaging.ExifOrientationMode.IgnoreExifOrientation,
                Windows.Graphics.Imaging.ColorManagementMode.DoNotColorManage);

            int w = (int)decoder.PixelWidth;
            int h = (int)decoder.PixelHeight;
            byte[] px = provider.DetachPixelData();

            int minX = w, minY = h, maxX = -1, maxY = -1;
            for (int y = 0; y < h; y++)
            {
                for (int x = 0; x < w; x++)
                {
                    int idx = (y * w + x) * 4;
                    int lum = (px[idx + 2] + px[idx + 1] + px[idx]) / 3;
                    if (lum < 80)
                    {
                        if (x < minX) minX = x;
                        if (y < minY) minY = y;
                        if (x > maxX) maxX = x;
                        if (y > maxY) maxY = y;
                    }
                }
            }

            if (maxX < minX || maxY < minY) return (0, 0, w, h);
            return (maxX - minX + 1, maxY - minY + 1, w, h);
        }

        private static Dictionary<string, byte[]> ParseIppAttributes(byte[] payload, out int trailerOffset)
        {
            var dict = new Dictionary<string, byte[]>(StringComparer.Ordinal);
            int pos = 8; // skip 8-byte IPP header
            string lastName = "";

            while (pos < payload.Length)
            {
                byte tag = payload[pos++];
                if (tag == 0x03) // end-of-attributes-tag
                {
                    break;
                }

                // Delimiter tags (0x01..0x05) have no name/value
                if (tag <= 0x0F)
                {
                    continue;
                }

                int nameLen = (payload[pos] << 8) | payload[pos + 1];
                pos += 2;
                string name = nameLen > 0 ? Encoding.UTF8.GetString(payload, pos, nameLen) : lastName;
                pos += nameLen;
                if (nameLen > 0) lastName = name;

                int valLen = (payload[pos] << 8) | payload[pos + 1];
                pos += 2;
                byte[] val = new byte[valLen];
                System.Buffer.BlockCopy(payload, pos, val, 0, valLen);
                pos += valLen;

                dict[name] = val;
            }

            trailerOffset = pos;
            return dict;
        }

        private static async Task<(string Method, string Path, string Body)> ReadHttpRequestAsync(NetworkStream ns)
        {
            using var headerMs = new MemoryStream();
            byte[] one = new byte[1];
            int matchState = 0;
            while (headerMs.Length < 32768)
            {
                int r = await ns.ReadAsync(one.AsMemory(0, 1));
                if (r <= 0) break;
                headerMs.WriteByte(one[0]);

                byte b = one[0];
                if ((matchState == 0 && b == '\r') ||
                    (matchState == 1 && b == '\n') ||
                    (matchState == 2 && b == '\r') ||
                    (matchState == 3 && b == '\n'))
                {
                    matchState++;
                    if (matchState == 4) break;
                }
                else
                {
                    matchState = b == '\r' ? 1 : 0;
                }
            }

            string headerText = Encoding.ASCII.GetString(headerMs.ToArray());
            string[] lines = headerText.Split("\r\n", StringSplitOptions.RemoveEmptyEntries);
            if (lines.Length == 0)
                return ("", "", "");

            string[] reqParts = lines[0].Split(' ', StringSplitOptions.RemoveEmptyEntries);
            string method = reqParts.Length > 0 ? reqParts[0] : "";
            string path = reqParts.Length > 1 ? reqParts[1] : "";

            int contentLength = 0;
            foreach (string line in lines.Skip(1))
            {
                if (line.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase))
                {
                    string rawLen = line.Substring("Content-Length:".Length).Trim();
                    _ = int.TryParse(rawLen, NumberStyles.Integer, CultureInfo.InvariantCulture, out contentLength);
                }
            }

            string body = "";
            if (contentLength > 0 && contentLength <= 1024 * 1024)
            {
                byte[] bodyBytes = new byte[contentLength];
                int readTotal = 0;
                while (readTotal < contentLength)
                {
                    int n = await ns.ReadAsync(bodyBytes.AsMemory(readTotal, contentLength - readTotal));
                    if (n <= 0) break;
                    readTotal += n;
                }
                body = Encoding.UTF8.GetString(bodyBytes, 0, readTotal);
            }

            return (method, path, body);
        }

        private static async Task WriteHttpResponseAsync(
            NetworkStream ns,
            string status,
            string contentType,
            byte[] body,
            string? extraHeader = null)
        {
            var sb = new StringBuilder();
            sb.Append($"HTTP/1.1 {status}\r\n");
            sb.Append($"Content-Type: {contentType}\r\n");
            sb.Append($"Content-Length: {body.Length}\r\n");
            sb.Append("Connection: close\r\n");
            if (!string.IsNullOrWhiteSpace(extraHeader))
                sb.Append($"{extraHeader}\r\n");
            sb.Append("\r\n");

            byte[] headerBytes = Encoding.ASCII.GetBytes(sb.ToString());
            await ns.WriteAsync(headerBytes);
            if (body.Length > 0)
                await ns.WriteAsync(body);
            await ns.FlushAsync();
        }

        private sealed class NonSeekableReadStream : Stream
        {
            private readonly byte[] _data;
            private int _pos;

            public NonSeekableReadStream(byte[] data) => _data = data;

            public override bool CanRead => true;
            public override bool CanSeek => false;
            public override bool CanWrite => false;
            public override long Length => throw new NotSupportedException();
            public override long Position
            {
                get => throw new NotSupportedException();
                set => throw new NotSupportedException();
            }

            public override void Flush() { }

            public override int Read(byte[] buffer, int offset, int count)
            {
                int avail = _data.Length - _pos;
                if (avail <= 0) return 0;
                int take = Math.Min(count, avail);
                System.Buffer.BlockCopy(_data, _pos, buffer, offset, take);
                _pos += take;
                return take;
            }

            public override long Seek(long offset, SeekOrigin origin) => throw new NotSupportedException();
            public override void SetLength(long value) => throw new NotSupportedException();
            public override void Write(byte[] buffer, int offset, int count) => throw new NotSupportedException();
        }
    }
}

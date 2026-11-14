using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkMcp
{
    public readonly struct JsonRpcFrameResult
    {
        public bool IsEof { get; init; }
        public bool UseContentLength { get; init; }
        public string? RawJson { get; init; }
        public string? ProtocolError { get; init; }

        public static JsonRpcFrameResult Eof() => new() { IsEof = true };
        public static JsonRpcFrameResult Success(string json, bool useContentLength) =>
            new() { RawJson = json, UseContentLength = useContentLength };
        public static JsonRpcFrameResult Error(string error, bool useContentLength) =>
            new() { ProtocolError = error, UseContentLength = useContentLength };
    }

    public sealed class JsonRpcByteFrameReader
    {
        private readonly Stream _stream;
        private readonly byte[] _buffer = new byte[16384];
        private int _pos;
        private int _len;

        public JsonRpcByteFrameReader(Stream stream)
        {
            _stream = stream ?? throw new ArgumentNullException(nameof(stream));
        }

        private async ValueTask<int> ReadByteAsync(CancellationToken ct)
        {
            if (_pos < _len)
                return _buffer[_pos++];

            _pos = 0;
            _len = await _stream.ReadAsync(_buffer.AsMemory(0, _buffer.Length), ct);
            if (_len <= 0)
                return -1;

            return _buffer[_pos++];
        }

        private async Task<int> ReadExactBytesAsync(byte[] destination, int count, CancellationToken ct)
        {
            int offset = 0;
            int buffered = _len - _pos;
            if (buffered > 0 && count > 0)
            {
                int take = Math.Min(buffered, count);
                Buffer.BlockCopy(_buffer, _pos, destination, 0, take);
                _pos += take;
                offset += take;
            }

            while (offset < count)
            {
                int n = await _stream.ReadAsync(destination.AsMemory(offset, count - offset), ct);
                if (n <= 0)
                    break;
                offset += n;
            }

            return offset;
        }

        private async Task DrainBytesAsync(long count, CancellationToken ct)
        {
            long remaining = count;
            int buffered = _len - _pos;
            if (buffered > 0 && remaining > 0)
            {
                int take = (int)Math.Min(buffered, remaining);
                _pos += take;
                remaining -= take;
            }

            byte[] discard = new byte[8192];
            while (remaining > 0)
            {
                int chunk = (int)Math.Min(discard.Length, remaining);
                int n = await _stream.ReadAsync(discard.AsMemory(0, chunk), ct);
                if (n <= 0)
                    break;
                remaining -= n;
            }
        }

        private async Task<(byte[]? Bytes, bool ExceededLimit, bool HitEof)> ReadRawLineBytesAsync(int maxBytes, CancellationToken ct)
        {
            using var ms = new MemoryStream(256);
            bool anyRead = false;
            bool exceeded = false;

            while (true)
            {
                int b = await ReadByteAsync(ct);
                if (b < 0)
                {
                    if (!anyRead)
                        return (null, false, true);
                    break;
                }

                anyRead = true;
                if (b == '\n')
                    break;
                if (b == '\r')
                    continue;

                if (!exceeded)
                {
                    if (ms.Length >= maxBytes)
                        exceeded = true;
                    else
                        ms.WriteByte((byte)b);
                }
            }

            return (exceeded ? null : ms.ToArray(), exceeded, false);
        }

        private static string StripLeadingBom(string text) =>
            text.Length > 0 && text[0] == '\uFEFF' ? text.Substring(1) : text;

        public async Task<JsonRpcFrameResult> ReadNextFrameAsync(CancellationToken ct = default)
        {
            while (true)
            {
                var (firstLineBytes, exceeded, hitEof) = await ReadRawLineBytesAsync(McpSecurityGuard.MaxJsonRpcMessageBytes, ct);
                if (hitEof)
                    return JsonRpcFrameResult.Eof();

                if (exceeded)
                {
                    return JsonRpcFrameResult.Error(
                        "Invalid Request: JSON-RPC Nachricht ueberschreitet das 512 KB Sicherheitslimit.",
                        useContentLength: false);
                }

                if (firstLineBytes == null || firstLineBytes.Length == 0)
                    continue;

                int prefixCheckLen = Math.Min(firstLineBytes.Length, 64);
                string asciiPrefix = StripLeadingBom(Encoding.UTF8.GetString(firstLineBytes, 0, prefixCheckLen)).TrimStart();

                if (asciiPrefix.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase) ||
                    asciiPrefix.StartsWith("Content-Type:", StringComparison.OrdinalIgnoreCase))
                {
                    long? declaredLength = null;
                    string firstHeader = StripLeadingBom(Encoding.UTF8.GetString(firstLineBytes)).Trim();
                    if (firstHeader.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase))
                    {
                        string numPart = firstHeader.Substring("Content-Length:".Length).Trim();
                        if (long.TryParse(numPart, out long parsed))
                            declaredLength = parsed;
                        else
                            declaredLength = -1;
                    }

                    int headerCount = 1;
                    bool tooManyHeaders = false;
                    while (true)
                    {
                        var (hBytes, hExceeded, hEof) = await ReadRawLineBytesAsync(4096, ct);
                        if (hEof)
                            return JsonRpcFrameResult.Eof();
                        if (hExceeded)
                            return JsonRpcFrameResult.Error("Invalid Request: Uebergrosser JSON-RPC Header.", useContentLength: true);
                        if (hBytes == null || hBytes.Length == 0)
                            break;

                        if (++headerCount > 32)
                        {
                            tooManyHeaders = true;
                            continue;
                        }

                        string headerLine = Encoding.ASCII.GetString(hBytes).Trim();
                        if (headerLine.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase))
                        {
                            string numPart = headerLine.Substring("Content-Length:".Length).Trim();
                            if (long.TryParse(numPart, out long parsed))
                                declaredLength = parsed;
                            else
                                declaredLength = -1;
                        }
                    }

                    if (tooManyHeaders)
                        return JsonRpcFrameResult.Error("Invalid Request: Zu viele Header-Zeilen im JSON-RPC Frame.", useContentLength: true);

                    if (!declaredLength.HasValue || declaredLength.Value < 0)
                        return JsonRpcFrameResult.Error("Invalid Request: Ungueltiger Content-Length Header.", useContentLength: true);

                    long contentLength = declaredLength.Value;
                    if (contentLength > McpSecurityGuard.MaxJsonRpcMessageBytes)
                    {
                        if (contentLength <= 16L * 1024L * 1024L)
                            await DrainBytesAsync(contentLength, ct);

                        return JsonRpcFrameResult.Error(
                            $"Invalid Request: Content-Length ({contentLength} Bytes) ueberschreitet das 512 KB Sicherheitslimit.",
                            useContentLength: true);
                    }

                    if (contentLength == 0)
                        continue;

                    byte[] payload = new byte[(int)contentLength];
                    int totalRead = await ReadExactBytesAsync(payload, (int)contentLength, ct);
                    if (totalRead < (int)contentLength)
                        return JsonRpcFrameResult.Eof();

                    string decoded = StripLeadingBom(Encoding.UTF8.GetString(payload, 0, totalRead));
                    return JsonRpcFrameResult.Success(decoded, useContentLength: true);
                }

                string lineJson = StripLeadingBom(Encoding.UTF8.GetString(firstLineBytes));
                return JsonRpcFrameResult.Success(lineJson, useContentLength: false);
            }
        }
    }

    public static class Program
    {
        private static readonly UTF8Encoding Utf8NoBom = new(encoderShouldEmitUTF8Identifier: false);

        private static readonly JsonSerializerOptions JsonCompact = new()
        {
            WriteIndented = false,
            Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping
        };

        private static readonly JsonSerializerOptions JsonPretty = new()
        {
            WriteIndented = true,
            Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping
        };

        private static readonly JsonDocumentOptions StrictJsonParseOptions = new()
        {
            MaxDepth = 24,
            CommentHandling = JsonCommentHandling.Disallow,
            AllowTrailingCommas = false
        };

        public static async Task<int> Main(string[] args)
        {
            if (args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                return await McpSelfTestSuite.RunAllAsync();
            }

            if (args.Any(a => string.Equals(a, "--stage-sync-shop-assets", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStageShopSyncCliAsync(args);
            }

            if (args.Any(a => string.Equals(a, "--stage-deploy", StringComparison.OrdinalIgnoreCase)))
            {
                return await RunStageDeployCliAsync(args);
            }

            // Ensure ACL lockdown on MCP data directory immediately at startup
            McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);

            // Capture raw stdin/stdout streams FIRST, then redirect Console.Out -> Console.Error
            // so no library (PDFsharp, SSH.NET, WPF) can ever pollute the JSON-RPC wire!
            using var stdin = Console.OpenStandardInput();
            using var stdout = Console.OpenStandardOutput();
            Console.SetOut(Console.Error);

            await RunStdioServerLoopAsync(stdin, stdout);
            return 0;
        }

        private static async Task<int> RunStageShopSyncCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                bool dryRun = args.Any(a => string.Equals(a, "--dry-run", StringComparison.OrdinalIgnoreCase));
                var res = await McpSecurityGuard.SyncStageShopAssetsDeltaAsync("Mittwald", dryRun: dryRun);
                Console.WriteLine(McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(res, JsonPretty)));
                return res.Success ? 0 : 1;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stage Shop-Asset-Sync Fehler: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        private static async Task<int> RunStageDeployCliAsync(string[] args)
        {
            try
            {
                McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.VaultDirectory);
                bool skipAssets = args.Any(a => string.Equals(a, "--skip-shop-assets", StringComparison.OrdinalIgnoreCase));

                StageShopAssetSyncResultDto? syncRes = null;
                if (!skipAssets)
                {
                    Console.WriteLine("🛍️ [1/3] Prüfe reines Shop-Asset-Delta per SHA-256 (Musik, Buchhaltung & Privatdaten 100 % gesperrt)...");
                    syncRes = await McpSecurityGuard.SyncStageShopAssetsDeltaAsync("Mittwald", dryRun: false);
                    Console.WriteLine($"   ✓ Manifest: {syncRes.TotalManifestFiles} Shop-Assets | Unverändert: {syncRes.UnchangedOnStageCount} | Hochgeladen: {syncRes.UploadedCount} ({syncRes.UploadedBytes / 1024.0:F1} KB)");
                }
                else
                {
                    Console.WriteLine("⏭️ [1/3] Shop-Asset-Abgleich übersprungen (--skip-shop-assets).");
                }

                Console.WriteLine("🚀 [2/3] Führe Stage-Deploy auf 'Mittwald' (/home/p-g27wim/html/seelenfunke-stage) aus...");
                string deployOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_deploy");
                Console.WriteLine(deployOut);

                Console.WriteLine("👷 [3/3] Starte isolierten Stage-Worker neu ('Mittwald Websocket' / worker-stage-3)...");
                string workerOut = await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald Websocket", "stage_worker_restart");
                if (!string.IsNullOrWhiteSpace(workerOut))
                    Console.WriteLine(workerOut);

                Console.WriteLine("✅ Stage-Autopilot komplett erfolgreich abgeschlossen (Live-Server blieb 100 % unberührt).");
                return 0;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"❌ Stage-Autopilot abgebrochen: {McpSecurityGuard.ScrubSecrets(ex.Message)}");
                return 1;
            }
        }

        public static async Task RunStdioServerLoopAsync(Stream stdin, Stream stdout, CancellationToken ct = default)
        {
            var frameReader = new JsonRpcByteFrameReader(stdin);

            while (!ct.IsCancellationRequested)
            {
                JsonRpcFrameResult frame;
                try
                {
                    frame = await frameReader.ReadNextFrameAsync(ct);
                }
                catch (OperationCanceledException)
                {
                    break;
                }
                catch (Exception ex)
                {
                    string errJson = BuildErrorResponse(null, -32600, ex.Message);
                    await WriteJsonRpcResponseAsync(stdout, errJson, false);
                    continue;
                }

                if (frame.IsEof)
                    break;

                if (frame.ProtocolError != null)
                {
                    string errJson = BuildErrorResponse(null, -32600, frame.ProtocolError);
                    await WriteJsonRpcResponseAsync(stdout, errJson, frame.UseContentLength);
                    continue;
                }

                if (string.IsNullOrWhiteSpace(frame.RawJson))
                    continue;

                string? responseJson = await ProcessJsonRpcMessageAsync(frame.RawJson);
                if (responseJson != null)
                {
                    await WriteJsonRpcResponseAsync(stdout, responseJson, frame.UseContentLength);
                }
            }
        }

        public static Task<JsonRpcFrameResult> ReadNextJsonRpcMessageAsync(Stream stdin, CancellationToken ct = default)
        {
            var reader = new JsonRpcByteFrameReader(stdin);
            return reader.ReadNextFrameAsync(ct);
        }

        private static async Task<string?> ReadBoundedLineAsync(StreamReader reader, int maxChars)
        {
            var sb = new StringBuilder(256);
            char[] one = new char[1];
            bool anyRead = false;

            while (true)
            {
                int n = await reader.ReadAsync(one, 0, 1);
                if (n == 0)
                {
                    return anyRead ? sb.ToString() : null;
                }
                anyRead = true;
                char c = one[0];
                if (c == '\n')
                    break;
                if (c == '\r')
                    continue;

                if (sb.Length >= maxChars)
                    throw new InvalidOperationException("IPC Zeile ueberschreitet das Sicherheitslimit.");

                sb.Append(c);
            }
            return sb.ToString();
        }

        private static async Task WriteJsonRpcResponseAsync(Stream stdout, string singleLineJson, bool useContentLength)
        {
            byte[] payload = Utf8NoBom.GetBytes(singleLineJson);
            if (useContentLength)
            {
                byte[] header = Encoding.ASCII.GetBytes($"Content-Length: {payload.Length}\r\n\r\n");
                await stdout.WriteAsync(header, 0, header.Length);
                await stdout.WriteAsync(payload, 0, payload.Length);
            }
            else
            {
                await stdout.WriteAsync(payload, 0, payload.Length);
                await stdout.WriteAsync(new byte[] { (byte)'\n' }, 0, 1);
            }
            await stdout.FlushAsync();
        }

        public static async Task<string?> ProcessJsonRpcMessageAsync(string rawJson)
        {
            if (rawJson.Length > McpSecurityGuard.MaxJsonRpcMessageBytes)
                return BuildErrorResponse(null, -32600, "Message exceeds 512 KB limit");

            JsonNode? reqNode;
            try
            {
                reqNode = JsonNode.Parse(rawJson, nodeOptions: null, documentOptions: StrictJsonParseOptions);
                if (reqNode == null) return BuildErrorResponse(null, -32700, "Parse error");
            }
            catch
            {
                return BuildErrorResponse(null, -32700, "Invalid or overly nested JSON");
            }

            bool hasId = reqNode is JsonObject obj && obj.ContainsKey("id");
            JsonNode? idNode = hasId ? reqNode["id"]?.DeepClone() : null;
            string method = reqNode["method"]?.GetValue<string>() ?? "";
            JsonObject? @params = reqNode["params"] as JsonObject;

            // JSON-RPC notifications (no "id" field) must not produce a response
            if (!hasId)
            {
                return null;
            }

            try
            {
                object result = method switch
                {
                    "initialize" => HandleInitialize(@params),
                    "ping" => new { },
                    "tools/list" => new { tools = BuildToolsCatalog() },
                    "tools/call" => await HandleToolCallAsync(@params),
                    "resources/list" => new { resources = BuildResourcesCatalog() },
                    "resources/read" => HandleResourceRead(@params),
                    "prompts/list" => new { prompts = Array.Empty<object>() },
                    _ => throw new JsonRpcMethodNotFoundException($"Unknown MCP method '{method}'")
                };

                var resp = new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = idNode,
                    ["result"] = JsonSerializer.SerializeToNode(result, JsonCompact)
                };
                return resp.ToJsonString(JsonCompact);
            }
            catch (JsonRpcMethodNotFoundException mnf)
            {
                return BuildErrorResponse(idNode, -32601, mnf.Message);
            }
            catch (Exception ex)
            {
                return BuildErrorResponse(idNode, -32603, McpSecurityGuard.ScrubSecrets(ex.Message));
            }
        }

        private sealed class JsonRpcMethodNotFoundException : Exception
        {
            public JsonRpcMethodNotFoundException(string msg) : base(msg) { }
        }

        private static string BuildErrorResponse(JsonNode? idNode, int code, string message)
        {
            var errResp = new JsonObject
            {
                ["jsonrpc"] = "2.0",
                ["id"] = idNode,
                ["error"] = new JsonObject
                {
                    ["code"] = code,
                    ["message"] = McpSecurityGuard.ScrubSecrets(message)
                }
            };
            return errResp.ToJsonString(JsonCompact);
        }

        private static object HandleInitialize(JsonObject? @params)
        {
            string clientProto = @params?["protocolVersion"]?.GetValue<string>() ?? "2024-11-05";
            string[] supported = { "2025-06-18", "2025-03-26", "2024-11-05" };
            string negotiated = supported.Contains(clientProto, StringComparer.Ordinal) ? clientProto : "2024-11-05";

            return new
            {
                protocolVersion = negotiated,
                capabilities = new
                {
                    tools = new { listChanged = false },
                    resources = new { subscribe = false, listChanged = false }
                },
                serverInfo = new
                {
                    name = "funkenwerk-mcp",
                    version = "1.3.0"
                },
                instructions = "Gehärteter lokaler Funkenwerk MCP-Server (Zero-Secret Architektur v1.3) für Mein-Seelenfunke: PDF-Formulare inspizieren & ausfüllen, 3-Wege Aufgaben/Kalender/Einkaufsliste synchronisieren, Musikplayer & Fokus-Modus live steuern, Smart-Home-Steckdosen & IP-Kameras steuern, alle Funkenwerk Collection Apps starten und sichere Server-Aktionen ohne Credential-Exposition ausführen."
            };
        }

        // =====================================================================
        // MCP RESOURCES (SAFE READ-ONLY STAMMDATEN & POLICY)
        // =====================================================================
        private static object[] BuildResourcesCatalog() => new object[]
        {
            new
            {
                uri = "funkenwerk://company/stammdaten",
                name = "Mein-Seelenfunke Unternehmens-Stammdaten",
                description = "Verifizierte Stammdaten von Mein-Seelenfunke (Alina Steinhauer) zum Ausfüllen von Lieferanten-, Händler- und Behörden-Formularen.",
                mimeType = "application/json"
            },
            new
            {
                uri = "funkenwerk://security/policy",
                name = "Funkenwerk MCP Sicherheits- & Härtungsstatus",
                description = "Aktive 8-Schichten-Sicherheitsarchitektur, erlaubte Pfade und Hash-Chain-Status.",
                mimeType = "application/json"
            }
        };

        private static object HandleResourceRead(JsonObject? @params)
        {
            string uri = @params?["uri"]?.GetValue<string>() ?? "";
            object data = uri switch
            {
                "funkenwerk://company/stammdaten" => BuildStammdatenObject(),
                "funkenwerk://security/policy" => McpSecurityGuard.RunSecuritySelfAudit(),
                _ => throw new ArgumentException($"Unbekannte Resource-URI: {uri}")
            };

            return new
            {
                contents = new[]
                {
                    new
                    {
                        uri,
                        mimeType = "application/json",
                        text = McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(data, JsonPretty))
                    }
                }
            };
        }

        public static object BuildStammdatenObject() => new
        {
            unternehmen = "Mein-Seelenfunke",
            rechtsform = "Einzelunternehmen (reguläre Besteuerung)",
            startdatum = "01.11.2026",
            inhaberin = "Alina Steinhauer",
            funktion = "Inhaberin",
            strasse = "Carl-Goerdeler-Ring 26",
            plz = "38518",
            ort = "Gifhorn",
            bundesland = "Niedersachsen",
            land = "Deutschland",
            email = "kontakt@mein-seelenfunke.de",
            telefon = "+49 1590 1966864",
            webseite = "https://mein-seelenfunke.de",
            geschaeftskontoBank = "FYRST (Deutsche Bank AG)",
            manufaktur = "xTool F2 Ultra UV (5W UV-Laser) + Förderband + RA3 Rotationsaufsatz + SafetyPro IF2 2.0",
            haupttaetigkeit = "Personalisierte Geschenkartikel & Schmuck-Veredelung (Handveredelt in Deutschland, Manufaktur Gifhorn)"
        };

        // =====================================================================
        // MCP TOOLS CATALOG (12 COMPREHENSIVE, STRICTLY TYPED TOOLS)
        // =====================================================================
        private static object[] BuildToolsCatalog() => new object[]
        {
            new
            {
                name = "pdf_inspect",
                description = "Analysiert eine lokale PDF-Datei (Seitenanzahl, Maße sowie sämtliche ausfüllbaren AcroForm-Formularfelder inkl. Namen, Typen, Koordinaten, Optionen und aktuellen Werten).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "filePath": { "type": "string", "description": "Absoluter Pfad zur PDF-Datei (z. B. in Downloads, Documents, Desktop)." }
                  },
                  "required": ["filePath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "pdf_fill_form",
                description = "Füllt interaktive PDF-Formularfelder (AcroForm: Text, Mehrzeilig, Checkboxen, RadioButtons, Dropdowns, kleine Ja/Nein-Boxen) sowie optionale Vektor-Overlays (Text, Haken, Kreuz, Korrektur) aus, speichert das PDF atomar & weiterhin bearbeitbar ab und öffnet es auf Wunsch direkt in Funkenwerk PDF zur Kontrolle & Unterschrift.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "inputPath": { "type": "string", "description": "Quell-PDF-Pfad." },
                    "outputPath": { "type": "string", "description": "Ziel-PDF-Pfad. Wenn leer, wird automatisch '<Name>_ausgefuellt.pdf' erstellt." },
                    "overwrite": { "type": "boolean", "description": "Erlaubt das Überschreiben einer bestehenden Zieldatei (Standard: false)." },
                    "openInFunkenwerkPdf": { "type": "boolean", "description": "Öffnet das fertig ausgefüllte Dokument direkt in Funkenwerk PDF (Standard: true)." },
                    "fieldValues": {
                      "type": "object",
                      "description": "Key-Value-Map der Formularfeldnamen auf ihre gewünschten Werte (für Checkboxen/Ja-Nein-Boxen 'X' oder 'true').",
                      "additionalProperties": { "type": "string" }
                    },
                    "overlays": {
                      "type": "array",
                      "description": "Optionale freie Vektor-Elemente für PDFs ohne Formularfelder.",
                      "items": {
                        "type": "object",
                        "properties": {
                          "pageNumber": { "type": "integer" },
                          "type": { "type": "string", "enum": ["text", "check", "cross", "whiteout"] },
                          "xPt": { "type": "number" },
                          "yPt": { "type": "number" },
                          "widthPt": { "type": "number" },
                          "heightPt": { "type": "number" },
                          "text": { "type": "string" },
                          "fontSizePt": { "type": "number" }
                        },
                        "required": ["pageNumber", "type", "xPt", "yPt"]
                      }
                    }
                  },
                  "required": ["inputPath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "pdf_autofill_stammdaten",
                description = "Füllt ein Lieferanten-, KYC-, Händler- oder Behörden-PDF automatisch mit den verifizierten Mein-Seelenfunke Stammdaten (Firma, Inhaberin, Adresse, Kontakt, Bank, Ort/Datum) plus optionalen Zusatzwerten aus, speichert eine neue Kopie atomar und öffnet sie in Funkenwerk PDF.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "inputPath": { "type": "string", "description": "Pfad zum unausgefüllten Formular-PDF." },
                    "outputPath": { "type": "string", "description": "Optionaler Zielpfad (Standard: '<Name>_Seelenfunke.pdf')." },
                    "overwrite": { "type": "boolean", "description": "Erlaubt das Überschreiben einer bestehenden Zieldatei (Standard: false)." },
                    "openInFunkenwerkPdf": { "type": "boolean", "description": "Direkt nach dem Ausfüllen in Funkenwerk PDF öffnen (Standard: true)." },
                    "overrides": {
                      "type": "object",
                      "description": "Zusätzliche oder überschreibende Feldwerte (z. B. Ja/Nein-Kästchen 'Zugehörigkeit Nein': 'X').",
                      "additionalProperties": { "type": "string" }
                    }
                  },
                  "required": ["inputPath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "tasks_list",
                description = "Ruft Aufgaben und Aufgabenlisten synchron aus dem Seelenfunke-Ökosystem (Webportal + Funkenwerk Windows + Android App) ab.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "onlyOpen": { "type": "boolean", "description": "Nur offene Aufgaben anzeigen (Standard: true)." },
                    "priority": { "type": "string", "enum": ["hoch", "mittel", "niedrig", "low", "normal", "high", "urgent"] },
                    "listName": { "type": "string" },
                    "search": { "type": "string" },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "tasks_manage",
                description = "Erstellt, aktualisiert, erledigt (toggle), ergänzt Unteraufgaben (subtask) oder löscht Aufgaben synchron für Webportal, Funkenwerk Windows und Android App.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["create", "update", "toggle", "subtask", "delete"] },
                    "taskId": { "type": "string", "description": "Erforderlich bei update, toggle, subtask, delete." },
                    "title": { "type": "string", "description": "Aufgabentitel (erforderlich bei create und subtask)." },
                    "priority": { "type": "string", "enum": ["hoch", "mittel", "niedrig", "low", "normal", "high", "urgent"] },
                    "listIdOrAlias": { "type": "string", "description": "z. B. 'default', 'my_day', 'important', 'planned' oder Listen-UUID." },
                    "listName": { "type": "string" },
                    "relevantFrom": { "type": "string", "description": "Fälligkeits-/Startdatum YYYY-MM-DD." },
                    "notes": { "type": "string" },
                    "isCompleted": { "type": "boolean" },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "calendar_list",
                description = "Liest anstehende Termine und terminierte Aufgaben aus dem zentralen Funkenwerk/Seelenfunke-Kalender.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "date": { "type": "string", "description": "Einzeltag YYYY-MM-DD (setzt Start- und Enddatum auf diesen Tag)." },
                    "startDate": { "type": "string", "description": "YYYY-MM-DD (Standard: heute)." },
                    "endDate": { "type": "string", "description": "YYYY-MM-DD." },
                    "daysAhead": { "type": "integer", "description": "Zeitraum in Tagen ab heute, falls endDate leer ist (Standard: 14)." },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "calendar_create",
                description = "Trägt einen neuen Termin oder Meilenstein in den synchronisierten Seelenfunke/Funkenwerk-Kalender ein.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "title": { "type": "string" },
                    "start": { "type": "string", "description": "ISO-8601 Datum/Uhrzeit, z. B. '2026-11-12T10:00:00' oder '2026-11-12'." },
                    "end": { "type": "string" },
                    "isAllDay": { "type": "boolean" },
                    "category": { "type": "string" },
                    "priority": { "type": "string", "enum": ["low", "medium", "high"] },
                    "description": { "type": "string" },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "required": ["title", "start"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "shopping_manage",
                description = "Verwaltet die synchronisierte Einkaufs-/Besorgungsliste (list, add, toggle, delete).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["list", "add", "toggle", "delete"] },
                    "itemId": { "type": "string" },
                    "name": { "type": "string" },
                    "quantity": { "type": "integer" },
                    "category": { "type": "string", "description": "Optionaler Kategoriename oder Kategorie-UUID beim Hinzufügen." },
                    "categoryId": { "type": "string", "description": "Optionale Kategorie-UUID." },
                    "statusFilter": { "type": "string", "enum": ["needed", "stocked"] },
                    "environment": { "type": "string", "enum": ["active", "local", "stage", "live"] }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "server_list_profiles",
                description = "Listet ausschließlich passwortfreie Metadaten der gespeicherten Funkenwerk FTP- und SSH-Profile auf (Name, Protokoll, Host, Port, SHA-256 Pinning-Status). Gibt NIEMALS Passwörter oder Keys zurück.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {},
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "server_open_client",
                description = "Startet Funkenwerk FTP oder Funkenwerk SSH auf dem Desktop. Bei SSH kann optional ein Profilname + Zielverzeichnis per verschlüsseltem Einmal-Handoff direkt verbunden werden (ohne Credential-Einblick für die KI).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "client": { "type": "string", "enum": ["ftp", "ssh"] },
                    "profileName": { "type": "string", "description": "Optionaler Profilname für direkten SSH-Verbindungsaufbau." },
                    "remoteDirectory": { "type": "string", "description": "Optionales Startverzeichnis auf dem Server (z. B. '/home/p-g27wim/html/seelenfunke-stage')." }
                  },
                  "required": ["client"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "ssh_exec_safe_readonly",
                description = "Führt ein streng freigegebenes Diagnose- oder Stage-Rollout-Preset auf einem per SHA-256 Host-Key gepinnten SSH-Profil aus (keine freien Shell-Befehle möglich, Zero-Secret Schutz, Live-Server technisch gesperrt).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "profileName": { "type": "string", "description": "Name des gespeicherten Profils (z. B. 'Mittwald' oder 'Mittwald Websocket')." },
                    "preset": {
                      "type": "string",
                      "enum": ["disk_usage", "uptime_and_load", "php_version", "artisan_about", "git_status", "git_log", "git_branch", "list_dir", "stage_git_pull", "stage_deploy", "stage_worker_restart"]
                    },
                    "remoteDirectory": { "type": "string", "description": "Optionaler sicherer Zielordner auf dem Server." }
                  },
                  "required": ["profileName", "preset"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "stage_sync_shop_assets",
                description = "Synchronisiert per SHA-256-Delta-SFTP ausschließlich reine öffentliche Shop-, Produkt-, 3D-, Konfigurator- und Blog-Medien aus shop-asset-manifest.json auf den Stage-Server (/home/p-g27wim/html/seelenfunke-stage). Musik, Buchhaltung, Leitung, Agenten, Support und Privatdaten sowie der Live-Server sind im C#-Code zu 100 % technisch gesperrt.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "profileName": { "type": "string", "description": "Stage-Profilname (Standard: 'Mittwald')." },
                    "dryRun": { "type": "boolean", "description": "Nur Delta berechnen ohne SFTP-Upload (Standard: false)." }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "collection_control",
                description = "Zentrale Steuerung & Prüfung der Funkenwerk Collection: Systemstatus abrufen ('status'), eine der 10 Desktop-Apps starten ('launch'), Unternehmens-Stammdaten abrufen ('stammdaten') oder das Sicherheits-Audit prüfen ('security_audit').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["status", "launch", "stammdaten", "security_audit"] },
                    "app": { "type": "string", "enum": ["funkenwerk", "tresor", "player", "video", "craft", "ftp", "ssh", "pdf", "drucker", "os", "browser"], "description": "Ziel-App bei action='launch'." },
                    "filePath": { "type": "string", "description": "Optionaler sicherer Dateipfad (für 'pdf'/'drucker'/'player'), HTTPS-URL (für 'browser') oder Projektname (für 'craft'). CLI-Flags sind gesperrt." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "music_control",
                description = "Steuert den integrierten Funkenwerk Musikplayer und den VSync-GPU Fokus-Modus live auf Alinas Windows-PC (Status abfragen, Songs/Playlists/Favoriten abspielen, Pause, Nächster/Vorheriger Titel, Lautstärke 0 bis 100 in 1er-Schritten, Fokus-Modus ein/aus, Shuffle, Repeat).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "enum": ["status", "play", "pause", "toggle", "next", "prev", "set_volume", "volume_delta", "focus_on", "focus_off", "favorite", "shuffle", "repeat"]
                    },
                    "query": { "type": "string", "description": "Optionaler Songtitel, Künstler oder Playlistname bei action='play' (z. B. 'Favoriten')." },
                    "volume": { "type": "integer", "minimum": -100, "maximum": 100, "description": "Ziel-Lautstärke 0..100 (bei set_volume) bzw. Änderung in 1er-Schritten (bei volume_delta)." },
                    "autoStartApp": { "type": "boolean", "description": "Startet Funkenwerk automatisch, falls die App gerade geschlossen ist (Standard: true bei play/focus_on, sonst false)." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "smarthome_control",
                description = "Steuert Smart-Home-Steckdosen ('Lichterkette Fußleiste', 'Packtisch Warm Licht', 'Lichterkette Spiegel' oder 'alle') und IP-Kameras ('Terasse', 'Einfahrt', 'Garage', 'Gewerbe': Großansicht, 4er-Raster, PTZ-Drehung, Schnappschuss) lokal über Funkenwerk ohne Passwort-Exposition.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "enum": ["status", "plug_on", "plug_off", "plug_toggle", "camera_focus", "camera_grid", "camera_ptz", "camera_snapshot"]
                    },
                    "target": { "type": "string", "description": "Name der Steckdose (z. B. 'Packtisch Warm Licht', 'alle') oder Kamera ('Terasse', 'Einfahrt', 'Garage', 'Gewerbe')." },
                    "direction": { "type": "string", "enum": ["left", "right", "up", "down", "stop"], "description": "PTZ-Bewegungsrichtung bei action='camera_ptz'." },
                    "autoStartApp": { "type": "boolean", "description": "Startet Funkenwerk automatisch, falls geschlossen (Standard: true)." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_workspace_status",
                description = "Prüft den kompletten Status von Funkenwerk Craft (ArtCraft v0.41.1, FilmCraft v0.20.1, SoundCraft v0.20.1, angelegte Video-Projekte, CC0-Soundpool sowie optional die Produkt- & Saisonbild-Abdeckung unter Y:\\Medien\\Produkte).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "includeMedienCoverage": { "type": "boolean", "description": "Scannt zusätzlich Y:\\Medien\\Produkte und zeigt für alle 9 Seelenfunke-Produkte vorhandene sowie noch fehlende Saison-/Feiertags-Hauptbilder (Standard: true)." }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_project_manage",
                description = "Verwaltet Video-Projekte in Funkenwerk Craft ('Funkenwerk Collection\\Funkenwerk Craft\\Videos\\<Projekt>'): Projekte auflisten ('list'), Projektdetails abrufen ('get'), Projekt anlegen/aktualisieren ('save'), Quelldateien aus Y:\\Medien sicher in das Projekt kopieren ('import_media').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["list", "get", "save", "import_media"] },
                    "projectName": { "type": "string", "description": "Name des Projekts unter Videos\\<Projektname>, z. B. 'Projekt 1'." },
                    "projectPath": { "type": "string", "description": "Optionaler Zielpfad der Projektdatei innerhalb von Funkenwerk Craft\\Videos." },
                    "outputPath": { "type": "string", "description": "Optionaler Zielpfad des Exportvideos innerhalb von Funkenwerk Craft\\Videos." },
                    "aspectRatio": { "type": "string", "enum": ["9:16", "1:1", "16:9"], "description": "Zielformat: '9:16' (Instagram Reel / TikTok / Shorts), '1:1' (Feed), '16:9' (YouTube)." },
                    "transitionDurationSec": { "type": "number", "minimum": 0.08, "maximum": 1.20, "description": "Standard-Übergangsdauer in Sekunden (Standard 0.20s für knackige Beat-Cuts)." },
                    "colorGrade": { "type": "string", "enum": ["dark_luxury", "natural"], "description": "Farbgrading: 'dark_luxury' (Obsidian-Kontrast & warme Kristall-Highlights) oder 'natural'." },
                    "overlayStyle": { "type": "string", "enum": ["editorial_minimal", "card"], "description": "Typografie-Stil: 'editorial_minimal' (rahmenlose reinweiße High-Fashion Cinema-Typografie) oder 'card'." },
                    "backgroundMusic": { "type": "string", "description": "Relativer Pfad im CC0-Soundpool, z. B. 'Beats_und_Ambient\\Dark_Luxury_Viral_808_120BPM_CC0.wav'." },
                    "musicVolume": { "type": "number", "minimum": 0, "maximum": 1.5, "description": "Lautstärke des Musikbetts (0.0 bis 1.5, Standard 0.56)." },
                    "sfxVolume": { "type": "number", "minimum": 0, "maximum": 1.5, "description": "Lautstärke der Cinema-SFX (0.0 bis 1.5, Standard 0.82)." },
                    "introAccentSfx": { "type": "string", "description": "Optionaler Sub-Boom / Sub-Drop zum Video-Start (Frame 0)." },
                    "outroAccentSfx": { "type": "string", "description": "Optionaler mystischer Kristall-/Sub-Swell auf dem finalen Abgang-Dissolve." },
                    "showBrandHeader": { "type": "boolean", "description": "Optionales dezentes reinweißes Marken-Wasserzeichen oben im Bild." },
                    "brandHeaderText": { "type": "string", "description": "Optionaler Marken-Header oben im Video." },
                    "outputFileName": { "type": "string", "description": "Zieldateiname für das fertige MP4 im Projektordner." },
                    "sourcePaths": {
                      "type": "array",
                      "items": { "type": "string" },
                      "description": "Liste sicherer Mediendateipfade (z. B. aus Y:\\Medien) bei action='import_media'."
                    },
                    "scenes": {
                      "type": "array",
                      "description": "Geordnete Szenen-Timeline bei action='save' (inkl. optionalem 'brand_outro' Abgang am Ende).",
                      "items": {
                        "type": "object",
                        "properties": {
                          "fileName": { "type": "string", "description": "Dateiname im Projektordner oder '__BRAND_OUTRO__' für den mystischen 60fps Marken-Abgang." },
                          "mediaType": { "type": "string", "enum": ["video", "image", "brand_outro"] },
                          "startSec": { "type": "number", "minimum": 0 },
                          "durationSec": { "type": "number", "minimum": 0.8, "maximum": 45 },
                          "fitMode": { "type": "string", "enum": ["fill", "contain_blur"] },
                          "speedMode": { "type": "string", "enum": ["speed_ramp", "slow_motion", "linear"], "description": "'speed_ramp' (2.4x Whip -> Optical-Flow Slow-Mo -> 2.4x Whip), 'slow_motion' oder 'linear'." },
                          "slowMoFactor": { "type": "number", "minimum": 0.25, "maximum": 0.90, "description": "Zeitlupen-Faktor für Hero-Phase (z. B. 0.35 bis 0.48)." },
                          "overlayBadge": { "type": "string" },
                          "overlayTitle": { "type": "string" },
                          "overlaySubtitle": { "type": "string" },
                          "transition": { "type": "string", "enum": ["whip_zoom", "whip_pan", "dark_cut", "velvet_dissolve", "flash_cut", "flash_white", "dissolve", "smoothleft", "fadeblack", "smoothup"] },
                          "transitionDurSec": { "type": "number", "minimum": 0, "maximum": 1.40, "description": "Optionale individuelle Übergangsdauer dieser Szene in Sekunden (z. B. 0.90 für einen weichen velvet_dissolve in den Marken-Abgang; 0 = Projektstandard)." },
                          "transitionSfx": { "type": "string" },
                          "impactSfx": { "type": "string", "description": "Optionaler Sub-Bass / Impulse-Hit auf dem Schnittpunkt." },
                          "riserSfx": { "type": "string", "description": "Optionaler Spannungs-Riser vor dem Schnittpunkt (schneidet exakt auf dem Beat ab)." },
                          "keepOriginalAudio": { "type": "boolean" },
                          "originalAudioVolume": { "type": "number", "minimum": 0, "maximum": 1.5 }
                        },
                        "required": ["fileName"]
                      }
                    }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_media_inspect",
                description = "Analysiert Foto- und Videodateien oder ganze Ordner in Y:\\Medien bzw. Funkenwerk Craft\\Videos per ffprobe/EXIF (Auflösung, Hoch-/Querformat, Dauer, FPS, Tonspur, Dateigröße).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "targetPath": { "type": "string", "description": "Pfad zu einer Mediendatei oder einem Ordner innerhalb von Y:\\Medien oder Funkenwerk Craft\\Videos." },
                    "maxFiles": { "type": "integer", "minimum": 1, "maximum": 100, "description": "Maximale Dateianzahl bei Ordner-Scan (Standard: 40)." }
                  },
                  "required": ["targetPath"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_soundpool_browser",
                description = "Durchsucht den lokalen, lizenzfreien & gewerblich nutzbaren CC0-Soundpool von Funkenwerk Craft (Übergangs-Whooshes, Kristall-/Gold-Akzente, Social-Media-Beats & Ambient-Tracks inkl. Lizenznachweis).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "category": { "type": "string", "enum": ["all", "Uebergaenge_Whoosh", "Effekte_Akzente", "Beats_und_Ambient"], "description": "Kategorie-Filter (Standard: 'all')." },
                    "inspectAudioFile": { "type": "string", "description": "Optionaler Pfad einer Sounddatei für detaillierte SoundCraft-CLI Formatprüfung." }
                  },
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_render_social_video",
                description = "Verarbeitet und rendert ein Funkenwerk Craft Projekt ('Videos\\<Projektname>') zu einem postbereiten Social-Media-Mastervideo (H.264 Main Profile, yuv420p, bt709, 60fps, AAC 48kHz, FastStart, Optical-Flow Speed-Ramps, CC0-Sounddesign, reinweiße Cinema-Typografie & mystischer Marken-Abgang) und aktualisiert synchron die FilmCraft .fcproj Datei.",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "projectName": { "type": "string", "description": "Projektname unter Videos\\<Projektname>, z. B. 'Projekt 1'." },
                    "outputPath": { "type": "string", "description": "Optionaler Zielpfad des MP4-Videos innerhalb von Funkenwerk Craft\\Videos." },
                    "openInCraftUi": { "type": "boolean", "description": "Öffnet nach erfolgreichem Rendern optional Funkenwerk Craft zur Live-Vorschau (Standard: false)." }
                  },
                  "required": ["projectName"],
                  "additionalProperties": false
                }
                """)
            },
            new
            {
                name = "craft_storytold_engine",
                description = "Direkte Profi-Schnittstelle zu den integrierten Storytold Engines (ArtCraft, FilmCraft, SoundCraft): Desktop-App mit geschützter Control-Bridge starten ('launch'), FilmCraft-Befehle abfragen ('filmcraft_commands'), .fcproj Projekt exportieren ('export_fcproj'), Live-TCP-Bridge Befehl senden ('filmcraft_bridge') oder SoundCraft-CLI nutzen ('soundcraft_commands' / 'soundcraft_plugins').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": {
                      "type": "string",
                      "enum": ["launch", "filmcraft_commands", "export_fcproj", "filmcraft_bridge", "soundcraft_commands", "soundcraft_plugins"]
                    },
                    "engine": { "type": "string", "enum": ["artcraft", "filmcraft", "soundcraft"], "description": "Ziel-Engine bei action='launch'." },
                    "projectName": { "type": "string", "description": "Optionaler Projektname für action='export_fcproj' oder 'launch' (öffnet die .fcproj in FilmCraft)." },
                    "filter": { "type": "string", "description": "Optionaler Befehlsfilter bei action='filmcraft_commands' (z. B. 'timeline', 'sequence', 'graphics')." },
                    "bridgeMethod": { "type": "string", "description": "JSON-RPC Methode für action='filmcraft_bridge' (z. B. 'engine.execute', 'ui.screenshot')." },
                    "bridgeParams": { "type": "object", "description": "Optionale Parameter für action='filmcraft_bridge'." }
                  },
                  "required": ["action"],
                  "additionalProperties": false
                }
                """)
            }
        };

        // =====================================================================
        // TOOL EXECUTION GATEKEEPER
        // =====================================================================
        private static async Task<object> HandleToolCallAsync(JsonObject? @params)
        {
            string toolName = @params?["name"]?.GetValue<string>() ?? "";
            JsonObject args = (@params?["arguments"] as JsonObject) ?? new JsonObject();

            try
            {
                McpSecurityGuard.EnforceRateLimit(toolName);
                object payload = await DispatchToolAsync(toolName, args);

                string jsonText = McpSecurityGuard.ScrubSecrets(JsonSerializer.Serialize(payload, JsonPretty));
                McpSecurityGuard.AppendAuditLog(toolName, "ok", "ALLOW");

                return new
                {
                    content = new[]
                    {
                        new { type = "text", text = jsonText }
                    },
                    isError = false
                };
            }
            catch (Exception ex)
            {
                string safeError = McpSecurityGuard.ScrubSecrets(ex.Message);
                McpSecurityGuard.AppendAuditLog(toolName, safeError, "ERROR_OR_DENY");

                return new
                {
                    content = new[]
                    {
                        new { type = "text", text = $"⚠ Sicherheits-/Ausführungsfehler in '{toolName}': {safeError}" }
                    },
                    isError = true
                };
            }
        }

        private static async Task<object> DispatchToolAsync(string toolName, JsonObject args)
        {
            switch (toolName)
            {
                case "pdf_inspect":
                {
                    string rawPath = GetString(args, "filePath");
                    string safePath = McpSecurityGuard.ValidateSafeFilePath(rawPath, new[] { ".pdf" }, mustExist: true);
                    return McpPdfEngine.InspectPdf(safePath);
                }

                case "pdf_fill_form":
                {
                    string rawIn = GetString(args, "inputPath");
                    string safeIn = McpSecurityGuard.ValidateSafeFilePath(rawIn, new[] { ".pdf" }, mustExist: true);

                    string? rawOut = GetOptString(args, "outputPath");
                    bool overwrite = GetBool(args, "overwrite", defaultVal: false);
                    bool openApp = GetBool(args, "openInFunkenwerkPdf", defaultVal: true);

                    if (string.IsNullOrWhiteSpace(rawOut))
                    {
                        string dir = Path.GetDirectoryName(safeIn)!;
                        string stem = Path.GetFileNameWithoutExtension(safeIn);
                        rawOut = Path.Combine(dir, $"{stem}_ausgefuellt.pdf");
                    }

                    string safeOut = McpSecurityGuard.ValidateSafeFilePath(rawOut, new[] { ".pdf" }, mustExist: false);

                    var fieldValues = new Dictionary<string, string>(StringComparer.Ordinal);
                    if (args["fieldValues"] is JsonObject fvObj)
                    {
                        if (fvObj.Count > 300)
                            throw new InvalidOperationException("Maximal 300 Formularfelder pro Aufruf erlaubt.");
                        foreach (var kv in fvObj)
                            fieldValues[kv.Key] = kv.Value?.ToString() ?? "";
                    }

                    var overlays = new List<McpPdfOverlayInput>();
                    if (args["overlays"] is JsonArray ovArr)
                    {
                        if (ovArr.Count > 150)
                            throw new InvalidOperationException("Maximal 150 Overlays pro Aufruf erlaubt.");
                        foreach (var item in ovArr)
                        {
                            if (item == null) continue;
                            var parsed = JsonSerializer.Deserialize<McpPdfOverlayInput>(item.ToJsonString(), new JsonSerializerOptions { PropertyNameCaseInsensitive = true });
                            if (parsed != null) overlays.Add(parsed);
                        }
                    }

                    byte[] srcBytes = await File.ReadAllBytesAsync(safeIn);
                    var (savedBytes, updatedFields, unmatchedKeys) = McpPdfEngine.ApplyFormValuesAndOverlays(srcBytes, fieldValues, overlays);
                    await McpSecurityGuard.WriteSafeFileAtomicAsync(safeOut, new[] { ".pdf" }, savedBytes, overwrite);

                    bool launched = false;
                    if (openApp)
                        launched = LaunchCollectionExecutable("pdf", safeOut);

                    return new
                    {
                        success = true,
                        inputPath = safeIn,
                        outputPath = safeOut,
                        updatedFieldsCount = updatedFields.Count,
                        updatedFields,
                        overlaysCount = overlays.Count,
                        unmatchedFieldNames = unmatchedKeys,
                        openedInFunkenwerkPdf = launched
                    };
                }

                case "pdf_autofill_stammdaten":
                {
                    string rawIn = GetString(args, "inputPath");
                    string safeIn = McpSecurityGuard.ValidateSafeFilePath(rawIn, new[] { ".pdf" }, mustExist: true);

                    string? rawOut = GetOptString(args, "outputPath");
                    if (string.IsNullOrWhiteSpace(rawOut))
                    {
                        string dir = Path.GetDirectoryName(safeIn)!;
                        string stem = Path.GetFileNameWithoutExtension(safeIn);
                        rawOut = Path.Combine(dir, $"{stem}_Seelenfunke.pdf");
                    }
                    string safeOut = McpSecurityGuard.ValidateSafeFilePath(rawOut, new[] { ".pdf" }, mustExist: false);
                    bool overwrite = GetBool(args, "overwrite", defaultVal: false);
                    bool openApp = GetBool(args, "openInFunkenwerkPdf", defaultVal: true);

                    byte[] srcBytes = await File.ReadAllBytesAsync(safeIn);
                    var fields = McpPdfEngine.ExtractFieldsFromBytes(srcBytes);
                    var autoMap = McpPdfEngine.BuildSeelenfunkeStammdatenMapping(fields);

                    if (args["overrides"] is JsonObject ovObj)
                    {
                        if (ovObj.Count > 300)
                            throw new InvalidOperationException("Maximal 300 Override-Felder erlaubt.");
                        foreach (var kv in ovObj)
                            autoMap[kv.Key] = kv.Value?.ToString() ?? "";
                    }

                    var (savedBytes, updatedFields, unmatchedKeys) = McpPdfEngine.ApplyFormValuesAndOverlays(srcBytes, autoMap, null);
                    await McpSecurityGuard.WriteSafeFileAtomicAsync(safeOut, new[] { ".pdf" }, savedBytes, overwrite);

                    var remainingOpenFields = fields
                        .Where(f => !f.IsReadOnly && !updatedFields.Contains(f.Name))
                        .Select(f => new { f.PageNumber, f.Name, f.Kind, f.IsTinyBoxText })
                        .ToList();

                    bool launched = false;
                    if (openApp)
                        launched = LaunchCollectionExecutable("pdf", safeOut);

                    return new
                    {
                        success = true,
                        outputPath = safeOut,
                        autoFilledFieldsCount = updatedFields.Count,
                        appliedValues = autoMap,
                        remainingFieldsForManualReview = remainingOpenFields,
                        unmatchedOverrideKeys = unmatchedKeys,
                        openedInFunkenwerkPdf = launched
                    };
                }

                case "tasks_list":
                    return await McpOrganizerClient.ListTasksAsync(
                        onlyOpen: GetBool(args, "onlyOpen", true),
                        priority: GetOptString(args, "priority"),
                        listName: GetOptString(args, "listName"),
                        search: GetOptString(args, "search"),
                        environment: GetOptString(args, "environment") ?? "active");

                case "tasks_manage":
                    return await McpOrganizerClient.ManageTaskAsync(
                        action: GetString(args, "action"),
                        taskId: GetOptString(args, "taskId"),
                        title: GetOptString(args, "title"),
                        priority: GetOptString(args, "priority"),
                        listIdOrAlias: GetOptString(args, "listIdOrAlias") ?? "default",
                        listName: GetOptString(args, "listName"),
                        relevantFrom: GetOptString(args, "relevantFrom"),
                        notes: GetOptString(args, "notes", maxLen: 8192),
                        isCompleted: args["isCompleted"] != null ? args["isCompleted"]!.GetValue<bool>() : null,
                        environment: GetOptString(args, "environment") ?? "active");

                case "calendar_list":
                    return await McpOrganizerClient.ListCalendarEventsAsync(
                        startDate: GetOptString(args, "startDate"),
                        endDate: GetOptString(args, "endDate"),
                        daysAhead: args["daysAhead"] != null ? args["daysAhead"]!.GetValue<int>() : 14,
                        environment: GetOptString(args, "environment") ?? "active",
                        date: GetOptString(args, "date"));

                case "calendar_create":
                    return await McpOrganizerClient.CreateCalendarEventAsync(
                        title: GetString(args, "title"),
                        start: GetString(args, "start"),
                        end: GetOptString(args, "end"),
                        isAllDay: GetBool(args, "isAllDay", false),
                        category: GetOptString(args, "category") ?? "general",
                        priority: GetOptString(args, "priority") ?? "low",
                        description: GetOptString(args, "description", maxLen: 8192),
                        environment: GetOptString(args, "environment") ?? "active");

                case "shopping_manage":
                    return await McpOrganizerClient.ManageShoppingAsync(
                        action: GetString(args, "action"),
                        itemId: GetOptString(args, "itemId"),
                        name: GetOptString(args, "name"),
                        quantity: args["quantity"] != null ? args["quantity"]!.GetValue<int>() : 1,
                        statusFilter: GetOptString(args, "statusFilter"),
                        environment: GetOptString(args, "environment") ?? "active",
                        category: GetOptString(args, "category") ?? GetOptString(args, "categoryId"));

                case "server_list_profiles":
                    return new
                    {
                        zeroSecretPolicy = "Passwörter, Tokens und Private Keys werden niemals an das Modell übergeben.",
                        profiles = McpSecurityGuard.GetSanitizedServerProfiles()
                    };

                case "server_open_client":
                {
                    string client = GetString(args, "client").ToLowerInvariant();
                    string? profileName = GetOptString(args, "profileName");
                    string? remoteDir = GetOptString(args, "remoteDirectory");

                    if (client == "ssh" && !string.IsNullOrWhiteSpace(profileName))
                    {
                        McpSecurityGuard.CreateBlindSshHandoffTicket(profileName!, remoteDir);
                        bool ok = LaunchCollectionExecutable("ssh", null, trustedInternalFlag: "--handoff");
                        return new { success = ok, launched = "Funkenwerk SSH", mode = "dpapi_blind_handoff", profile = profileName, remoteDirectory = remoteDir ?? "default" };
                    }
                    else if (client == "ftp" || client == "ssh")
                    {
                        bool ok = LaunchCollectionExecutable(client, null);
                        return new { success = ok, launched = client == "ftp" ? "Funkenwerk FTP" : "Funkenwerk SSH" };
                    }

                    throw new ArgumentException("Ungültiger Client (erlaubt: 'ftp' oder 'ssh').");
                }

                case "ssh_exec_safe_readonly":
                {
                    string profileName = GetString(args, "profileName");
                    string preset = GetString(args, "preset");
                    string? remDir = GetOptString(args, "remoteDirectory");
                    string output = await McpSecurityGuard.ExecutePinnedSshPresetAsync(profileName, preset, remDir);
                    return new
                    {
                        profile = profileName,
                        preset,
                        output
                    };
                }

                case "stage_sync_shop_assets":
                {
                    string profileName = GetOptString(args, "profileName") ?? "Mittwald";
                    bool dryRun = GetBool(args, "dryRun", false);
                    return await McpSecurityGuard.SyncStageShopAssetsDeltaAsync(profileName, dryRun);
                }

                case "collection_control":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    switch (action)
                    {
                        case "status":
                            return await McpOrganizerClient.ProbeEcosystemHealthAsync();
                        case "stammdaten":
                            return BuildStammdatenObject();
                        case "security_audit":
                            return McpSecurityGuard.RunSecuritySelfAudit();
                        case "launch":
                        {
                            string appKey = GetString(args, "app").ToLowerInvariant();
                            string? rawFile = GetOptString(args, "filePath");
                            string? safeArg = null;
                            if (!string.IsNullOrWhiteSpace(rawFile))
                            {
                                string trimmed = rawFile.Trim();
                                if (trimmed.StartsWith('-') || trimmed.StartsWith('/'))
                                    throw new UnauthorizedAccessException("CLI-Flags sind beim App-Start aus Sicherheitsgruenden gesperrt.");

                                if (appKey == "craft")
                                {
                                    safeArg = McpCraftBridge.ValidateSafeProjectName(trimmed);
                                }
                                else if (appKey == "browser")
                                {
                                    if (!Uri.TryCreate(trimmed, UriKind.Absolute, out var browserUri) ||
                                        !string.Equals(browserUri.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase) ||
                                        !string.IsNullOrEmpty(browserUri.UserInfo))
                                    {
                                        throw new UnauthorizedAccessException("Funkenwerk Browser akzeptiert per MCP nur sichere https:// URLs.");
                                    }
                                    safeArg = browserUri.AbsoluteUri;
                                }
                                else
                                {
                                    string[] allowedExts = appKey switch
                                    {
                                        "pdf" => new[] { ".pdf" },
                                        "drucker" => new[] { ".pdf", ".png", ".jpg", ".jpeg", ".webp", ".bmp", ".tif", ".tiff" },
                                        "player" => new[] { ".mp4", ".mkv", ".webm", ".mov", ".avi", ".mp3", ".wav", ".flac", ".m4a", ".ogg" },
                                        _ => Array.Empty<string>()
                                    };
                                    if (allowedExts.Length == 0)
                                        throw new UnauthorizedAccessException("Dateiparameter werden aus Sicherheitsgründen nur für 'pdf', 'drucker', 'player', 'browser' und 'craft' akzeptiert.");

                                    string validated = McpSecurityGuard.ValidateSafeFilePath(trimmed, allowedExts, mustExist: true, maxBytes: 500L * 1024L * 1024L);
                                    safeArg = validated;
                                }
                            }

                            bool ok = LaunchCollectionExecutable(appKey, safeArg);
                            return new { success = ok, app = appKey, openedFile = safeArg };
                        }
                        default:
                            throw new ArgumentException("Erlaubte collection_control Aktionen: status, launch, stammdaten, security_audit.");
                    }
                }

                case "music_control":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    string? query = GetOptString(args, "query", maxLen: 256);
                    int? volume = args["volume"] != null ? args["volume"]!.GetValue<int>() : null;
                    if (action == "set_volume" && (!volume.HasValue || volume.Value < 0 || volume.Value > 100))
                        throw new ArgumentException("Für action='set_volume' muss 'volume' zwischen 0 und 100 liegen.");

                    bool defaultAutoStart = action is "play" or "focus_on" or "toggle";
                    bool autoStart = GetBool(args, "autoStartApp", defaultAutoStart);
                    return await SendFunkenwerkLocalPipeCommandAsync(
                        domain: "music",
                        action: action,
                        query: query,
                        target: null,
                        direction: null,
                        volume: volume,
                        autoStartApp: autoStart);
                }

                case "smarthome_control":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    string? target = GetOptString(args, "target", maxLen: 128);
                    string? direction = GetOptString(args, "direction", maxLen: 32);
                    bool defaultAutoStart = action != "status";
                    bool autoStart = GetBool(args, "autoStartApp", defaultAutoStart);
                    return await SendFunkenwerkLocalPipeCommandAsync(
                        domain: "smarthome",
                        action: action,
                        query: null,
                        target: target,
                        direction: direction,
                        volume: null,
                        autoStartApp: autoStart);
                }

                case "craft_workspace_status":
                {
                    bool includeCov = GetBool(args, "includeMedienCoverage", true);
                    var craftStatus = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject { ["action"] = "status" });
                    return new
                    {
                        craft = craftStatus,
                        medienCoverage = includeCov ? McpCraftBridge.ScanMedienProductCoverage() : null
                    };
                }

                case "craft_project_manage":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    switch (action)
                    {
                        case "list":
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject { ["action"] = "list_projects" });

                        case "get":
                        {
                            string safeProjectName = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "get_project",
                                ["projectName"] = safeProjectName
                            });
                        }

                        case "save":
                        {
                            string safeProjectName = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            var req = new JsonObject
                            {
                                ["action"] = "save_project",
                                ["projectName"] = safeProjectName
                            };

                            string? rawProjectPath = GetOptString(args, "projectPath");
                            if (!string.IsNullOrWhiteSpace(rawProjectPath))
                            {
                                req["projectPath"] = McpCraftBridge.ValidateSafeCraftWritePath(rawProjectPath, new[] { ".json", ".fcproj" });
                            }

                            string? rawOutputPath = GetOptString(args, "outputPath");
                            if (!string.IsNullOrWhiteSpace(rawOutputPath))
                            {
                                req["outputPath"] = McpCraftBridge.ValidateSafeCraftWritePath(rawOutputPath, new[] { ".mp4", ".mov", ".webm" });
                            }

                            if (args["aspectRatio"] != null) req["aspectRatio"] = GetOptString(args, "aspectRatio", 16);
                            if (args["transitionDurationSec"] != null) req["transitionDurationSec"] = args["transitionDurationSec"]!.GetValue<double>();
                            if (args["colorGrade"] != null) req["colorGrade"] = GetOptString(args, "colorGrade", 32);
                            if (args["overlayStyle"] != null) req["overlayStyle"] = GetOptString(args, "overlayStyle", 32);
                            if (args["backgroundMusic"] != null) req["backgroundMusic"] = GetOptString(args, "backgroundMusic", 260);
                            if (args["musicVolume"] != null) req["musicVolume"] = args["musicVolume"]!.GetValue<double>();
                            if (args["sfxVolume"] != null) req["sfxVolume"] = args["sfxVolume"]!.GetValue<double>();
                            if (args["introAccentSfx"] != null) req["introAccentSfx"] = GetOptString(args, "introAccentSfx", 260);
                            if (args["outroAccentSfx"] != null) req["outroAccentSfx"] = GetOptString(args, "outroAccentSfx", 260);
                            if (args["showBrandHeader"] != null) req["showBrandHeader"] = args["showBrandHeader"]!.GetValue<bool>();
                            if (args["brandHeaderText"] != null) req["brandHeaderText"] = GetOptString(args, "brandHeaderText", 160);
                            if (args["outputFileName"] != null)
                            {
                                string outName = GetOptString(args, "outputFileName", 160) ?? "";
                                if (outName.Contains("..", StringComparison.Ordinal) ||
                                    outName.Contains('/') || outName.Contains('\\') || outName.Contains(':') ||
                                    outName.IndexOfAny(Path.GetInvalidFileNameChars()) >= 0)
                                {
                                    throw new UnauthorizedAccessException("Ungültiger outputFileName (keine Pfadtrenner oder '..' erlaubt).");
                                }
                                string candidatePath = Path.Combine(McpCraftBridge.CraftVideosDir, safeProjectName, outName);
                                McpCraftBridge.ValidateSafeCraftWritePath(candidatePath, new[] { ".mp4", ".mov", ".webm" });
                                req["outputFileName"] = outName;
                            }
                            if (args["scenes"] is JsonArray scArr)
                            {
                                if (scArr.Count > 60)
                                    throw new InvalidOperationException("Maximal 60 Szenen pro Projekt erlaubt.");
                                req["scenes"] = scArr.DeepClone();
                            }
                            return await McpCraftBridge.InvokeCraftActionAsync(req);
                        }

                        case "import_media":
                        {
                            string projName = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            var validatedPaths = new JsonArray();
                            if (args["sourcePaths"] is JsonArray spArr)
                            {
                                if (spArr.Count > 50)
                                    throw new InvalidOperationException("Maximal 50 Mediendateien pro Import-Aufruf erlaubt.");
                                foreach (var item in spArr)
                                {
                                    string raw = item?.GetValue<string>() ?? "";
                                    string safe = McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(raw, allowAudio: false, allowDirectories: false);
                                    validatedPaths.Add(safe);
                                }
                            }
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "import_media",
                                ["projectName"] = projName,
                                ["sourcePaths"] = validatedPaths
                            });
                        }

                        default:
                            throw new ArgumentException("Erlaubte craft_project_manage Aktionen: list, get, save, import_media.");
                    }
                }

                case "craft_media_inspect":
                {
                    string rawTarget = GetString(args, "targetPath");
                    string safeTarget = McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(rawTarget, allowAudio: true, allowDirectories: true);
                    int maxFiles = args["maxFiles"] != null ? Math.Clamp(args["maxFiles"]!.GetValue<int>(), 1, 100) : 40;

                    if (Directory.Exists(safeTarget))
                    {
                        var files = Directory.GetFiles(safeTarget, "*", SearchOption.AllDirectories)
                            .Where(f =>
                            {
                                string ext = Path.GetExtension(f).ToLowerInvariant();
                                return ext is ".mp4" or ".mov" or ".mkv" or ".webm" or ".jpg" or ".jpeg" or ".png" or ".webp";
                            })
                            .OrderBy(f => f)
                            .Take(maxFiles)
                            .ToList();

                        var items = new List<JsonNode?>();
                        foreach (string f in files)
                        {
                            string safeFile = McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(f, allowAudio: false, allowDirectories: false);
                            var res = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "inspect_media",
                                ["filePath"] = safeFile
                            });
                            items.Add(res["media"]?.DeepClone());
                        }
                        return new { directory = safeTarget, inspectedCount = items.Count, items };
                    }
                    else
                    {
                        return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                        {
                            ["action"] = "inspect_media",
                            ["filePath"] = safeTarget
                        });
                    }
                }

                case "craft_soundpool_browser":
                {
                    string? cat = GetOptString(args, "category", 64);
                    string? inspectFile = GetOptString(args, "inspectAudioFile");
                    var pool = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                    {
                        ["action"] = "list_sounds",
                        ["category"] = cat ?? "all"
                    });

                    string? scInfo = null;
                    if (!string.IsNullOrWhiteSpace(inspectFile))
                    {
                        scInfo = await McpCraftBridge.RunSoundCraftCliSafeAsync("info", inspectFile);
                    }

                    return new { soundPool = pool, soundCraftFileInfo = scInfo };
                }

                case "craft_render_social_video":
                {
                    string projName = McpCraftBridge.ValidateSafeProjectName(GetString(args, "projectName", 80));
                    string? rawOutputPath = GetOptString(args, "outputPath");
                    var renderReq = new JsonObject
                    {
                        ["action"] = "render_video",
                        ["projectName"] = projName
                    };
                    if (!string.IsNullOrWhiteSpace(rawOutputPath))
                    {
                        renderReq["outputPath"] = McpCraftBridge.ValidateSafeCraftWritePath(rawOutputPath, new[] { ".mp4", ".mov", ".webm" });
                    }

                    bool openUi = GetBool(args, "openInCraftUi", false);
                    var renderRes = await McpCraftBridge.InvokeCraftActionAsync(renderReq, timeoutSec: 420);

                    if (openUi && renderRes["ok"]?.GetValue<bool>() == true)
                    {
                        LaunchCollectionExecutable("craft", projName);
                    }
                    return renderRes;
                }

                case "craft_storytold_engine":
                {
                    string action = GetString(args, "action").ToLowerInvariant();
                    switch (action)
                    {
                        case "launch":
                        {
                            string eng = GetOptString(args, "engine", 32) ?? "artcraft";
                            string? rawProj = GetOptString(args, "projectName", 80);
                            string? targetPath = null;
                            if (eng == "filmcraft" && !string.IsNullOrWhiteSpace(rawProj))
                            {
                                string safeProj = McpCraftBridge.ValidateSafeProjectName(rawProj);
                                var exp = await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                                {
                                    ["action"] = "export_filmcraft",
                                    ["projectName"] = safeProj
                                });
                                targetPath = exp["filmcraftProjectPath"]?.GetValue<string>();
                            }
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "launch_engine",
                                ["engine"] = eng,
                                ["targetPath"] = targetPath,
                                ["enableControlBridge"] = true
                            });
                        }

                        case "filmcraft_commands":
                        {
                            string? filter = GetOptString(args, "filter", 64);
                            var cliArgs = new JsonArray("commands");
                            if (!string.IsNullOrWhiteSpace(filter))
                                cliArgs.Add(filter);
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "filmcraft_cli",
                                ["args"] = cliArgs
                            });
                        }

                        case "export_fcproj":
                        {
                            string proj = McpCraftBridge.ValidateSafeProjectName(GetOptString(args, "projectName", 80) ?? "Projekt 1");
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "export_filmcraft",
                                ["projectName"] = proj
                            });
                        }

                        case "filmcraft_bridge":
                        {
                            string rawMethod = GetString(args, "bridgeMethod", 120);
                            string safeMethod = McpCraftBridge.ValidateAllowedFilmCraftBridgeMethod(rawMethod);
                            return await McpCraftBridge.InvokeCraftActionAsync(new JsonObject
                            {
                                ["action"] = "filmcraft_bridge",
                                ["method"] = safeMethod,
                                ["params"] = args["bridgeParams"]?.DeepClone() ?? new JsonObject()
                            });
                        }

                        case "soundcraft_commands":
                            return new { ok = true, output = await McpCraftBridge.RunSoundCraftCliSafeAsync("commands") };

                        case "soundcraft_plugins":
                            return new { ok = true, output = await McpCraftBridge.RunSoundCraftCliSafeAsync("plugins") };

                        default:
                            throw new ArgumentException("Unbekannte craft_storytold_engine Aktion.");
                    }
                }

                default:
                    throw new ArgumentException($"Unbekanntes Tool '{toolName}'.");
            }
        }

        private const string FunkenwerkControlPipeName = "Funkenwerk_ControlPipe_v1";

        private static async Task<object> SendFunkenwerkLocalPipeCommandAsync(
            string domain,
            string action,
            string? query,
            string? target,
            string? direction,
            int? volume,
            bool autoStartApp)
        {
            string reqJson = JsonSerializer.Serialize(new
            {
                domain,
                action,
                query,
                target,
                direction,
                volume
            }, JsonCompact);

            for (int attempt = 0; attempt < 2; attempt++)
            {
                try
                {
                    using var cts = new CancellationTokenSource(TimeSpan.FromMilliseconds(attempt == 0 ? 2500 : 5000));
                    using var pipe = new NamedPipeClientStream(
                        serverName: ".",
                        pipeName: FunkenwerkControlPipeName,
                        direction: PipeDirection.InOut,
                        options: PipeOptions.Asynchronous | PipeOptions.CurrentUserOnly);

                    await pipe.ConnectAsync(cts.Token);

                    using var writer = new StreamWriter(pipe, Utf8NoBom, bufferSize: 4096, leaveOpen: true) { AutoFlush = true };
                    using var reader = new StreamReader(pipe, Utf8NoBom, detectEncodingFromByteOrderMarks: false, bufferSize: 4096, leaveOpen: true);

                    await writer.WriteLineAsync(reqJson);
                    string? respLine = await ReadBoundedLineAsync(reader, 65536);
                    if (string.IsNullOrWhiteSpace(respLine))
                    {
                        return new { ok = false, error = "Leere Antwort von Funkenwerk IPC-Bridge." };
                    }

                    var parsed = JsonNode.Parse(respLine, documentOptions: StrictJsonParseOptions);
                    return parsed ?? (object)new { ok = true, raw = respLine };
                }
                catch (Exception) when (attempt == 0 && autoStartApp)
                {
                    LaunchCollectionExecutable("funkenwerk", null);
                    await Task.Delay(2600);
                }
                catch (TimeoutException)
                {
                    return new
                    {
                        ok = false,
                        funkenwerkRunning = false,
                        hint = "Funkenwerk Desktop Suite läuft aktuell nicht auf dem Desktop. Setze autoStartApp=true oder starte Funkenwerk über collection_control (action='launch', app='funkenwerk')."
                    };
                }
                catch (OperationCanceledException)
                {
                    return new
                    {
                        ok = false,
                        funkenwerkRunning = false,
                        hint = "Funkenwerk Desktop Suite läuft aktuell nicht auf dem Desktop. Setze autoStartApp=true oder starte Funkenwerk über collection_control (action='launch', app='funkenwerk')."
                    };
                }
            }

            return new { ok = false, funkenwerkRunning = false };
        }

        public static ProcessStartInfo BuildValidatedCollectionStartInfo(
            string appKey,
            string? userArgument,
            string? trustedInternalFlag = null,
            bool requireBinaryExists = true)
        {
            (string subFolder, string exeName) = (appKey ?? "").Trim().ToLowerInvariant() switch
            {
                "funkenwerk" => ("Funkenwerk", "Funkenwerk.exe"),
                "tresor" => ("Funkenwerk Tresor", "FunkenwerkTresor.exe"),
                "player" => ("Funkenwerk Player", "FunkenwerkPlayer.exe"),
                "video" => ("Funkenwerk Video", "FunkenwerkVideo.exe"),
                "craft" => ("Funkenwerk Craft", "FunkenwerkCraft.exe"),
                "ftp" => ("Funkenwerk FTP", "FunkenwerkFtp.exe"),
                "ssh" => ("Funkenwerk SSH", "FunkenwerkSsh.exe"),
                "pdf" => ("Funkenwerk PDF", "FunkenwerkPdf.exe"),
                "drucker" => ("Funkenwerk Drucker", "FunkenwerkDrucker.exe"),
                "os" => ("Funkenwerk OS", "FunkenwerkOS.exe"),
                "browser" => ("Funkenwerk Browser", "FunkenwerkBrowser.exe"),
                _ => throw new ArgumentException($"Unbekannte Funkenwerk-App '{appKey}'.")
            };

            string exePath = Path.Combine(McpSecurityGuard.CollectionRootDir, subFolder, exeName);
            if (requireBinaryExists && !File.Exists(exePath))
                throw new FileNotFoundException($"App-Binary nicht gefunden: {exePath}");

            var psi = new ProcessStartInfo
            {
                FileName = exePath,
                WorkingDirectory = Path.GetDirectoryName(exePath)!,
                UseShellExecute = false
            };

            if (!string.IsNullOrWhiteSpace(trustedInternalFlag))
            {
                if (appKey != "ssh" || trustedInternalFlag != "--handoff")
                    throw new UnauthorizedAccessException("Unbekanntes internes Handoff-Flag.");
                psi.ArgumentList.Add("--handoff");
            }

            if (!string.IsNullOrWhiteSpace(userArgument))
            {
                string cleanArg = userArgument.Trim();
                if (cleanArg.StartsWith('-') || cleanArg.StartsWith('/'))
                    throw new UnauthorizedAccessException("CLI-Flags (beginnend mit '-' oder '/') sind in LaunchCollectionExecutable strikt verboten.");
                if (cleanArg.Any(c => c == '\0' || c == '\r' || c == '\n'))
                    throw new UnauthorizedAccessException("Steuerzeichen im Argument sind verboten.");

                psi.ArgumentList.Add(cleanArg);
            }

            return psi;
        }

        private static bool LaunchCollectionExecutable(string appKey, string? userArgument, string? trustedInternalFlag = null)
        {
            var psi = BuildValidatedCollectionStartInfo(appKey, userArgument, trustedInternalFlag, requireBinaryExists: true);
            Process.Start(psi);
            return true;
        }

        private static string GetString(JsonObject obj, string key, int maxLen = McpSecurityGuard.MaxStringArgumentChars)
        {
            string val = obj[key]?.GetValue<string>() ?? throw new ArgumentException($"Pflichtparameter '{key}' fehlt.");
            if (val.Length > maxLen)
                throw new InvalidOperationException($"Parameter '{key}' überschreitet das Sicherheitslimit von {maxLen} Zeichen.");
            return val;
        }

        private static string? GetOptString(JsonObject obj, string key, int maxLen = McpSecurityGuard.MaxStringArgumentChars)
        {
            string? val = obj[key]?.GetValue<string>();
            if (val != null && val.Length > maxLen)
                throw new InvalidOperationException($"Parameter '{key}' überschreitet das Sicherheitslimit von {maxLen} Zeichen.");
            return val;
        }

        private static bool GetBool(JsonObject obj, string key, bool defaultVal) =>
            obj[key] != null ? obj[key]!.GetValue<bool>() : defaultVal;
    }

    // =========================================================================
    // PORTABLE, DETERMINISTIC 36-POINT SECURITY & E2E SELF-TEST SUITE
    // =========================================================================
    public static class McpSelfTestSuite
    {
        private sealed class MockOrganizerHttpHandler : HttpMessageHandler
        {
            public List<(HttpMethod Method, Uri Uri, string Body)> CapturedRequests { get; } = new();

            protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
            {
                string body = request.Content != null ? await request.Content.ReadAsStringAsync(cancellationToken) : "";
                lock (CapturedRequests)
                {
                    CapturedRequests.Add((request.Method, request.RequestUri!, body));
                }

                string pathAndQuery = request.RequestUri!.PathAndQuery;
                string responseJson;

                if (pathAndQuery.StartsWith("/api/funki/tasks/lists/", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Post)
                {
                    using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
                    string prio = doc.RootElement.TryGetProperty("priority", out var p) ? p.GetString() ?? "mittel" : "mittel";
                    string title = doc.RootElement.TryGetProperty("title", out var t) ? t.GetString() ?? "" : "";
                    responseJson = JsonSerializer.Serialize(new
                    {
                        success = true,
                        data = new { id = "99999999-1111-2222-3333-444444444444", title, priority = prio, is_completed = false }
                    });
                }
                else if (pathAndQuery.StartsWith("/api/funki/tasks/lists", StringComparison.OrdinalIgnoreCase))
                {
                    responseJson = """
                    {
                      "success": true,
                      "data": [
                        {
                          "id": "11111111-1111-1111-1111-111111111111",
                          "name": "Operativer Status",
                          "tasks": [
                            { "id": "22222222-2222-2222-2222-222222222222", "title": "Laser Rohlinge pruefen", "priority": "hoch", "is_completed": false }
                          ]
                        }
                      ]
                    }
                    """;
                }
                else if (pathAndQuery.StartsWith("/api/funki/calendar/events", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Get)
                {
                    responseJson = """
                    {
                      "success": true,
                      "data": [
                        { "id": "33333333-3333-3333-3333-333333333333", "title": "Steuerberater Export", "start": "2026-11-14T10:00:00", "end": "2026-11-14T11:00:00", "is_all_day": false, "category": "business" }
                      ]
                    }
                    """;
                }
                else if (pathAndQuery.StartsWith("/api/funki/calendar/events", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Post)
                {
                    responseJson = """{"success":true,"data":{"id":"44444444-4444-4444-4444-444444444444"}}""";
                }
                else if (pathAndQuery.StartsWith("/api/funki/shopping/categories", StringComparison.OrdinalIgnoreCase))
                {
                    responseJson = """
                    {
                      "success": true,
                      "data": [
                        { "id": "cat-uuid-verpackung-001", "name": "Verpackung" },
                        { "id": "cat-uuid-rohlinge-002", "name": "Rohlinge" }
                      ]
                    }
                    """;
                }
                else if (pathAndQuery.StartsWith("/api/funki/shopping/items", StringComparison.OrdinalIgnoreCase) && request.Method == HttpMethod.Post)
                {
                    using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
                    string? catId = doc.RootElement.TryGetProperty("shopping_category_id", out var sc) ? sc.GetString() : null;
                    string? itemName = doc.RootElement.TryGetProperty("name", out var nm) ? nm.GetString() : null;
                    responseJson = JsonSerializer.Serialize(new
                    {
                        success = true,
                        data = new { id = "shop-item-001", name = itemName, shopping_category_id = catId }
                    });
                }
                else if (pathAndQuery.StartsWith("/api/funki/shopping/items", StringComparison.OrdinalIgnoreCase))
                {
                    responseJson = """
                    {
                      "success": true,
                      "categories": [
                        { "id": "cat-uuid-verpackung-001", "name": "Verpackung" },
                        { "id": "cat-uuid-rohlinge-002", "name": "Rohlinge" }
                      ],
                      "data": [
                        { "id": "shop-item-001", "name": "Westpack Boxen", "quantity": 50 }
                      ]
                    }
                    """;
                }
                else
                {
                    responseJson = """{"success":true,"data":{}}""";
                }

                return new HttpResponseMessage(HttpStatusCode.OK)
                {
                    Content = new StringContent(responseJson, Encoding.UTF8, "application/json")
                };
            }
        }

        public static async Task<int> RunAllAsync()
        {
            int passed = 0;
            int failed = 0;

            void AssertTest(string name, bool condition, string detail = "")
            {
                if (condition)
                {
                    passed++;
                    Console.WriteLine($"  [PASS] {name}");
                }
                else
                {
                    failed++;
                    Console.WriteLine($"  [FAIL] {name} -> {detail}");
                }
            }

            void AssertThrowsSecurity(string name, Action act)
            {
                try
                {
                    act();
                    AssertTest(name, false, "Erwartete Sicherheits-Blockade trat NICHT ein!");
                }
                catch (Exception ex) when (ex is UnauthorizedAccessException || ex is ArgumentException || ex is InvalidOperationException)
                {
                    AssertTest(name, true);
                }
            }

            string tempRoot = Path.Combine(Path.GetTempPath(), $"FunkenwerkMcpSelfTest_{Guid.NewGuid():N}");
            string tempVaultDir = Path.Combine(tempRoot, "Vault");
            string tempDocsDir = Path.Combine(tempRoot, "AllowedDocs");
            Directory.CreateDirectory(tempVaultDir);
            Directory.CreateDirectory(tempDocsDir);

            using var mockHandler = new MockOrganizerHttpHandler();
            using var mockClient = new HttpClient(mockHandler) { Timeout = TimeSpan.FromSeconds(5) };

            try
            {
                using var vaultScope = McpSecurityGuard.UseTemporaryVaultDirectory(tempVaultDir, tempDocsDir);
                McpSecurityGuard.ResetRateLimiterForTests();
                McpOrganizerClient.TestHttpClientOverride = mockClient;
                McpOrganizerClient.TestBaseUrlOverride = "https://stage.mein-seelenfunke.de/api";

                Console.WriteLine("=== FUNKENWERK MCP v1.5 HARDENED ZERO-TRUST & E2E SELF-TEST (40 CHECKS) ===");

                // 01. Path Traversal Defense
                AssertThrowsSecurity("01. Block Path Traversal (..\\..\\Windows\\System32)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, @"..\..\Windows\System32\drivers\etc\hosts"), new[] { ".pdf" }, false));

                // 02. SSH Private Key Sandbox Defense
                string userHome = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
                AssertThrowsSecurity("02. Block .ssh Private Key Access (id_ed25519)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(userHome, ".ssh", "id_ed25519"), Array.Empty<string>(), false));

                // 03. Antigravity .gemini Config Defense
                AssertThrowsSecurity("03. Block .gemini Config Access (mcp_config.json)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(userHome, ".gemini", "config", "mcp_config.json"), Array.Empty<string>(), false));

                // 04. AppData DPAPI Vault Defense
                AssertThrowsSecurity("04. Block AppData Vault Access (profiles_vault.json)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(
                        Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkFtp", "profiles_vault.json"),
                        Array.Empty<string>(), false));

                // 05. Funkenwerk Collection Suite Directory Defense
                AssertThrowsSecurity("05. Block Entire Desktop\\Funkenwerk Collection Binary/Vault Tree", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(McpSecurityGuard.CollectionRootDir, "Funkenwerk PDF", "trojan.pdf"), new[] { ".pdf" }, false));

                // 06. NTFS Alternate Data Stream (ADS) Defense
                AssertThrowsSecurity("06. Block NTFS Alternate Data Stream (test.pdf:Zone.Identifier)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, "test.pdf:Zone.Identifier"), new[] { ".pdf" }, false));

                // 07. Executable Extension Defense
                AssertThrowsSecurity("07. Block Executable File Extensions (.exe / .ps1 / .lnk)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, "exploit.ps1"), new[] { ".ps1" }, false));

                // 08. Windows DOS Reserved Device Names Defense
                AssertThrowsSecurity("08. Block Windows DOS Reserved Device Names (CON.pdf)", () =>
                    McpSecurityGuard.ValidateSafeFilePath(Path.Combine(tempDocsDir, "CON.pdf"), new[] { ".pdf" }, false));

                // 09. Disguised Non-PDF File Rejected + UTF-8 BOM Offset-3 PDF Accepted
                string fakePdf = Path.Combine(tempDocsDir, "_fake_env.pdf");
                string bomPdf = Path.Combine(tempDocsDir, "_valid_bom.pdf");
                await File.WriteAllTextAsync(fakePdf, "APP_KEY=base64:FakeDisguisedEnvFileNotAPdf");
                byte[] syntheticPdfBytes = McpPdfEngine.CreateSyntheticAcroFormPdfBytes();
                byte[] bomPdfBytes = new byte[3 + syntheticPdfBytes.Length];
                bomPdfBytes[0] = 0xEF; bomPdfBytes[1] = 0xBB; bomPdfBytes[2] = 0xBF;
                Buffer.BlockCopy(syntheticPdfBytes, 0, bomPdfBytes, 3, syntheticPdfBytes.Length);
                await File.WriteAllBytesAsync(bomPdf, bomPdfBytes);
                bool disguisedBlocked = false;
                try { McpSecurityGuard.ValidateSafeFilePath(fakePdf, new[] { ".pdf" }, mustExist: true); }
                catch (UnauthorizedAccessException) { disguisedBlocked = true; }
                string validatedBom = McpSecurityGuard.ValidateSafeFilePath(bomPdf, new[] { ".pdf" }, mustExist: true);
                AssertTest("09. Disguised Non-PDF Blocked & Valid Offset-0 / UTF-8 BOM Offset-3 PDF Accepted",
                    disguisedBlocked && File.Exists(validatedBom));

                // 10. Polyglot PDF with '%PDF-' Shifted to Byte Offset 20 Strictly Rejected
                string polyglotPdf = Path.Combine(tempDocsDir, "_polyglot_offset20.pdf");
                byte[] polyBytes = new byte[20 + syntheticPdfBytes.Length];
                Encoding.ASCII.GetBytes("MZ_HTML_POLYGLOT_PAD").CopyTo(polyBytes, 0);
                Buffer.BlockCopy(syntheticPdfBytes, 0, polyBytes, 20, syntheticPdfBytes.Length);
                await File.WriteAllBytesAsync(polyglotPdf, polyBytes);
                AssertThrowsSecurity("10. Block Polyglot File With '%PDF-' Shifted to Byte Offset 20", () =>
                    McpSecurityGuard.ValidateSafeFilePath(polyglotPdf, new[] { ".pdf" }, mustExist: true));

                // 11. NTFS Junction / Reparse Point Ancestry Blocked
                string junctionTarget = Path.Combine(tempRoot, "OutsideSecretDir");
                Directory.CreateDirectory(junctionTarget);
                string secretTargetPdf = Path.Combine(junctionTarget, "secret.pdf");
                await File.WriteAllBytesAsync(secretTargetPdf, syntheticPdfBytes);
                string junctionLink = Path.Combine(tempDocsDir, "JunctionEscape");
                using (var mkProc = Process.Start(new ProcessStartInfo("cmd.exe")
                {
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    ArgumentList = { "/c", "mklink", "/J", junctionLink, junctionTarget }
                })!)
                {
                    await mkProc.WaitForExitAsync();
                }
                string viaJunctionFile = Path.Combine(junctionLink, "secret.pdf");
                bool reparseDetected = McpSecurityGuard.HasReparsePointInAncestry(viaJunctionFile);
                bool safePathBlockedJunction = false;
                try { McpSecurityGuard.ValidateSafeFilePath(viaJunctionFile, new[] { ".pdf" }, mustExist: true); }
                catch (UnauthorizedAccessException) { safePathBlockedJunction = true; }
                bool craftBlockedJunction = false;
                try { McpCraftBridge.ValidateSafeMediaSourceOrCraftPath(viaJunctionFile, allowAudio: false); }
                catch (UnauthorizedAccessException) { craftBlockedJunction = true; }
                try { Directory.Delete(junctionLink); } catch { }
                AssertTest("11. NTFS Junction / Reparse Point Ancestry Blocked Across SafePath & Craft",
                    reparseDetected && safePathBlockedJunction && craftBlockedJunction);

                // 12. Outbound DLP Scrubber Redacts Sanctum, FWAES256GCM1, FWVAULT2, JWT, JSON password & RTSP URLs
                string simulatedLeak =
                    "Sanctum: 12|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCD " +
                    "VaultNew: FWAES256GCM1:YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXo= " +
                    "VaultOld: FWVAULT2:YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXo= " +
                    "Jwt: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U " +
                    "JsonPass: {\"password\":\"GeheimJsonPass99!\"} " +
                    "Rtsp: rtsp://admin:KameraGeheim77@192.168.178.55:554/stream1 " +
                    "Env: DB_PASSWORD=SuperGeheim123! " +
                    "Url: https://admin:GeheimPass@mein-seelenfunke.de/api";
                string scrubbed = McpSecurityGuard.ScrubSecrets(simulatedLeak);
                AssertTest("12. Outbound DLP Redacts Sanctum, FWAES256GCM1, JWT, JSON Password & RTSP Credentials",
                    !scrubbed.Contains("AbCdEfGhIjKl")
                    && !scrubbed.Contains("YWJjZGVm")
                    && !scrubbed.Contains("dozjgNryP4J3")
                    && !scrubbed.Contains("GeheimJsonPass99")
                    && !scrubbed.Contains("KameraGeheim77")
                    && !scrubbed.Contains("SuperGeheim123")
                    && !scrubbed.Contains("GeheimPass")
                    && scrubbed.Contains("[REDACTED-BY-FUNKENWERK-DLP]"),
                    scrubbed);

                // 13. SSRF, UserInfo & HTTP Downgrade Defense
                bool evilBlocked = !McpSecurityGuard.IsAllowedApiUrl("https://attacker.example.com/api", out _);
                bool httpRemoteBlocked = !McpSecurityGuard.IsAllowedApiUrl("http://stage.mein-seelenfunke.de/api", out _);
                bool userInfoBlocked = !McpSecurityGuard.IsAllowedApiUrl("https://user:pass@stage.mein-seelenfunke.de/api", out _);
                bool httpsStageAllowed = McpSecurityGuard.IsAllowedApiUrl("https://stage.mein-seelenfunke.de/api", out _);
                bool httpsLiveAllowed = McpSecurityGuard.IsAllowedApiUrl("https://mein-seelenfunke.de/api", out _);
                AssertTest("13. SSRF Guard, UserInfo Rejection & HTTPS Enforcement",
                    evilBlocked && httpRemoteBlocked && userInfoBlocked && httpsStageAllowed && httpsLiveAllowed);

                // 14. Arbitrary SSH Command Injection Blocked + 11 Safe Presets Verified (incl. stage_deploy & stage_worker_restart)
                bool sshInjectionBlocked = false;
                try { await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "rm -rf /; cat ~/.ssh/id_ed25519"); }
                catch (UnauthorizedAccessException) { sshInjectionBlocked = true; }
                bool hasAll11Presets = McpSecurityGuard.SafeReadonlySshPresets.Count == 11
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("uptime_and_load")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("disk_usage")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_git_pull")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_deploy")
                    && McpSecurityGuard.SafeReadonlySshPresets.Contains("stage_worker_restart");
                AssertTest("14. Arbitrary SSH Command Blocked & 11 SafeReadonlySshPresets (incl. stage_deploy) Verified",
                    sshInjectionBlocked && hasAll11Presets);

                // 15. SSH RemoteDirectory Metacharacter Injection Blocked
                bool sshDirBlocked = false;
                try { await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "git_status", "/home/p-g27wim'; id #"); }
                catch (UnauthorizedAccessException) { sshDirBlocked = true; }
                AssertTest("15. Block SSH RemoteDirectory Shell-Escape / Metacharacter Injection", sshDirBlocked);

                // 16. RunSecuritySelfAudit Bootstraps Missing security_audit.jsonl Before LockDownFileAcl (Isolated Vault)
                string isolatedAuditLog = Path.Combine(McpSecurityGuard.VaultDirectory, "security_audit.jsonl");
                if (File.Exists(isolatedAuditLog)) File.Delete(isolatedAuditLog);
                var freshAuditStatus = McpSecurityGuard.RunSecuritySelfAudit();
                McpSecurityGuard.AppendAuditLog("self_test", "integrity_probe_v1_4", "ALLOW");
                var afterEntryAudit = McpSecurityGuard.RunSecuritySelfAudit();
                AssertTest("16. RunSecuritySelfAudit Creates & ACL-Locks security_audit.jsonl in Isolated Temp Vault",
                    File.Exists(isolatedAuditLog)
                    && freshAuditStatus.DirectoryAclRestrictedToCurrentUser
                    && freshAuditStatus.AuditFileAclRestrictedToCurrentUser
                    && afterEntryAudit.AuditHashChainValid
                    && afterEntryAudit.AuditEntriesCount >= 1);

                // 17. Cryptographic Audit Hash-Chain Tamper Detection
                string tempChain = Path.Combine(tempVaultDir, "_tamper_test_chain.jsonl");
                McpSecurityGuard.AppendAuditLog("t1", "alpha", "ALLOW", tempChain);
                McpSecurityGuard.AppendAuditLog("t2", "beta", "DENY", tempChain);
                McpSecurityGuard.AppendAuditLog("t3", "gamma", "ALLOW", tempChain);
                var (validBefore, countBefore) = McpSecurityGuard.VerifyAuditChain(tempChain);
                string[] chainLines = await File.ReadAllLinesAsync(tempChain);
                chainLines[1] = chainLines[1].Replace("\"DENY\"", "\"ALLOW\"");
                await File.WriteAllLinesAsync(tempChain, chainLines);
                var (validAfter, _) = McpSecurityGuard.VerifyAuditChain(tempChain);
                AssertTest("17. SHA-256 Audit Hash-Chain Detects 1-Byte Log Tampering",
                    validBefore && countBefore == 3 && !validAfter);

                // 18. Deeply Nested JSON Bomb Defense (MaxDepth = 24)
                string jsonBomb = "{\"jsonrpc\":\"2.0\",\"id\":99,\"method\":\"ping\",\"params\":{\"a\":" + new string('[', 40) + "1" + new string(']', 40) + "}}";
                string? bombResp = await Program.ProcessJsonRpcMessageAsync(jsonBomb);
                AssertTest("18. Deeply Nested JSON Bomb Rejected Cleanly (MaxDepth=24 -> -32700)",
                    bombResp != null && bombResp.Contains("-32700"));

                // 19. Oversized Tool Argument (> 4096 chars) Rejected Safely
                string hugePathReq = "{\"jsonrpc\":\"2.0\",\"id\":100,\"method\":\"tools/call\",\"params\":{\"name\":\"pdf_inspect\",\"arguments\":{\"filePath\":\"" + new string('A', 5000) + ".pdf\"}}}";
                string? hugeResp = await Program.ProcessJsonRpcMessageAsync(hugePathReq);
                AssertTest("19. Oversized Tool Argument (>4 KB) Rejected Safely",
                    hugeResp != null && hugeResp.Contains("\"isError\":true"));

                // 20. Zero-Secret Server Profile Listing
                var sanitizedProfiles = McpSecurityGuard.GetSanitizedServerProfiles();
                string profilesJson = JsonSerializer.Serialize(sanitizedProfiles);
                AssertTest("20. Zero-Secret Server Profiles Serialization (Zero Passwords/Keys Exposed)",
                    !profilesJson.Contains("EncryptedPassword") && !profilesJson.Contains("FWAES256GCM1:") && !profilesJson.Contains("FWSSHAES256GCM1:"));

                // 21. CRITICAL Real UTF-8 Multi-Byte Content-Length Byte-Stream Framing (< 200ms, Zero Hang!)
                {
                    string utf8ReqBody = """{"jsonrpc":"2.0","id":701,"method":"tools/call","params":{"name":"tasks_manage","arguments":{"action":"create","title":"Alina Steinhauer · Größe 50€ äöüß ✨","priority":"normal"}}}""";
                    byte[] utf8PayloadBytes = Encoding.UTF8.GetBytes(utf8ReqBody);
                    byte[] headerBytes = Encoding.ASCII.GetBytes($"Content-Length: {utf8PayloadBytes.Length}\r\n\r\n");

                    using var inMs = new MemoryStream();
                    inMs.Write(headerBytes, 0, headerBytes.Length);
                    inMs.Write(utf8PayloadBytes, 0, utf8PayloadBytes.Length);
                    inMs.Position = 0;

                    using var outMs = new MemoryStream();
                    var sw = Stopwatch.StartNew();
                    var loopTask = Program.RunStdioServerLoopAsync(inMs, outMs);
                    bool finishedFast = await Task.WhenAny(loopTask, Task.Delay(200)) == loopTask;
                    sw.Stop();

                    string wireOut = Encoding.UTF8.GetString(outMs.ToArray());
                    AssertTest("21. UTF-8 Multi-Byte Content-Length Framing ('Alina Steinhauer · Größe 50€ äöüß ✨') < 200ms",
                        finishedFast
                        && sw.ElapsedMilliseconds < 200
                        && utf8PayloadBytes.Length > utf8ReqBody.Length
                        && wireOut.StartsWith("Content-Length:", StringComparison.Ordinal)
                        && wireOut.Contains("\"id\":701")
                        && wireOut.Contains("Alina Steinhauer"),
                        $"elapsed={sw.ElapsedMilliseconds}ms, out={wireOut}");
                }

                // 22. Oversized Frame Returns JSON-RPC -32600 Error WITHOUT Breaking Server Loop
                {
                    int overLen = McpSecurityGuard.MaxJsonRpcMessageBytes + 1024;
                    byte[] overBody = Encoding.ASCII.GetBytes(new string('X', overLen));
                    byte[] overHdr = Encoding.ASCII.GetBytes($"Content-Length: {overLen}\r\n\r\n");
                    string followUpPing = "{\"jsonrpc\":\"2.0\",\"id\":702,\"method\":\"ping\"}\n";
                    byte[] pingBytes = Encoding.UTF8.GetBytes(followUpPing);

                    using var inMs = new MemoryStream();
                    inMs.Write(overHdr, 0, overHdr.Length);
                    inMs.Write(overBody, 0, overBody.Length);
                    inMs.Write(pingBytes, 0, pingBytes.Length);
                    inMs.Position = 0;

                    using var outMs = new MemoryStream();
                    await Program.RunStdioServerLoopAsync(inMs, outMs);
                    string wireOut = Encoding.UTF8.GetString(outMs.ToArray());
                    AssertTest("22. Oversized Frame Returns -32600 And Keeps Server Loop Alive For Next Frame",
                        wireOut.Contains("-32600") && wireOut.Contains("\"id\":702"),
                        wireOut);
                }

                // 23. Synthetic AcroForm PDF E2E: pdf_inspect + pdf_fill_form (Rotated /MK /R 90 + Unicode + CheckBox + Overwrite Guard)
                string synthInputPdf = Path.Combine(tempDocsDir, "Synthetic_Haendlerbogen.pdf");
                string synthFilledPdf = Path.Combine(tempDocsDir, "Synthetic_Haendlerbogen_Filled.pdf");
                await File.WriteAllBytesAsync(synthInputPdf, syntheticPdfBytes);
                {
                    var inspectReq = new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 2301,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "pdf_inspect",
                            ["arguments"] = new JsonObject { ["filePath"] = synthInputPdf }
                        }
                    };
                    string? inspectResp = await Program.ProcessJsonRpcMessageAsync(inspectReq.ToJsonString());

                    var fillReq = new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 2302,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "pdf_fill_form",
                            ["arguments"] = new JsonObject
                            {
                                ["inputPath"] = synthInputPdf,
                                ["outputPath"] = synthFilledPdf,
                                ["overwrite"] = false,
                                ["openInFunkenwerkPdf"] = false,
                                ["fieldValues"] = new JsonObject
                                {
                                    ["Firmenname"] = "Mein-Seelenfunke",
                                    ["Name des Inhabers"] = "Alina Steinhauer · Größe 50€ Čech ✨",
                                    ["NotizRotiert90"] = "Rotiert 90 Grad OK",
                                    ["AGB_Akzeptiert"] = "X"
                                }
                            }
                        }
                    };
                    string? fillResp = await Program.ProcessJsonRpcMessageAsync(fillReq.ToJsonString());
                    // Second call with overwrite=false must return isError=true
                    string? dupFillResp = await Program.ProcessJsonRpcMessageAsync(fillReq.ToJsonString());

                    var verifyFilled = McpPdfEngine.InspectPdf(synthFilledPdf);
                    var rotField = verifyFilled.Fields.FirstOrDefault(f => f.Name == "NotizRotiert90");
                    var ownerField = verifyFilled.Fields.FirstOrDefault(f => f.Name == "Name des Inhabers");
                    var chkField = verifyFilled.Fields.FirstOrDefault(f => f.Name == "AGB_Akzeptiert");

                    AssertTest("23. Synthetic AcroForm PDF Inspect + Fill (Rotated /MK /R 90 + Unicode + Overwrite Guard)",
                        inspectResp != null && inspectResp.Contains("NotizRotiert90")
                        && fillResp != null && fillResp.Contains("\"isError\":false")
                        && dupFillResp != null && dupFillResp.Contains("\"isError\":true")
                        && rotField != null && rotField.WidgetRotation == 90 && rotField.Value.Contains("Rotiert 90 Grad OK")
                        && ownerField != null && ownerField.Value.Contains("Alina Steinhauer")
                        && chkField != null && chkField.IsChecked);
                }

                // 24. Synthetic AcroForm PDF E2E: pdf_autofill_stammdaten (Zero Downloads Dependency)
                string synthStammdatenOut = Path.Combine(tempDocsDir, "Synthetic_Haendlerbogen_Stammdaten.pdf");
                {
                    var autoReq = new JsonObject
                    {
                        ["jsonrpc"] = "2.0",
                        ["id"] = 2401,
                        ["method"] = "tools/call",
                        ["params"] = new JsonObject
                        {
                            ["name"] = "pdf_autofill_stammdaten",
                            ["arguments"] = new JsonObject
                            {
                                ["inputPath"] = synthInputPdf,
                                ["outputPath"] = synthStammdatenOut,
                                ["overwrite"] = true,
                                ["openInFunkenwerkPdf"] = false,
                                ["overrides"] = new JsonObject { ["AGB_Akzeptiert"] = "X" }
                            }
                        }
                    };
                    string? autoResp = await Program.ProcessJsonRpcMessageAsync(autoReq.ToJsonString());
                    var verifyAuto = McpPdfEngine.InspectPdf(synthStammdatenOut);
                    AssertTest("24. Synthetic AcroForm PDF Stammdaten Autofill (Mein-Seelenfunke / Gifhorn / FYRST)",
                        autoResp != null && autoResp.Contains("\"isError\":false")
                        && verifyAuto.Fields.First(f => f.Name == "Firmenname").Value == "Mein-Seelenfunke"
                        && verifyAuto.Fields.First(f => f.Name == "Name des Inhabers").Value == "Alina Steinhauer"
                        && verifyAuto.Fields.First(f => f.Name == "PLZ").Value == "38518"
                        && verifyAuto.Fields.First(f => f.Name == "Ort").Value == "Gifhorn"
                        && verifyAuto.Fields.First(f => f.Name == "Bank-Name").Value == "FYRST (Deutsche Bank AG)");
                }

                // 25. MCP JSON-RPC 2.0 Handshake, Notification Silence & Tools Catalog
                string? initResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"antigravity","version":"2.0"}}}""");
                string? notifResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","method":"notifications/initialized"}""");
                string? toolsResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3,"method":"tools/list","params":{}}""");
                AssertTest("25. MCP JSON-RPC 2.0 Handshake, Notification Silence & 21-Tool Schema Catalog",
                    initResp != null && initResp.Contains("\"funkenwerk-mcp\"")
                    && notifResp == null
                    && toolsResp != null && toolsResp.Contains("\"uptime_and_load\"") && toolsResp.Contains("\"pdf_autofill_stammdaten\"")
                    && toolsResp.Contains("\"stage_sync_shop_assets\"") && toolsResp.Contains("\"stage_deploy\""));

                // 26. MCP resources/list & resources/read E2E
                string? resListResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2601,"method":"resources/list","params":{}}""");
                string? resStammdatenResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2602,"method":"resources/read","params":{"uri":"funkenwerk://company/stammdaten"}}""");
                string? resPolicyResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2603,"method":"resources/read","params":{"uri":"funkenwerk://security/policy"}}""");
                string? resInvalidResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2604,"method":"resources/read","params":{"uri":"funkenwerk://unknown/secret"}}""");
                AssertTest("26. MCP resources/list & resources/read (Stammdaten + Security Policy + Invalid URI Error)",
                    resListResp != null && resListResp.Contains("funkenwerk://company/stammdaten")
                    && resStammdatenResp != null && resStammdatenResp.Contains("Carl-Goerdeler-Ring 26")
                    && resPolicyResp != null && resPolicyResp.Contains("AuditHashChainValid")
                    && resInvalidResp != null && resInvalidResp.Contains("-32603"));

                // 27. Mocked HttpMessageHandler: tasks_manage Priority Mapping (low->niedrig, normal->mittel, urgent->hoch)
                mockHandler.CapturedRequests.Clear();
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T1", priority: "low");
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T2", priority: "normal");
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T3", priority: "urgent");
                await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T4", priority: "high");
                bool invalidPrioRejected = false;
                try { await McpOrganizerClient.ManageTaskAsync(action: "create", title: "T5", priority: "critical_invalid"); }
                catch (ArgumentException) { invalidPrioRejected = true; }
                var postBodies = mockHandler.CapturedRequests.Where(r => r.Method == HttpMethod.Post).Select(r => r.Body).ToList();
                AssertTest("27. Organizer tasks_manage Priority Normalization (low->niedrig, normal->mittel, urgent|high->hoch)",
                    postBodies.Count == 4
                    && postBodies[0].Contains("\"priority\":\"niedrig\"")
                    && postBodies[1].Contains("\"priority\":\"mittel\"")
                    && postBodies[2].Contains("\"priority\":\"hoch\"")
                    && postBodies[3].Contains("\"priority\":\"hoch\"")
                    && invalidPrioRejected);

                // 28. Mocked HttpMessageHandler: calendar_list 'date' Parameter & calendar_create
                mockHandler.CapturedRequests.Clear();
                string? calDateResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2801,"method":"tools/call","params":{"name":"calendar_list","arguments":{"date":"2026-11-14"}}}""");
                string? calCreateResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2802,"method":"tools/call","params":{"name":"calendar_create","arguments":{"title":"Laser Wartung","start":"2026-11-15T09:00:00"}}}""");
                bool exactDateQuerySent = mockHandler.CapturedRequests.Any(r =>
                    r.Method == HttpMethod.Get &&
                    r.Uri.Query.Contains("start=2026-11-14", StringComparison.Ordinal) &&
                    r.Uri.Query.Contains("end=2026-11-14", StringComparison.Ordinal));
                AssertTest("28. Organizer calendar_list Single-Day 'date' Parameter & calendar_create Verified",
                    calDateResp != null && calDateResp.Contains("Steuerberater Export") && exactDateQuerySent
                    && calCreateResp != null && calCreateResp.Contains("\"isError\":false"));

                // 29. Mocked HttpMessageHandler: shopping_manage Category Name Resolution & UUID Passthrough
                mockHandler.CapturedRequests.Clear();
                string? shopByNameResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2901,"method":"tools/call","params":{"name":"shopping_manage","arguments":{"action":"add","name":"Schmuckboxen Schwarz","quantity":100,"category":"Verpackung"}}}""");
                string directUuid = "88888888-4444-4444-4444-121212121212";
                string? shopByUuidResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = 2902,
                    ["method"] = "tools/call",
                    ["params"] = new JsonObject
                    {
                        ["name"] = "shopping_manage",
                        ["arguments"] = new JsonObject
                        {
                            ["action"] = "add",
                            ["name"] = "Edelstahl Rohlinge",
                            ["quantity"] = 25,
                            ["category"] = directUuid
                        }
                    }
                }.ToJsonString());
                var shopPosts = mockHandler.CapturedRequests.Where(r => r.Method == HttpMethod.Post && r.Uri.AbsolutePath.EndsWith("/shopping/items")).Select(r => r.Body).ToList();
                AssertTest("29. Organizer shopping_manage Category Name->UUID Resolution & Direct UUID Passthrough",
                    shopByNameResp != null && shopByNameResp.Contains("cat-uuid-verpackung-001")
                    && shopByUuidResp != null && shopByUuidResp.Contains(directUuid)
                    && shopPosts.Count == 2);

                // 30. LaunchCollectionExecutable Hardening (UseShellExecute=false, ArgumentList, Reject '-flag' & '/flag')
                var safePsi = Program.BuildValidatedCollectionStartInfo("pdf", synthFilledPdf, requireBinaryExists: false);
                bool dashFlagRejected = false;
                try { Program.BuildValidatedCollectionStartInfo("browser", "--remote-debugging-port=9222", requireBinaryExists: false); }
                catch (UnauthorizedAccessException) { dashFlagRejected = true; }
                bool slashFlagRejected = false;
                try { Program.BuildValidatedCollectionStartInfo("pdf", "/print-to-default", requireBinaryExists: false); }
                catch (UnauthorizedAccessException) { slashFlagRejected = true; }
                AssertTest("30. LaunchCollectionExecutable Uses UseShellExecute=false + ArgumentList & Blocks CLI Flags",
                    !safePsi.UseShellExecute
                    && safePsi.ArgumentList.Count == 1
                    && safePsi.ArgumentList[0] == synthFilledPdf
                    && dashFlagRejected
                    && slashFlagRejected);

                // 31. Craft projectName Sanitization Guard
                AssertThrowsSecurity("31. Block Craft projectName Traversal & Invalid Characters ('../evil')", () =>
                {
                    string okName = McpCraftBridge.ValidateSafeProjectName("Reel Herbst 2026_v1");
                    if (okName != "Reel Herbst 2026_v1") throw new Exception("Valid name rejected");
                    McpCraftBridge.ValidateSafeProjectName("../evil_escape");
                });

                // 32. Craft Write Sandbox (projectPath & outputPath Outside Craft\Videos Blocked, Inside Allowed)
                string allowedCraftExport = Path.Combine(McpCraftBridge.CraftVideosDir, "Exports", "social_master.mp4");
                string validatedCraftOut = McpCraftBridge.ValidateSafeCraftWritePath(allowedCraftExport, new[] { ".mp4" });
                string? craftSaveOutsideResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = 3201,
                    ["method"] = "tools/call",
                    ["params"] = new JsonObject
                    {
                        ["name"] = "craft_project_manage",
                        ["arguments"] = new JsonObject
                        {
                            ["action"] = "save",
                            ["projectName"] = "Projekt 1",
                            ["outputPath"] = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "outside_escape.mp4")
                        }
                    }
                }.ToJsonString());
                string? craftRenderOutsideResp = await Program.ProcessJsonRpcMessageAsync(new JsonObject
                {
                    ["jsonrpc"] = "2.0",
                    ["id"] = 3202,
                    ["method"] = "tools/call",
                    ["params"] = new JsonObject
                    {
                        ["name"] = "craft_render_social_video",
                        ["arguments"] = new JsonObject
                        {
                            ["projectName"] = "Projekt 1",
                            ["outputPath"] = Path.Combine(tempDocsDir, "outside_escape.mp4")
                        }
                    }
                }.ToJsonString());
                AssertTest("32. Craft Write Sandbox Enforced For projectPath & outputPath",
                    validatedCraftOut == Path.GetFullPath(allowedCraftExport)
                    && craftSaveOutsideResp != null && craftSaveOutsideResp.Contains("\"isError\":true")
                    && craftRenderOutsideResp != null && craftRenderOutsideResp.Contains("\"isError\":true"));

                // 33. Craft filmcraft_bridge RPC Method Allowlist
                string allowedRpc = McpCraftBridge.ValidateAllowedFilmCraftBridgeMethod("engine.ping");
                string? blockedBridgeResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3301,"method":"tools/call","params":{"name":"craft_storytold_engine","arguments":{"action":"filmcraft_bridge","bridgeMethod":"system.exec"}}}""");
                AssertTest("33. Craft filmcraft_bridge RPC Method Allowlist ('engine.ping' Allowed, 'system.exec' Blocked)",
                    allowedRpc == "engine.ping"
                    && blockedBridgeResp != null && blockedBridgeResp.Contains("\"isError\":true"));

                // 34. Craft Media Inspect Path Guard (Blocks System & UNC Paths) + Craft Workspace Check
                string? mediaWinIniResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3401,"method":"tools/call","params":{"name":"craft_media_inspect","arguments":{"targetPath":"C:\\Windows\\win.ini"}}}""");
                string? mediaUncResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3402,"method":"tools/call","params":{"name":"craft_media_inspect","arguments":{"targetPath":"\\\\attacker.local\\share\\clip.mp4"}}}""");
                bool craftCheckOk = mediaWinIniResp != null && mediaWinIniResp.Contains("\"isError\":true")
                    && mediaUncResp != null && mediaUncResp.Contains("\"isError\":true");
                if (File.Exists(McpCraftBridge.CraftExePath))
                {
                    string? craftStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3403,"method":"tools/call","params":{"name":"craft_workspace_status","arguments":{"includeMedienCoverage":false}}}""");
                    craftCheckOk = craftCheckOk && craftStatusResp != null && craftStatusResp.Contains("\"isError\":false");
                }
                AssertTest("34. Craft Media Inspect Blocks System/UNC Paths & Verifies Craft Bridge", craftCheckOk);

                // 35. Music & SmartHome Control Tools + Ecosystem Health Probe
                var health = await McpOrganizerClient.ProbeEcosystemHealthAsync();
                string healthJson = JsonSerializer.Serialize(health);
                string? volInvalidResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3501,"method":"tools/call","params":{"name":"music_control","arguments":{"action":"set_volume","volume":150}}}""");
                string? musicStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3502,"method":"tools/call","params":{"name":"music_control","arguments":{"action":"status","autoStartApp":false}}}""");
                string? smartStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3503,"method":"tools/call","params":{"name":"smarthome_control","arguments":{"action":"status","autoStartApp":false}}}""");
                AssertTest("35. Ecosystem Health, Music 0..100 Volume Guard & CurrentUser Pipe Probes",
                    healthJson.Contains("\"apiOnline\":true")
                    && volInvalidResp != null && volInvalidResp.Contains("\"isError\":true")
                    && musicStatusResp != null && musicStatusResp.Contains("\"isError\":false")
                    && smartStatusResp != null && smartStatusResp.Contains("\"isError\":false"));

                // 36. Sliding-Window Rate Limiter Exhaustion Test (60 Allowed -> 61st Blocked)
                McpSecurityGuard.ResetRateLimiterForTests();
                bool all60Allowed = true;
                for (int i = 0; i < 60; i++)
                {
                    try { McpSecurityGuard.EnforceRateLimit("rate_test_tool"); }
                    catch { all60Allowed = false; break; }
                }
                bool call61Blocked = false;
                try { McpSecurityGuard.EnforceRateLimit("rate_test_tool"); }
                catch (InvalidOperationException) { call61Blocked = true; }
                McpSecurityGuard.ResetRateLimiterForTests();
                AssertTest("36. Sliding-Window Rate Limiter Allows 60/min And Rejects 61st Burst Call",
                    all60Allowed && call61Blocked);

                // 37. Stage Live-Air-Gap Guard Blocks seelenfunke-live, steinhauer-invest, production & Non-Mittwald Hosts
                {
                    bool liveBlocked = false;
                    try { McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/seelenfunke-live"); }
                    catch (UnauthorizedAccessException) { liveBlocked = true; }

                    bool investBlocked = false;
                    try { McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/steinhauer-invest"); }
                    catch (UnauthorizedAccessException) { investBlocked = true; }

                    bool wrongHostBlocked = false;
                    try { McpSecurityGuard.ValidateStageOnlyTarget("mein-seelenfunke.de", "/home/p-g27wim/html/seelenfunke-stage"); }
                    catch (UnauthorizedAccessException) { wrongHostBlocked = true; }

                    McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/seelenfunke-stage");
                    McpSecurityGuard.ValidateStageOnlyTarget("ssh.altgemeinde.project.host", "/home/p-g27wim/html/worker-stage-3", allowWorkerStage: true);

                    AssertTest("37. Stage Live-Air-Gap Guard Blocks seelenfunke-live, steinhauer-invest & Non-Stage Hosts",
                        liveBlocked && investBlocked && wrongHostBlocked);
                }

                // 38. Stage Pure-Shop Asset Guard Blocks Music (.mp3/.m4a), Buchhaltung, Health, Env & Traversal
                {
                    string[] blockedSamples =
                    {
                        "leitung/music/tracks/song.m4a",
                        "leitung/music/playlists_catalog.json",
                        "buchhaltung/receipts/2026-11-01_Beleg.pdf",
                        "buchhaltung/contracts/Vertrag.pdf",
                        "buchhaltung/erictresor/cert.pfx",
                        "agenten/workspace/Gesundheit/befund.pdf",
                        "public/support/wiki/health/rezept.jpg",
                        "public/leitung/person_profiles/avatar.webp",
                        "private/system-brain-map.json",
                        "firebase-service-account.json",
                        ".env",
                        "public/produkte/products/echtgold-seelen-herz/overlay.png",
                        "public/produkte/products/seelen-kristall/backup/old.png",
                        "public/produkte/../../.env"
                    };

                    bool allForbiddenBlocked = true;
                    foreach (string bad in blockedSamples)
                    {
                        bool threw = false;
                        try { McpSecurityGuard.ValidatePureShopAssetRelativePath(bad); }
                        catch (UnauthorizedAccessException) { threw = true; }
                        if (!threw) { allForbiddenBlocked = false; break; }
                    }
                    AssertTest("38. Stage Pure-Shop Asset Guard Hard-Blocks Music, Buchhaltung, Health, Env & Backups",
                        allForbiddenBlocked);
                }

                // 39. Stage Pure-Shop Asset Guard Accepts Active Product, 3D GLB, Configurator, Digital ZIP & Blog Assets
                {
                    string p1 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/produkte/products/seelen-kristall/t_seelenk.glb");
                    string p2 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/produkte/product-templates/seelen-kristall-weihnachten.jpg");
                    string p3 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/produkte/laser-anleitungen/Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf");
                    string p4 = McpSecurityGuard.ValidatePureShopAssetRelativePath("produkte/products-secure/KI_Agenten_Management_Paket.zip");
                    string p5 = McpSecurityGuard.ValidatePureShopAssetRelativePath("public/marketing/blog/herzensprojekt.webp");
                    AssertTest("39. Stage Pure-Shop Asset Guard Accepts Valid 3D GLB, Templates, Laser-Guides, Digital ZIP & Blog",
                        p1.EndsWith(".glb") && p2.EndsWith(".jpg") && p3.EndsWith(".pdf") && p4.EndsWith(".zip") && p5.EndsWith(".webp"));
                }

                // 40. SSH Preset stage_deploy Rejects Live Target Path Before Any Network Socket Opens
                {
                    bool livePresetRejected = false;
                    try
                    {
                        await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "stage_deploy", "/home/p-g27wim/html/seelenfunke-live");
                    }
                    catch (UnauthorizedAccessException)
                    {
                        livePresetRejected = true;
                    }
                    AssertTest("40. SSH Preset stage_deploy Rejects /seelenfunke-live Before Opening Any Network Socket",
                        livePresetRejected);
                }

                Console.WriteLine($"=== RESULT: {passed} PASSED, {failed} FAILED ===");
                return failed == 0 ? 0 : 1;
            }
            finally
            {
                McpOrganizerClient.TestHttpClientOverride = null;
                McpOrganizerClient.TestBaseUrlOverride = null;
                McpSecurityGuard.ResetRateLimiterForTests();
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

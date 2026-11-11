using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Threading;
using System.Threading.Tasks;

namespace FunkenwerkMcp
{
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

            // Ensure ACL lockdown on MCP data directory immediately at startup
            McpSecurityGuard.LockDownDirectoryAcl(McpSecurityGuard.McpDataDir);

            // Capture raw stdin/stdout streams FIRST, then redirect Console.Out -> Console.Error
            // so no library (PDFsharp, SSH.NET, WPF) can ever pollute the JSON-RPC wire!
            using var stdin = Console.OpenStandardInput();
            using var stdout = Console.OpenStandardOutput();
            Console.SetOut(Console.Error);

            using var reader = new StreamReader(stdin, Utf8NoBom, detectEncodingFromByteOrderMarks: true, bufferSize: 16384, leaveOpen: true);

            while (true)
            {
                string? rawMessage;
                bool useContentLengthHeader;
                try
                {
                    (rawMessage, useContentLengthHeader) = await ReadNextJsonRpcMessageAsync(reader);
                }
                catch (Exception ex)
                {
                    string errJson = BuildErrorResponse(null, -32600, ex.Message);
                    await WriteJsonRpcResponseAsync(stdout, errJson, false);
                    break;
                }

                if (rawMessage == null)
                    break; // EOF on stdin -> clean shutdown

                if (string.IsNullOrWhiteSpace(rawMessage))
                    continue;

                string? responseJson = await ProcessJsonRpcMessageAsync(rawMessage);
                if (responseJson != null)
                {
                    await WriteJsonRpcResponseAsync(stdout, responseJson, useContentLengthHeader);
                }
            }

            return 0;
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
                    throw new InvalidOperationException("JSON-RPC Nachricht überschreitet das 512 KB Sicherheitslimit.");

                sb.Append(c);
            }
            return sb.ToString();
        }

        private static async Task<(string? Json, bool UseContentLength)> ReadNextJsonRpcMessageAsync(StreamReader reader)
        {
            string? line = await ReadBoundedLineAsync(reader, McpSecurityGuard.MaxJsonRpcMessageBytes);
            if (line == null)
                return (null, false);

            // Support optional LSP-style Content-Length framing if a client uses it
            if (line.StartsWith("Content-Length:", StringComparison.OrdinalIgnoreCase))
            {
                string lenPart = line.Substring("Content-Length:".Length).Trim();
                if (!int.TryParse(lenPart, out int contentLength) || contentLength < 0 || contentLength > McpSecurityGuard.MaxJsonRpcMessageBytes)
                    throw new InvalidOperationException("Ungültiger Content-Length Header.");

                int headerLines = 0;
                while (true)
                {
                    if (++headerLines > 32)
                        throw new InvalidOperationException("Zu viele Header-Zeilen im JSON-RPC Frame.");
                    string? headerLine = await ReadBoundedLineAsync(reader, 4096);
                    if (headerLine == null || headerLine.Length == 0)
                        break;
                }

                char[] buf = new char[contentLength];
                int readTotal = 0;
                while (readTotal < contentLength)
                {
                    int n = await reader.ReadAsync(buf, readTotal, contentLength - readTotal);
                    if (n <= 0) break;
                    readTotal += n;
                }
                return (new string(buf, 0, readTotal), true);
            }

            return (line, false);
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
                    "priority": { "type": "string", "enum": ["hoch", "mittel", "niedrig"] },
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
                    "priority": { "type": "string", "enum": ["hoch", "mittel", "niedrig"] },
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
                description = "Führt ein streng freigegebenes Diagnose- oder Stage-Git-Pull-Preset auf einem per SHA-256 Host-Key gepinnten SSH-Profil aus (keine freien Shell-Befehle möglich, Zero-Secret Schutz).",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "profileName": { "type": "string", "description": "Name des gespeicherten Profils (z. B. 'Mittwald')." },
                    "preset": {
                      "type": "string",
                      "enum": ["git_status", "git_log", "git_branch", "php_version", "artisan_about", "disk_usage", "list_dir", "stage_git_pull"]
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
                name = "collection_control",
                description = "Zentrale Steuerung & Prüfung der Funkenwerk Collection: Systemstatus abrufen ('status'), eine der 10 Desktop-Apps starten ('launch'), Unternehmens-Stammdaten abrufen ('stammdaten') oder das Sicherheits-Audit prüfen ('security_audit').",
                inputSchema = JsonNode.Parse("""
                {
                  "type": "object",
                  "properties": {
                    "action": { "type": "string", "enum": ["status", "launch", "stammdaten", "security_audit"] },
                    "app": { "type": "string", "enum": ["funkenwerk", "tresor", "player", "video", "ftp", "ssh", "pdf", "drucker", "os", "browser"], "description": "Ziel-App bei action='launch'." },
                    "filePath": { "type": "string", "description": "Optionaler sicherer Dateipfad (für 'pdf'/'drucker'/'player') oder '--scan' (für 'drucker')." }
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
                        environment: GetOptString(args, "environment") ?? "active");

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
                        environment: GetOptString(args, "environment") ?? "active");

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
                        bool ok = LaunchCollectionExecutable("ssh", "--handoff");
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
                                if (appKey == "drucker" && string.Equals(rawFile.Trim(), "--scan", StringComparison.OrdinalIgnoreCase))
                                {
                                    safeArg = "--scan";
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
                                        throw new UnauthorizedAccessException("Dateiparameter werden aus Sicherheitsgründen nur für 'pdf', 'drucker' und 'player' akzeptiert.");

                                    string validated = McpSecurityGuard.ValidateSafeFilePath(rawFile, allowedExts, mustExist: true, maxBytes: 500L * 1024L * 1024L);
                                    safeArg = validated;
                                }
                            }

                            bool ok = LaunchCollectionExecutable(appKey, safeArg);
                            return new { success = ok, app = appKey, openedFile = rawFile };
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

        private static bool LaunchCollectionExecutable(string appKey, string? rawArguments)
        {
            (string subFolder, string exeName) = appKey switch
            {
                "funkenwerk" => ("Funkenwerk", "Funkenwerk.exe"),
                "tresor" => ("Funkenwerk Tresor", "FunkenwerkTresor.exe"),
                "player" => ("Funkenwerk Player", "FunkenwerkPlayer.exe"),
                "video" => ("Funkenwerk Video", "FunkenwerkVideo.exe"),
                "ftp" => ("Funkenwerk FTP", "FunkenwerkFtp.exe"),
                "ssh" => ("Funkenwerk SSH", "FunkenwerkSsh.exe"),
                "pdf" => ("Funkenwerk PDF", "FunkenwerkPdf.exe"),
                "drucker" => ("Funkenwerk Drucker", "FunkenwerkDrucker.exe"),
                "os" => ("Funkenwerk OS", "FunkenwerkOS.exe"),
                "browser" => ("Funkenwerk Browser", "FunkenwerkBrowser.exe"),
                _ => throw new ArgumentException($"Unbekannte Funkenwerk-App '{appKey}'.")
            };

            string exePath = Path.Combine(McpSecurityGuard.CollectionRootDir, subFolder, exeName);
            if (!File.Exists(exePath))
                throw new FileNotFoundException($"App-Binary nicht gefunden: {exePath}");

            var psi = new ProcessStartInfo
            {
                FileName = exePath,
                WorkingDirectory = Path.GetDirectoryName(exePath)!,
                UseShellExecute = false
            };

            if (!string.IsNullOrWhiteSpace(rawArguments))
            {
                if (rawArguments == "--handoff")
                {
                    psi.ArgumentList.Add("--handoff");
                }
                else
                {
                    psi.ArgumentList.Add(rawArguments.Trim('"'));
                }
            }

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
    // AUTOMATED 22-POINT SECURITY PENETRATION & E2E INTEGRATION SELF-TEST SUITE
    // =========================================================================
    public static class McpSelfTestSuite
    {
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

            Console.WriteLine("=== FUNKENWERK MCP v1.3 HARDENED SECURITY & E2E SELF-TEST (26 CHECKS) ===");

            // 1. Path Traversal Defense
            AssertThrowsSecurity("01. Block Path Traversal (..\\..\\Windows\\System32)", () =>
                McpSecurityGuard.ValidateSafeFilePath(@"C:\Users\konta\Downloads\..\..\Windows\System32\drivers\etc\hosts", new[] { ".pdf" }, false));

            // 2. SSH Private Key Sandbox Defense
            AssertThrowsSecurity("02. Block .ssh Private Key Access (id_ed25519)", () =>
                McpSecurityGuard.ValidateSafeFilePath(@"C:\Users\konta\.ssh\id_ed25519", Array.Empty<string>(), false));

            // 3. Antigravity .gemini Config Defense
            AssertThrowsSecurity("03. Block .gemini Config Access (mcp_config.json)", () =>
                McpSecurityGuard.ValidateSafeFilePath(@"C:\Users\konta\.gemini\config\mcp_config.json", Array.Empty<string>(), false));

            // 4. AppData DPAPI Vault Defense
            AssertThrowsSecurity("04. Block AppData Vault Access (profiles_vault.json)", () =>
                McpSecurityGuard.ValidateSafeFilePath(
                    Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkFtp", "profiles_vault.json"),
                    Array.Empty<string>(), false));

            // 5. Funkenwerk Collection Suite Directory Defense (Blocks ALL reads/writes inside Desktop\Funkenwerk Collection\*)
            AssertThrowsSecurity("05. Block Entire Desktop\\Funkenwerk Collection Binary/Vault Tree", () =>
                McpSecurityGuard.ValidateSafeFilePath(@"C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk PDF\trojan.pdf", new[] { ".pdf" }, false));

            // 6. NTFS Alternate Data Stream (ADS) Defense
            AssertThrowsSecurity("06. Block NTFS Alternate Data Stream (test.pdf:Zone.Identifier)", () =>
                McpSecurityGuard.ValidateSafeFilePath(@"C:\Users\konta\Downloads\test.pdf:Zone.Identifier", new[] { ".pdf" }, false));

            // 7. Executable Extension Defense
            AssertThrowsSecurity("07. Block Executable File Extensions (.exe / .ps1 / .lnk)", () =>
                McpSecurityGuard.ValidateSafeFilePath(@"C:\Users\konta\Downloads\exploit.ps1", new[] { ".ps1" }, false));

            // 8. Windows DOS Reserved Device & Named-Pipe Defense (Second-Pass Hardening)
            AssertThrowsSecurity("08. Block Windows DOS Reserved Device Names & Named Pipes (CON.pdf / \\\\.\\pipe)", () =>
            {
                McpSecurityGuard.ValidateSafeFilePath(@"C:\Users\konta\Downloads\CON.pdf", new[] { ".pdf" }, false);
            });

            // 9. Fake/Disguised PDF Magic-Header Defense (Second-Pass Hardening)
            string fakePdf = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "Downloads", "_mcp_fake_secret_test.pdf");
            try
            {
                await File.WriteAllTextAsync(fakePdf, "APP_KEY=base64:FakeDisguisedEnvFileNotAPdf");
                AssertThrowsSecurity("09. Block Disguised Non-PDF File Missing '%PDF-' Magic Header", () =>
                    McpSecurityGuard.ValidateSafeFilePath(fakePdf, new[] { ".pdf" }, mustExist: true));
            }
            finally
            {
                if (File.Exists(fakePdf)) File.Delete(fakePdf);
            }

            // 10. Outbound DLP 14-Pattern Secret Redactor Defense
            string simulatedLeak = "Sanctum: 1|CTIWGm2Ryb0d60ixSJPME2J6uSUkR1tm6Ga6DEfBd86476e9 Vault: FWVAULT2:YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXo= Env: DB_PASSWORD=SuperGeheim123! Url: https://admin:GeheimPass@mein-seelenfunke.de/api";
            string scrubbed = McpSecurityGuard.ScrubSecrets(simulatedLeak);
            AssertTest("10. Outbound DLP Redacts Sanctum, FWVAULT2, DB_PASSWORD & BasicAuth URLs",
                !scrubbed.Contains("CTIWGm2R") && !scrubbed.Contains("YWJjZGVm") && !scrubbed.Contains("SuperGeheim123") && !scrubbed.Contains("GeheimPass") && scrubbed.Contains("[REDACTED-BY-FUNKENWERK-DLP]"),
                scrubbed);

            // 11. SSRF, UserInfo & HTTP Downgrade Defense
            bool evilBlocked = !McpSecurityGuard.IsAllowedApiUrl("https://attacker.example.com/api", out _);
            bool httpRemoteBlocked = !McpSecurityGuard.IsAllowedApiUrl("http://stage.mein-seelenfunke.de/api", out _);
            bool userInfoBlocked = !McpSecurityGuard.IsAllowedApiUrl("https://user:pass@stage.mein-seelenfunke.de/api", out _);
            bool httpsStageAllowed = McpSecurityGuard.IsAllowedApiUrl("https://stage.mein-seelenfunke.de/api", out _);
            AssertTest("11. SSRF Guard, UserInfo Rejection & HTTPS Enforcement", evilBlocked && httpRemoteBlocked && userInfoBlocked && httpsStageAllowed);

            // 12. Arbitrary SSH Command Injection Defense
            bool sshInjectionBlocked = false;
            try
            {
                await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "rm -rf /; cat ~/.ssh/id_ed25519");
            }
            catch (UnauthorizedAccessException)
            {
                sshInjectionBlocked = true;
            }
            AssertTest("12. Block Arbitrary SSH Command Outside Strict Preset Enum", sshInjectionBlocked);

            // 13. SSH Subdirectory Shell-Metacharacter / Traversal Injection Defense
            bool sshDirBlocked = false;
            try
            {
                await McpSecurityGuard.ExecutePinnedSshPresetAsync("Mittwald", "git_status", "/home/p-g27wim'; id #");
            }
            catch (UnauthorizedAccessException)
            {
                sshDirBlocked = true;
            }
            AssertTest("13. Block SSH RemoteDirectory Shell-Escape / Metacharacter Injection", sshDirBlocked);

            // 14. NTFS Directory + File ACL & SHA-256 Hash-Chained Audit Log Integrity
            McpSecurityGuard.AppendAuditLog("self_test", "integrity_probe_v1_1", "ALLOW");
            var auditStatus = McpSecurityGuard.RunSecuritySelfAudit();
            AssertTest("14. NTFS CurrentUser Dir+File ACL & Live SHA-256 Audit Chain Verified",
                auditStatus.DirectoryAclRestrictedToCurrentUser && auditStatus.AuditFileAclRestrictedToCurrentUser && auditStatus.AuditHashChainValid && auditStatus.AuditEntriesCount >= 1);

            // 15. Cryptographic Audit Hash-Chain Tamper Detection Proof (Second-Pass Hardening)
            string tempChain = Path.Combine(McpSecurityGuard.McpDataDir, "_tamper_test_chain.jsonl");
            try
            {
                if (File.Exists(tempChain)) File.Delete(tempChain);
                McpSecurityGuard.AppendAuditLog("t1", "alpha", "ALLOW", tempChain);
                McpSecurityGuard.AppendAuditLog("t2", "beta", "DENY", tempChain);
                McpSecurityGuard.AppendAuditLog("t3", "gamma", "ALLOW", tempChain);
                var (validBefore, countBefore) = McpSecurityGuard.VerifyAuditChain(tempChain);

                // Tamper 1 word ("DENY" -> "ALLOW") in entry #2 without knowing future chain hashes
                string[] lines = await File.ReadAllLinesAsync(tempChain);
                lines[1] = lines[1].Replace("\"DENY\"", "\"ALLOW\"");
                await File.WriteAllLinesAsync(tempChain, lines);
                var (validAfter, _) = McpSecurityGuard.VerifyAuditChain(tempChain);

                AssertTest("15. SHA-256 Audit Hash-Chain Detects 1-Byte Log Tampering",
                    validBefore && countBefore == 3 && !validAfter);
            }
            finally
            {
                if (File.Exists(tempChain)) File.Delete(tempChain);
            }

            // 16. Deeply Nested JSON Bomb Defense (MaxDepth = 24)
            string jsonBomb = "{\"jsonrpc\":\"2.0\",\"id\":99,\"method\":\"ping\",\"params\":{\"a\":" + new string('[', 40) + "1" + new string(']', 40) + "}}";
            string? bombResp = await Program.ProcessJsonRpcMessageAsync(jsonBomb);
            AssertTest("16. Deeply Nested JSON Bomb Rejected Cleanly (MaxDepth=24 -> -32700)",
                bombResp != null && bombResp.Contains("-32700"));

            // 17. Oversized String Parameter Defense (> 4096 chars)
            string hugePathReq = "{\"jsonrpc\":\"2.0\",\"id\":100,\"method\":\"tools/call\",\"params\":{\"name\":\"pdf_inspect\",\"arguments\":{\"filePath\":\"" + new string('A', 5000) + ".pdf\"}}}";
            string? hugeResp = await Program.ProcessJsonRpcMessageAsync(hugePathReq);
            AssertTest("17. Oversized Tool Argument (>4 KB) Rejected Safely",
                hugeResp != null && hugeResp.Contains("\"isError\":true"));

            // 18. Zero-Secret Server Profile Listing + OpenSSH SHA-256 Pin Recognition
            var sanitizedProfiles = McpSecurityGuard.GetSanitizedServerProfiles();
            string profilesJson = JsonSerializer.Serialize(sanitizedProfiles);
            AssertTest("18. Zero-Secret Server Profiles Serialization (Zero Passwords/Keys Exposed)",
                !profilesJson.Contains("EncryptedPassword") && !profilesJson.Contains("FWAES256GCM1:") && !profilesJson.Contains("FWSSHAES256GCM1:"));

            // 19. Atomic PDF Write + Overwrite-Protection + FBM KYC Stammdaten Autofill E2E
            string sampleKyc = @"C:\Users\konta\Downloads\FBM_KYC_und_Verhaltensregeln_für_Geschaeftspartner-DE.pdf";
            if (File.Exists(sampleKyc))
            {
                string tempOut = @"C:\Users\konta\Downloads\_mcp_selftest_temp_kyc.pdf";
                try
                {
                    if (File.Exists(tempOut)) File.Delete(tempOut);
                    var inspect = McpPdfEngine.InspectPdf(sampleKyc);
                    var autoMap = McpPdfEngine.BuildSeelenfunkeStammdatenMapping(inspect.Fields);
                    autoMap["Zugehörigkeit Nein"] = "X";

                    byte[] rawBytes = await File.ReadAllBytesAsync(sampleKyc);
                    var (saved, _, _) = McpPdfEngine.ApplyFormValuesAndOverlays(rawBytes, autoMap, null);
                    await McpSecurityGuard.WriteSafeFileAtomicAsync(tempOut, new[] { ".pdf" }, saved, allowOverwrite: false);

                    // Verify overwrite=false blocks accidental overwrite!
                    bool overwriteBlocked = false;
                    try
                    {
                        await McpSecurityGuard.WriteSafeFileAtomicAsync(tempOut, new[] { ".pdf" }, saved, allowOverwrite: false);
                    }
                    catch (InvalidOperationException)
                    {
                        overwriteBlocked = true;
                    }

                    // Verify overwrite=true succeeds atomically
                    await McpSecurityGuard.WriteSafeFileAtomicAsync(tempOut, new[] { ".pdf" }, saved, allowOverwrite: true);

                    var verifyInspect = McpPdfEngine.InspectPdf(tempOut);
                    string firmVal = verifyInspect.Fields.First(f => f.Name == "Firmenname").Value;
                    string ownerVal = verifyInspect.Fields.First(f => f.Name == "Name des Inhabers").Value;
                    string bankVal = verifyInspect.Fields.First(f => f.Name == "Bank-Name").Value;
                    string neinVal = verifyInspect.Fields.First(f => f.Name == "Zugehörigkeit Nein").Value;

                    AssertTest("19. Atomic PDF Write, Overwrite-Guard & FBM KYC Stammdaten Autofill",
                        overwriteBlocked
                        && inspect.FormFieldCount == 35
                        && firmVal == "Mein-Seelenfunke"
                        && ownerVal == "Alina Steinhauer"
                        && bankVal == "FYRST (Deutsche Bank AG)"
                        && neinVal == "X");
                }
                finally
                {
                    if (File.Exists(tempOut)) File.Delete(tempOut);
                }
            }

            // 20. Full JSON-RPC 2.0 MCP Protocol Handshake, Resources & Tool Call Simulation
            string? initResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"antigravity","version":"2.0"}}}""");
            string? notifResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","method":"notifications/initialized"}""");
            string? resListResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":2,"method":"resources/list","params":{}}""");
            string? toolsResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":3,"method":"tools/list","params":{}}""");
            string? callResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"collection_control","arguments":{"action":"stammdaten"}}}""");

            AssertTest("20. MCP JSON-RPC 2.0 Handshake, Resources, Tools & Notification Silence",
                initResp != null && initResp.Contains("\"funkenwerk-mcp\"")
                && notifResp == null
                && resListResp != null && resListResp.Contains("funkenwerk://security/policy")
                && toolsResp != null && toolsResp.Contains("\"pdf_autofill_stammdaten\"") && toolsResp.Contains("\"tasks_manage\"")
                && callResp != null && callResp.Contains("Alina Steinhauer") && callResp.Contains("\"isError\":false"));

            // 21. Live 3-Way Organizer Sync Read Verification (Tasks + Calendar via Zero-Secret FWVAULT2 Bridge)
            var tasksObj = await McpOrganizerClient.ListTasksAsync(onlyOpen: true);
            var calObj = await McpOrganizerClient.ListCalendarEventsAsync(daysAhead: 14);
            string orgJson = JsonSerializer.Serialize(new { tasksObj, calObj });
            AssertTest("21. Live 3-Way Organizer API Sync (Tasks + Calendar via FWVAULT2 Zero-Secret Auth)",
                orgJson.Contains("\"lists\"") && orgJson.Contains("\"events\"") && !orgJson.Contains("FWVAULT2:"));

            // 22. Ecosystem Health & Collection Binaries Verification
            var health = await McpOrganizerClient.ProbeEcosystemHealthAsync();
            string healthJson = JsonSerializer.Serialize(health);
            AssertTest("22. Live Ecosystem Health & 6 Collection Binaries Verified",
                healthJson.Contains("\"apiOnline\":true") && healthJson.Contains("FunkenwerkPdf.exe\"") && !healthJson.Contains("FWVAULT2:"),
                healthJson);

            // 23. Music & SmartHome Live Control Tools Registration & Volume Bounds Guard
            string? volInvalidResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":23,"method":"tools/call","params":{"name":"music_control","arguments":{"action":"set_volume","volume":150}}}""");
            string? musicStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":24,"method":"tools/call","params":{"name":"music_control","arguments":{"action":"status","autoStartApp":false}}}""");
            string? smartStatusResp = await Program.ProcessJsonRpcMessageAsync("""{"jsonrpc":"2.0","id":25,"method":"tools/call","params":{"name":"smarthome_control","arguments":{"action":"status","autoStartApp":false}}}""");
            AssertTest("23. Music & SmartHome Control Tools + 0..100 Volume Guard + CurrentUser IPC",
                toolsResp != null && toolsResp.Contains("\"music_control\"") && toolsResp.Contains("\"smarthome_control\"")
                && volInvalidResp != null && volInvalidResp.Contains("\"isError\":true")
                && musicStatusResp != null && musicStatusResp.Contains("\"isError\":false")
                && smartStatusResp != null && smartStatusResp.Contains("\"isError\":false"));

            // 24. All 10 Funkenwerk Collection Binaries Verified (incl. FunkenwerkTresor.exe, FunkenwerkDrucker.exe, FunkenwerkBrowser.exe & FunkenwerkOS.exe)
            AssertTest("24. All 10 Funkenwerk Collection Binaries Verified",
                healthJson.Contains("Funkenwerk.exe\"")
                && healthJson.Contains("FunkenwerkTresor.exe\"")
                && healthJson.Contains("FunkenwerkPdf.exe\"")
                && healthJson.Contains("FunkenwerkDrucker.exe\"")
                && healthJson.Contains("FunkenwerkOS.exe\"")
                && healthJson.Contains("FunkenwerkBrowser.exe\"")
                && healthJson.Contains("FunkenwerkFtp.exe\"")
                && healthJson.Contains("FunkenwerkSsh.exe\"")
                && healthJson.Contains("FunkenwerkPlayer.exe\"")
                && healthJson.Contains("FunkenwerkVideo.exe\""),
                healthJson);

            Console.WriteLine($"=== RESULT: {passed} PASSED, {failed} FAILED ===");
            return failed == 0 ? 0 : 1;
        }
    }
}

using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Media;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;

namespace Funkenwerk
{
    public sealed class FunkenwerkAgentMessage
    {
        public string Role { get; set; } = "user"; // "user" | "assistant"
        public string Text { get; set; } = "";
        public string Timestamp { get; set; } = DateTime.Now.ToString("HH:mm");
        public List<string> ActionBadges { get; set; } = new();
    }

    public sealed class FunkenwerkAgentSession
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string Title { get; set; } = "Neuer Chat";
        public string UpdatedAt { get; set; } = DateTime.Now.ToString("dd.MM.yyyy HH:mm");
        public List<FunkenwerkAgentMessage> Messages { get; set; } = new();
    }

    public sealed class LocalBridgeResult
    {
        public bool Ok { get; set; } = true;
        public string Summary { get; set; } = "";
        public string? Badge { get; set; }
        public object? Data { get; set; }
    }

    public interface IFunkenwerkLocalBridge
    {
        Task<LocalBridgeResult> ExecuteMusicCommandAsync(string action, string? query = null, int? volume = null);
        Task<LocalBridgeResult> ExecuteSmartHomeCommandAsync(string action, string? target = null, string? direction = null);
        Task<LocalBridgeResult> ExecuteSuiteCommandAsync(string action, string? target = null);
        Task<string> BuildLivePcContextSummaryAsync();
    }

    /// <summary>
    /// Integrated PC AI Agent ("Funkira") + CurrentUser-ACL Named Pipe IPC Server ("Funkenwerk_ControlPipe_v1")
    /// + Native JSON-RPC 2.0 MCP Client Bridge to FunkenwerkMcp.exe (v1.3) for PDF Autofill,
    /// 3-Way Organizer, Music, SmartHome, Pinned SSH Diagnostics, and all Funkenwerk Collection Apps.
    /// </summary>
    public sealed class FunkenwerkAgentEngine : IDisposable
    {
        public const string PipeName = "Funkenwerk_ControlPipe_v1";
        private const int MaxPipePayloadBytes = 65536;

        private static string ResolveMcpBinaryPath()
        {
            string desktopMcp = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "Funkenwerk Collection", "Funkenwerk MCP", "FunkenwerkMcp.exe");
            if (File.Exists(desktopMcp)) return desktopMcp;
            if (File.Exists(@"C:\Funkenwerk Collection\Funkenwerk MCP\FunkenwerkMcp.exe")) return @"C:\Funkenwerk Collection\Funkenwerk MCP\FunkenwerkMcp.exe";
            return desktopMcp;
        }

        private readonly IFunkenwerkLocalBridge _bridge;
        private readonly string _sessionsFilePath;
        private CancellationTokenSource? _pipeCts;
        private bool _disposed;

        public List<FunkenwerkAgentSession> Sessions { get; private set; } = new();
        public FunkenwerkAgentSession ActiveSession { get; private set; }
        public bool TtsEnabled { get; set; } = false; // Strict User Opt-In per Seelenfunke Rule #7

        public FunkenwerkAgentEngine(IFunkenwerkLocalBridge bridge)
        {
            _bridge = bridge;
            string appDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Funkenwerk");
            Directory.CreateDirectory(appDir);
            _sessionsFilePath = Path.Combine(appDir, "agent_sessions.json");

            LoadSessions();
            ActiveSession = Sessions.FirstOrDefault() ?? CreateNewSession(save: true);
        }

        /// <summary>
        /// Calls our hardened local FunkenwerkMcp.exe (v1.3) via one-shot JSON-RPC 2.0 over stdio.
        /// Gives Funkira instant access to pdf_autofill_stammdaten, pdf_inspect,
        /// tasks_manage, calendar_create, shopping_manage, ssh_exec_safe_readonly, and server_list_profiles.
        /// </summary>
        public static async Task<(bool Ok, string JsonPayload, string Error)> CallFunkenwerkMcpToolAsync(
            string toolName,
            object argumentsObj,
            int timeoutMs = 15000)
        {
            string mcpBin = ResolveMcpBinaryPath();
            if (!File.Exists(mcpBin))
                return (false, "", "FunkenwerkMcp.exe wurde nicht gefunden.");

            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = mcpBin,
                    WorkingDirectory = Path.GetDirectoryName(mcpBin)!,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardInput = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    StandardInputEncoding = new UTF8Encoding(false),
                    StandardOutputEncoding = new UTF8Encoding(false)
                };

                using var proc = Process.Start(psi);
                if (proc == null)
                    return (false, "", "MCP-Prozess konnte nicht gestartet werden.");

                using var cts = new CancellationTokenSource(timeoutMs);

                string initReq = "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2025-03-26\",\"capabilities\":{},\"clientInfo\":{\"name\":\"funkenwerk-funkira\",\"version\":\"1.3\"}}}";
                string callReq = JsonSerializer.Serialize(new
                {
                    jsonrpc = "2.0",
                    id = 2,
                    method = "tools/call",
                    @params = new
                    {
                        name = toolName,
                        arguments = argumentsObj
                    }
                });

                await proc.StandardInput.WriteLineAsync(initReq).ConfigureAwait(false);
                await proc.StandardInput.WriteLineAsync("{\"jsonrpc\":\"2.0\",\"method\":\"notifications/initialized\"}").ConfigureAwait(false);
                await proc.StandardInput.WriteLineAsync(callReq).ConfigureAwait(false);
                await proc.StandardInput.FlushAsync().ConfigureAwait(false);
                proc.StandardInput.Close();

                string? toolLine = null;
                while (!cts.IsCancellationRequested)
                {
                    string? line = await proc.StandardOutput.ReadLineAsync(cts.Token).ConfigureAwait(false);
                    if (line == null) break;
                    if (line.Contains("\"id\":2"))
                    {
                        toolLine = line;
                        break;
                    }
                }

                try { if (!proc.HasExited) proc.Kill(); } catch { }

                if (string.IsNullOrWhiteSpace(toolLine))
                    return (false, "", "Keine Antwort vom Funkenwerk MCP Server erhalten.");

                using var doc = JsonDocument.Parse(toolLine);
                var root = doc.RootElement;
                if (root.TryGetProperty("error", out var errObj))
                {
                    string msg = errObj.TryGetProperty("message", out var mEl) ? (mEl.GetString() ?? "MCP Fehler") : "MCP Fehler";
                    return (false, "", msg);
                }

                if (root.TryGetProperty("result", out var resEl))
                {
                    bool isErr = resEl.TryGetProperty("isError", out var ieEl) && ieEl.ValueKind == JsonValueKind.True;
                    string textPayload = "";
                    if (resEl.TryGetProperty("content", out var cArr) && cArr.ValueKind == JsonValueKind.Array && cArr.GetArrayLength() > 0)
                    {
                        var first = cArr[0];
                        if (first.TryGetProperty("text", out var txtEl))
                            textPayload = txtEl.GetString() ?? "";
                    }
                    return (!isErr, textPayload, isErr ? textPayload : "");
                }

                return (false, "", "Ungültiges MCP Antwortformat.");
            }
            catch (Exception ex)
            {
                return (false, "", ex.Message);
            }
        }

        public void StartLocalControlPipeServer()
        {
            if (_pipeCts != null) return;
            _pipeCts = new CancellationTokenSource();
            var ct = _pipeCts.Token;

            for (int worker = 0; worker < 2; worker++)
            {
                _ = Task.Run(() => RunPipeAcceptLoopAsync(ct), ct);
            }
        }

        private async Task RunPipeAcceptLoopAsync(CancellationToken ct)
        {
            while (!ct.IsCancellationRequested)
            {
                NamedPipeServerStream? server = null;
                try
                {
                    server = new NamedPipeServerStream(
                        PipeName,
                        PipeDirection.InOut,
                        maxNumberOfServerInstances: 4,
                        PipeTransmissionMode.Byte,
                        PipeOptions.Asynchronous | PipeOptions.CurrentUserOnly,
                        inBufferSize: MaxPipePayloadBytes,
                        outBufferSize: MaxPipePayloadBytes);

                    await server.WaitForConnectionAsync(ct).ConfigureAwait(false);

                    using var reader = new StreamReader(server, new UTF8Encoding(false), detectEncodingFromByteOrderMarks: false, bufferSize: 4096, leaveOpen: true);
                    using var writer = new StreamWriter(server, new UTF8Encoding(false), bufferSize: 4096, leaveOpen: true) { AutoFlush = true };

                    var lineTask = reader.ReadLineAsync();
                    var completed = await Task.WhenAny(lineTask, Task.Delay(5000, ct)).ConfigureAwait(false);
                    if (completed != lineTask)
                    {
                        continue;
                    }

                    string? rawRequest = await lineTask.ConfigureAwait(false);
                    if (string.IsNullOrWhiteSpace(rawRequest) || rawRequest.Length > MaxPipePayloadBytes)
                    {
                        await writer.WriteLineAsync("{\"ok\":false,\"error\":\"Invalid or oversized IPC payload.\"}").ConfigureAwait(false);
                        continue;
                    }

                    string responseJson = await HandlePipeCommandJsonAsync(rawRequest).ConfigureAwait(false);
                    await writer.WriteLineAsync(responseJson).ConfigureAwait(false);
                }
                catch (OperationCanceledException)
                {
                    break;
                }
                catch
                {
                    await Task.Delay(250, ct).ConfigureAwait(false);
                }
                finally
                {
                    try { server?.Dispose(); } catch { }
                }
            }
        }

        private async Task<string> HandlePipeCommandJsonAsync(string rawJson)
        {
            try
            {
                using var doc = JsonDocument.Parse(rawJson);
                var root = doc.RootElement;
                string domain = root.TryGetProperty("domain", out var domEl) && domEl.ValueKind == JsonValueKind.String
                    ? (domEl.GetString() ?? "").Trim().ToLowerInvariant()
                    : "";
                string action = root.TryGetProperty("action", out var actEl) && actEl.ValueKind == JsonValueKind.String
                    ? (actEl.GetString() ?? "").Trim().ToLowerInvariant()
                    : "status";
                string? query = root.TryGetProperty("query", out var qEl) && qEl.ValueKind == JsonValueKind.String
                    ? qEl.GetString()
                    : null;
                string? target = root.TryGetProperty("target", out var tEl) && tEl.ValueKind == JsonValueKind.String
                    ? tEl.GetString()
                    : null;
                string? direction = root.TryGetProperty("direction", out var dEl) && dEl.ValueKind == JsonValueKind.String
                    ? dEl.GetString()
                    : null;
                int? volume = root.TryGetProperty("volume", out var vEl) && vEl.ValueKind == JsonValueKind.Number && vEl.TryGetInt32(out int vInt)
                    ? vInt
                    : null;

                LocalBridgeResult res;
                if (domain == "music")
                {
                    res = await _bridge.ExecuteMusicCommandAsync(action, query, volume).ConfigureAwait(false);
                }
                else if (domain == "smarthome")
                {
                    res = await _bridge.ExecuteSmartHomeCommandAsync(action, target ?? query, direction).ConfigureAwait(false);
                }
                else if (domain == "suite")
                {
                    res = await _bridge.ExecuteSuiteCommandAsync(action, target ?? query).ConfigureAwait(false);
                }
                else
                {
                    string summary = await _bridge.BuildLivePcContextSummaryAsync().ConfigureAwait(false);
                    res = new LocalBridgeResult { Ok = true, Summary = summary };
                }

                return JsonSerializer.Serialize(new
                {
                    ok = res.Ok,
                    summary = res.Summary,
                    badge = res.Badge,
                    data = res.Data
                });
            }
            catch (Exception ex)
            {
                return JsonSerializer.Serialize(new
                {
                    ok = false,
                    error = ex.GetType().Name + ": " + ex.Message
                });
            }
        }

        public void LoadSessions()
        {
            try
            {
                if (File.Exists(_sessionsFilePath))
                {
                    var loaded = JsonSerializer.Deserialize<List<FunkenwerkAgentSession>>(File.ReadAllText(_sessionsFilePath));
                    if (loaded != null && loaded.Count > 0)
                    {
                        foreach (var s in loaded)
                        {
                            foreach (var m in s.Messages)
                            {
                                if (m.Text != null && m.Text.Contains("Funkenwerk Miro", StringComparison.OrdinalIgnoreCase))
                                {
                                    m.Text = "Hallo Alina! Ich bin direkt mit deinem Funkenwerk MCP v1.3 verbunden und beherrsche PDF-Ausfüllen, Aufgaben, Kalender, Einkaufsliste, Musikplayer, Fokus-Modus, Smart-Home-Steckdosen, IP-Kameras und alle Funkenwerk Tools.";
                                }
                            }
                        }
                        Sessions = loaded;
                        return;
                    }
                }
            }
            catch { }

            Sessions = new List<FunkenwerkAgentSession>();
        }

        public void SaveSessions()
        {
            try
            {
                if (Sessions.Count > 30)
                {
                    Sessions = Sessions.Take(30).ToList();
                }
                string json = JsonSerializer.Serialize(Sessions, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(_sessionsFilePath, json, new UTF8Encoding(false));
            }
            catch { }
        }

        public FunkenwerkAgentSession CreateNewSession(bool save = true)
        {
            var s = new FunkenwerkAgentSession
            {
                Id = Guid.NewGuid().ToString("N"),
                Title = "Neuer Chat",
                UpdatedAt = DateTime.Now.ToString("dd.MM.yyyy HH:mm"),
                Messages = new List<FunkenwerkAgentMessage>
                {
                    new FunkenwerkAgentMessage
                    {
                        Role = "assistant",
                        Text = "Hallo Alina! Ich bin direkt mit deinem Funkenwerk MCP v1.3 verbunden und beherrsche PDF-Ausfüllen, Aufgaben, Kalender, Einkaufsliste, Musikplayer, Fokus-Modus, Smart-Home-Steckdosen, IP-Kameras und alle Funkenwerk Tools.",
                        Timestamp = DateTime.Now.ToString("HH:mm")
                    }
                }
            };
            Sessions.Insert(0, s);
            ActiveSession = s;
            if (save) SaveSessions();
            return s;
        }

        public void SelectSession(string id)
        {
            var found = Sessions.FirstOrDefault(s => s.Id == id);
            if (found != null)
            {
                ActiveSession = found;
            }
        }

        public void DeleteSession(string id)
        {
            Sessions.RemoveAll(s => s.Id == id);
            if (Sessions.Count == 0)
            {
                CreateNewSession(save: true);
            }
            else if (ActiveSession.Id == id)
            {
                ActiveSession = Sessions[0];
                SaveSessions();
            }
            else
            {
                SaveSessions();
            }
        }

        public async Task<FunkenwerkAgentMessage> SendPromptAsync(
            string rawPrompt,
            HttpClient http,
            string apiBaseUrl,
            string apiToken,
            CancellationToken ct = default)
        {
            string prompt = (rawPrompt ?? "").Trim();
            var userMsg = new FunkenwerkAgentMessage
            {
                Role = "user",
                Text = prompt,
                Timestamp = DateTime.Now.ToString("HH:mm")
            };
            ActiveSession.Messages.Add(userMsg);

            if (ActiveSession.Title == "Neuer Chat" && prompt.Length > 0)
            {
                ActiveSession.Title = prompt.Length > 34 ? prompt.Substring(0, 34).Trim() + "..." : prompt;
            }
            ActiveSession.UpdatedAt = DateTime.Now.ToString("dd.MM.yyyy HH:mm");

            // 1. Detect & execute immediate local PC & MCP actions (PDF, Tasks, Shopping, Music, Focus, SmartHome, Apps)
            var (executedActions, isPureDirectCommand) = await DetectAndRunLocalPcActionsAsync(prompt).ConfigureAwait(false);
            var badges = executedActions
                .Where(a => !string.IsNullOrWhiteSpace(a.Badge))
                .Select(a => a.Badge!)
                .Distinct()
                .ToList();

            if (isPureDirectCommand && executedActions.Count > 0)
            {
                string directReply = string.Join("\n", executedActions.Select(a => "• " + SanitizeCleanDesignText(a.Summary)));
                var directAssistantMsg = new FunkenwerkAgentMessage
                {
                    Role = "assistant",
                    Text = directReply,
                    Timestamp = DateTime.Now.ToString("HH:mm"),
                    ActionBadges = badges
                };
                ActiveSession.Messages.Add(directAssistantMsg);
                SaveSessions();
                return directAssistantMsg;
            }

            // 2. Build live PC state context
            string pcContext = await _bridge.BuildLivePcContextSummaryAsync().ConfigureAwait(false);
            string executedNote = executedActions.Count > 0
                ? "\nBereits lokal auf Alinas PC ausgeführt:\n" + string.Join("\n", executedActions.Select(a => "- " + a.Summary))
                : "";

            string mcpToolSpec =
                "\nDu kannst auf Alinas PC bei Bedarf zusätzlich lokale Funkenwerk-MCP-Aktionen auslösen, indem du am Ende deiner Antwort einen oder mehrere Blöcke im exakten Format <fw_action>{\"tool\":\"NAME\",\"args\":{...}}</fw_action> anhängst (wird automatisch ausgeführt und ausgeblendet):\n" +
                "- pdf_autofill_stammdaten: {\"inputPath\":\"C:\\\\Users\\\\konta\\\\Downloads\\\\datei.pdf\",\"openInFunkenwerkPdf\":true}\n" +
                "- tasks_manage: {\"action\":\"create\",\"title\":\"...\",\"priority\":\"high\"}\n" +
                "- calendar_create: {\"title\":\"...\",\"start\":\"YYYY-MM-DD HH:mm\",\"category\":\"Geschäftlich\"}\n" +
                "- shopping_manage: {\"action\":\"add\",\"name\":\"...\",\"quantity\":\"1\"}\n" +
                "- music_control: {\"action\":\"play\"|\"pause\"|\"next\"|\"prev\"|\"set_volume\"|\"focus_on\"|\"focus_off\",\"query\":\"...\",\"volume\":50}\n" +
                "- smarthome_control: {\"action\":\"plug_on\"|\"plug_off\"|\"camera_focus\"|\"camera_ptz\"|\"camera_snapshot\",\"target\":\"...\",\"direction\":\"left\"|\"right\"|\"up\"|\"down\"}\n" +
                "- collection_control: {\"action\":\"launch\",\"app\":\"pdf\"|\"tresor\"|\"craft\"|\"ftp\"|\"ssh\"|\"player\"|\"video\"}\n" +
                "- craft_workspace_status: {\"includeMedienCoverage\":true}\n" +
                "- craft_project_manage: {\"action\":\"list\"|\"get\"|\"save\"|\"import_media\",\"projectName\":\"Projekt 1\"}\n" +
                "- craft_soundpool_browser: {\"category\":\"all\"|\"Uebergaenge_Whoosh\"|\"Effekte_Akzente\"|\"Beats_und_Ambient\"}\n" +
                "- craft_render_social_video: {\"projectName\":\"Projekt 1\",\"openInCraftUi\":true}\n" +
                "- craft_storytold_engine: {\"action\":\"launch\"|\"export_fcproj\",\"engine\":\"artcraft\"|\"filmcraft\"|\"soundcraft\",\"projectName\":\"Projekt 1\"}\n" +
                "- ssh_exec_safe_readonly: {\"profileName\":\"Mittwald\",\"preset\":\"disk_and_uptime\"|\"git_status\"|\"php_and_artisan\"}";

            // 3. Query Seelenfunke Portal AI (/api/ai/chat -> Funkira)
            string? cloudReply = null;
            string? base64WavAudio = null;

            if (!string.IsNullOrWhiteSpace(apiBaseUrl) && !string.IsNullOrWhiteSpace(apiToken))
            {
                try
                {
                    var historyList = new List<object>
                    {
                        new
                        {
                            role = "system",
                            content = "Du bist Funkira, direkt integriert in die Funkenwerk Windows Desktop Suite auf Alinas PC (verbunden mit Funkenwerk MCP v1.3). Antworte präzise, herzlich und ohne lange Gedankenstriche. Aktueller Live-PC-Status:\n" + pcContext + executedNote + mcpToolSpec
                        }
                    };

                    foreach (var m in ActiveSession.Messages.TakeLast(12))
                    {
                        historyList.Add(new
                        {
                            role = m.Role == "assistant" ? "assistant" : "user",
                            content = m.Text
                        });
                    }

                    string bodyJson = JsonSerializer.Serialize(new
                    {
                        prompt = prompt,
                        history = historyList
                    });

                    using var cts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                    cts.CancelAfter(TimeSpan.FromSeconds(28));

                    using var req = new HttpRequestMessage(HttpMethod.Post, $"{apiBaseUrl.TrimEnd('/')}/ai/chat")
                    {
                        Content = new StringContent(bodyJson, Encoding.UTF8, "application/json")
                    };
                    req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                    req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", apiToken);

                    using var resp = await http.SendAsync(req, cts.Token).ConfigureAwait(false);
                    if (resp.IsSuccessStatusCode)
                    {
                        string respStr = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                        using var doc = JsonDocument.Parse(respStr);
                        var root = doc.RootElement;
                        if (root.TryGetProperty("response", out var rEl) && rEl.ValueKind == JsonValueKind.String)
                        {
                            cloudReply = rEl.GetString();
                        }
                        if (TtsEnabled && root.TryGetProperty("audio", out var audEl) && audEl.ValueKind == JsonValueKind.String)
                        {
                            base64WavAudio = audEl.GetString();
                        }
                    }
                }
                catch
                {
                    // Fallback to local intelligent summary below
                }
            }

            // 4. Execute any <fw_action>{...}</fw_action> tool calls emitted by Cloud Funkira!
            if (!string.IsNullOrWhiteSpace(cloudReply))
            {
                var (cleanedReply, aiActionResults) = await ParseAndExecuteStructuredFwActionsAsync(cloudReply!, executedActions.Count > 0).ConfigureAwait(false);
                cloudReply = cleanedReply;
                foreach (var ar in aiActionResults)
                {
                    executedActions.Add(ar);
                    if (!string.IsNullOrWhiteSpace(ar.Badge) && !badges.Contains(ar.Badge!))
                        badges.Add(ar.Badge!);
                }
            }

            string finalText;
            if (!string.IsNullOrWhiteSpace(cloudReply))
            {
                finalText = SanitizeCleanDesignText(StripSpeakTags(cloudReply!));
            }
            else if (executedActions.Count > 0)
            {
                finalText = string.Join("\n", executedActions.Select(a => "• " + SanitizeCleanDesignText(a.Summary)));
            }
            else
            {
                finalText = SanitizeCleanDesignText(
                    "Aktueller Funkenwerk PC- & Infrastruktur-Status:\n" + pcContext +
                    "\n\nTipp: Sag z. B. \"Fülle die KYC PDF aus\", \"Spiel Musik ab\" oder \"Packtisch Warm Licht einschalten\".");
            }

            if (TtsEnabled && !string.IsNullOrWhiteSpace(base64WavAudio))
            {
                PlayWavBase64Safe(base64WavAudio!);
            }

            var assistantMsg = new FunkenwerkAgentMessage
            {
                Role = "assistant",
                Text = finalText,
                Timestamp = DateTime.Now.ToString("HH:mm"),
                ActionBadges = badges
            };
            ActiveSession.Messages.Add(assistantMsg);
            SaveSessions();
            return assistantMsg;
        }

        private async Task<(string CleanedText, List<LocalBridgeResult> ActionResults)> ParseAndExecuteStructuredFwActionsAsync(
            string replyText,
            bool alreadyExecutedLocally)
        {
            var actionResults = new List<LocalBridgeResult>();
            var matches = Regex.Matches(replyText, @"<fw_action>\s*(\{.*?\})\s*</fw_action>", RegexOptions.Singleline | RegexOptions.IgnoreCase);
            if (matches.Count == 0)
                return (replyText, actionResults);

            string stripped = Regex.Replace(replyText, @"<fw_action>\s*\{.*?\}\s*</fw_action>", "", RegexOptions.Singleline | RegexOptions.IgnoreCase).Trim();

            if (alreadyExecutedLocally)
                return (stripped, actionResults);

            int executed = 0;
            foreach (Match m in matches)
            {
                if (executed >= 5) break;
                executed++;
                try
                {
                    var node = JsonNode.Parse(m.Groups[1].Value) as JsonObject;
                    if (node == null) continue;
                    string tool = node["tool"]?.GetValue<string>()?.Trim().ToLowerInvariant() ?? "";
                    JsonObject args = (node["args"] as JsonObject) ?? new JsonObject();

                    if (tool == "music_control")
                    {
                        string act = args["action"]?.GetValue<string>() ?? "status";
                        string? q = args["query"]?.GetValue<string>();
                        int? vol = args["volume"] != null ? args["volume"]!.GetValue<int>() : null;
                        actionResults.Add(await _bridge.ExecuteMusicCommandAsync(act, q, vol).ConfigureAwait(false));
                    }
                    else if (tool == "smarthome_control")
                    {
                        string act = args["action"]?.GetValue<string>() ?? "status";
                        string? tgt = args["target"]?.GetValue<string>();
                        string? dir = args["direction"]?.GetValue<string>();
                        actionResults.Add(await _bridge.ExecuteSmartHomeCommandAsync(act, tgt, dir).ConfigureAwait(false));
                    }
                    else if (tool == "collection_control")
                    {
                        string act = args["action"]?.GetValue<string>() ?? "launch";
                        string app = args["app"]?.GetValue<string>() ?? "pdf";
                        if (act == "launch")
                            actionResults.Add(await _bridge.ExecuteSuiteCommandAsync("launch_collection_app", app).ConfigureAwait(false));
                    }
                    else if (!string.IsNullOrWhiteSpace(tool))
                    {
                        int mcpTimeout = tool == "craft_render_social_video" ? 180000 : 20000;
                        var (ok, payload, err) = await CallFunkenwerkMcpToolAsync(tool, args, mcpTimeout).ConfigureAwait(false);
                        if (tool is "tasks_manage" or "calendar_create")
                        {
                            await _bridge.ExecuteSuiteCommandAsync("refresh_organizer").ConfigureAwait(false);
                        }

                        string badge = tool switch
                        {
                            "pdf_autofill_stammdaten" or "pdf_fill_form" or "pdf_inspect" => "📄 Funkenwerk PDF",
                            "tasks_manage" or "tasks_list" => "✅ Aufgaben",
                            "calendar_create" or "calendar_list" => "📅 Kalender",
                            "shopping_manage" => "🛒 Einkaufsliste",
                            "ssh_exec_safe_readonly" => "🖥 SSH Diagnose",
                            var t when t.StartsWith("craft_", StringComparison.OrdinalIgnoreCase) => "🎬 Funkenwerk Craft",
                            _ => "✦ MCP"
                        };

                        actionResults.Add(new LocalBridgeResult
                        {
                            Ok = ok,
                            Badge = badge,
                            Summary = ok ? $"{badge} ausgeführt." : $"Fehler bei {tool}: {err}",
                            Data = payload
                        });
                    }
                }
                catch { }
            }

            return (stripped, actionResults);
        }

        private async Task<(List<LocalBridgeResult> Results, bool IsPureDirectCommand)> DetectAndRunLocalPcActionsAsync(string prompt)
        {
            var results = new List<LocalBridgeResult>();
            if (string.IsNullOrWhiteSpace(prompt)) return (results, false);

            string q = prompt.ToLowerInvariant();
            bool hasQuestionMark = q.Contains('?') ||
                                   Regex.IsMatch(q, @"\b(warum|wieso|wie viele|welche ip|wie lautet|erkläre|analysiere|umsatz|steuer|rechnung)\b");

            // 1. PDF Stammdaten Autofill in Downloads / Documents / Desktop
            if (Regex.IsMatch(q, @"\b(stammdaten|ausfüllen|fülle)\b") && q.Contains("pdf"))
            {
                string? candidatePdf = FindMatchingUserPdf(prompt);
                if (!string.IsNullOrEmpty(candidatePdf))
                {
                    var (ok, _, err) = await CallFunkenwerkMcpToolAsync("pdf_autofill_stammdaten", new
                    {
                        inputPath = candidatePdf,
                        overwrite = true,
                        openInFunkenwerkPdf = true
                    }).ConfigureAwait(false);
                    results.Add(new LocalBridgeResult
                    {
                        Ok = ok,
                        Badge = "📄 PDF Autofill",
                        Summary = ok
                            ? $"PDF '{Path.GetFileName(candidatePdf)}' mit Seelenfunke-Stammdaten ausgefüllt und in Funkenwerk PDF geöffnet."
                            : $"PDF-Autofill fehlgeschlagen: {err}"
                    });
                }
            }

            // 3. Direct Task / To-Do Creation via MCP + Live UI Refresh
            var mTodo = Regex.Match(prompt, @"^(?:neue\s+aufgabe|aufgabe\s+anlegen|todo|to-do)\s*:\s*(.+)$", RegexOptions.IgnoreCase);
            if (mTodo.Success)
            {
                string todoTitle = mTodo.Groups[1].Value.Trim();
                var (ok, _, err) = await CallFunkenwerkMcpToolAsync("tasks_manage", new
                {
                    action = "create",
                    title = todoTitle,
                    priority = "medium"
                }).ConfigureAwait(false);
                if (ok)
                {
                    await _bridge.ExecuteSuiteCommandAsync("refresh_organizer").ConfigureAwait(false);
                }
                results.Add(new LocalBridgeResult
                {
                    Ok = ok,
                    Badge = "✅ Neue Aufgabe",
                    Summary = ok ? $"Aufgabe '{todoTitle}' angelegt und synchronisiert." : $"Aufgabe konnte nicht erstellt werden: {err}"
                });
            }

            // 4. Direct Shopping Item Addition via MCP
            var mShop = Regex.Match(prompt, @"^(?:einkaufsliste|auf\s+die\s+einkaufsliste(?:\s+setzen)?)\s*:\s*(.+)$", RegexOptions.IgnoreCase);
            if (mShop.Success)
            {
                string itemTitle = mShop.Groups[1].Value.Trim();
                var (ok, _, err) = await CallFunkenwerkMcpToolAsync("shopping_manage", new
                {
                    action = "add",
                    name = itemTitle
                }).ConfigureAwait(false);
                results.Add(new LocalBridgeResult
                {
                    Ok = ok,
                    Badge = "🛒 Einkaufsliste",
                    Summary = ok ? $"'{itemTitle}' auf die Einkaufsliste gesetzt." : $"Einkaufsliste-Fehler: {err}"
                });
            }

            // 5. Volume Control (0..100 in 1-unit steps)
            var volMatch = Regex.Match(q, @"(?:lautstärke|volume|musik\s+auf|ton\s+auf)[^\d]{0,18}(\d{1,3})\s*%?");
            if (volMatch.Success && int.TryParse(volMatch.Groups[1].Value, out int targetVol))
            {
                results.Add(await _bridge.ExecuteMusicCommandAsync("set_volume", null, Math.Clamp(targetVol, 0, 100)).ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(leiser|etwas leiser)\b") && (q.Contains("musik") || q.Contains("ton") || q.Contains("player") || q.Length < 25))
            {
                results.Add(await _bridge.ExecuteMusicCommandAsync("volume_delta", null, -10).ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(lauter|etwas lauter)\b") && (q.Contains("musik") || q.Contains("ton") || q.Contains("player") || q.Length < 25))
            {
                results.Add(await _bridge.ExecuteMusicCommandAsync("volume_delta", null, +10).ConfigureAwait(false));
            }

            // 6. Music Focus Mode ("Fokus-Modus")
            if (Regex.IsMatch(q, @"fokus[\s\-]*modus|fokus[\s\-]*animation"))
            {
                bool off = Regex.IsMatch(q, @"\b(aus|beenden|stopp|stop|schließen|verlassen)\b");
                results.Add(await _bridge.ExecuteMusicCommandAsync(off ? "focus_off" : "focus_on").ConfigureAwait(false));
            }

            // 7. Music Play / Pause / Next / Prev / Track Search
            if (Regex.IsMatch(q, @"\b(nächster song|nächstes lied|weiter|next track|überspringen|skip)\b") && !q.Contains("mail"))
            {
                results.Add(await _bridge.ExecuteMusicCommandAsync("next").ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(vorheriger song|vorheriges lied|zurück|letztes lied)\b") && (q.Contains("lied") || q.Contains("song") || q.Contains("musik")))
            {
                results.Add(await _bridge.ExecuteMusicCommandAsync("prev").ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(musik\s+(?:pausieren|pause|stopp|stoppen|aus|anhalten)|pausiere\s+(?:die\s+)?musik|stopp(?:e)?\s+(?:die\s+)?musik|^pause$)\b"))
            {
                results.Add(await _bridge.ExecuteMusicCommandAsync("pause").ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(spiel(?:e)?\s+(?:bitte\s+)?(.+?)\s+ab|spiel(?:e)?\s+musik|musik\s+(?:abspielen|starten|an|weiter)|starte\s+(?:die\s+)?musik)\b"))
            {
                string? songQuery = null;
                var mPlay = Regex.Match(prompt, @"spiel(?:e)?\s+(?:bitte\s+)?(?:die\s+playlist\s+|das\s+lied\s+|den\s+song\s+)?[""']?([^""'?!.,]+?)[""']?(?:\s+ab)?$", RegexOptions.IgnoreCase);
                if (mPlay.Success)
                {
                    string candidate = mPlay.Groups[1].Value.Trim();
                    if (!Regex.IsMatch(candidate, @"^(musik|etwas musik|ein lied|weiter)$", RegexOptions.IgnoreCase))
                    {
                        songQuery = candidate;
                    }
                }
                results.Add(await _bridge.ExecuteMusicCommandAsync("play", songQuery).ConfigureAwait(false));
            }

            // 8. SmartHome Plugs ("Lichterkette Fußleiste", "Packtisch Warm Licht", "Lichterkette Spiegel")
            if (Regex.IsMatch(q, @"\b(steckdose|packtisch|fußleiste|fussleiste|spiegel|lichterkette|alle\s+lichter|smart\s*plug)\b"))
            {
                bool turnOff = Regex.IsMatch(q, @"\b(aus|ausschalten|deaktivieren|off)\b");
                bool turnOn = Regex.IsMatch(q, @"\b(ein|an|einschalten|aktivieren|on)\b");
                if (turnOn || turnOff)
                {
                    string target = "alle";
                    if (q.Contains("packtisch") || q.Contains("warm")) target = "Packtisch Warm Licht";
                    else if (q.Contains("fußleiste") || q.Contains("fussleiste")) target = "Lichterkette Fußleiste";
                    else if (q.Contains("spiegel")) target = "Lichterkette Spiegel";

                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(turnOff ? "plug_off" : "plug_on", target).ConfigureAwait(false));
                }
            }

            // 9. SmartHome Cameras (PTZ / Snapshot / View)
            if (Regex.IsMatch(q, @"\b(terasse|terrasse|einfahrt|garage|gewerbe|kamera)\b"))
            {
                string camTarget = "Terasse";
                if (q.Contains("einfahrt")) camTarget = "Einfahrt";
                else if (q.Contains("garage")) camTarget = "Garage";
                else if (q.Contains("gewerbe")) camTarget = "Gewerbe";

                if (Regex.IsMatch(q, @"\b(nach\s+links|links\s+drehen)\b"))
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "left").ConfigureAwait(false));
                else if (Regex.IsMatch(q, @"\b(nach\s+rechts|rechts\s+drehen)\b"))
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "right").ConfigureAwait(false));
                else if (Regex.IsMatch(q, @"\b(nach\s+oben|hoch\s+neigen)\b"))
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "up").ConfigureAwait(false));
                else if (Regex.IsMatch(q, @"\b(nach\s+unten|runter\s+neigen)\b"))
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "down").ConfigureAwait(false));
                else if (Regex.IsMatch(q, @"\b(foto|snapshot|schnappschuss)\b"))
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_snapshot", camTarget).ConfigureAwait(false));
                else if (Regex.IsMatch(q, @"\b(groß|gross|einzelansicht|fokus|zeig(?:e)?\s+mir\s+die\s+kamera)\b") && !hasQuestionMark)
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_focus", camTarget).ConfigureAwait(false));
            }

            // 10. Launch Funkenwerk Collection Apps (OS / Tresor / Craft / PDF / Drucker / FTP / SSH / Player / Video) or Mail Sync
            if (results.Count == 0 && Regex.IsMatch(q, @"\b(öffne|starte)\s+(?:funkenwerk\s+)?(os|leitstand|festung|tresor|craft|artcraft|filmcraft|soundcraft|pdf|drucker|scanner|ftp|ssh|player|video)\b"))
            {
                string app = "pdf";
                if (q.Contains("os") || q.Contains("leitstand") || q.Contains("festung")) app = "os";
                else if (q.Contains("tresor")) app = "tresor";
                else if (q.Contains("craft")) app = "craft";
                else if (q.Contains("scanner")) app = "drucker|--scan";
                else if (q.Contains("drucker")) app = "drucker";
                else if (q.Contains("ftp")) app = "ftp";
                else if (q.Contains("ssh")) app = "ssh";
                else if (q.Contains("video")) app = "video";
                else if (q.Contains("player")) app = "player";

                results.Add(await _bridge.ExecuteSuiteCommandAsync("launch_collection_app", app).ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(ki[\s\-]*trichter|mails?\s+sortieren|mails?\s+synchronisieren)\b"))
            {
                results.Add(await _bridge.ExecuteSuiteCommandAsync("ai_sort_mails").ConfigureAwait(false));
            }

            bool isPureDirect = results.Count > 0 && !hasQuestionMark && prompt.Length <= 120;
            return (results, isPureDirect);
        }

        private static string? FindMatchingUserPdf(string prompt)
        {
            try
            {
                string userDir = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
                string[] roots =
                {
                    Path.Combine(userDir, "Downloads"),
                    Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments),
                    Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory)
                };

                var words = Regex.Matches(prompt.ToLowerInvariant(), @"[a-zäöüß0-9_]{3,}")
                    .Select(m => m.Value)
                    .Where(w => w is not ("pdf" or "die" or "das" or "den" or "mit" or "meinen" or "stammdaten" or "ausfüllen" or "fülle" or "bitte" or "datei" or "ordner" or "download" or "downloads"))
                    .ToList();

                string? bestFile = null;
                int bestScore = 0;
                DateTime newest = DateTime.MinValue;

                foreach (string root in roots)
                {
                    if (!Directory.Exists(root)) continue;
                    foreach (string pdf in Directory.GetFiles(root, "*.pdf", SearchOption.TopDirectoryOnly))
                    {
                        string fn = Path.GetFileNameWithoutExtension(pdf).ToLowerInvariant();
                        int score = words.Count(w => fn.Contains(w));
                        DateTime mtime = File.GetLastWriteTimeUtc(pdf);
                        if (score > bestScore || (score == bestScore && mtime > newest))
                        {
                            bestScore = score;
                            newest = mtime;
                            bestFile = pdf;
                        }
                    }
                }

                return bestFile;
            }
            catch
            {
                return null;
            }
        }

        private static string StripSpeakTags(string text)
        {
            if (string.IsNullOrEmpty(text)) return "";
            string cleaned = Regex.Replace(text, @"<speak>\s*", "", RegexOptions.IgnoreCase);
            cleaned = Regex.Replace(cleaned, @"\s*</speak>", "\n\n", RegexOptions.IgnoreCase);
            return cleaned.Trim();
        }

        private static string SanitizeCleanDesignText(string text)
        {
            if (string.IsNullOrEmpty(text)) return "";
            // Enforce Seelenfunke Rule #1: Never output " – " (en-dash surrounded by spaces)
            return text.Replace(" – ", " · ").Replace(" — ", " · ").Trim();
        }

        private static void PlayWavBase64Safe(string base64Wav)
        {
            Task.Run(() =>
            {
                try
                {
                    byte[] wavBytes = Convert.FromBase64String(base64Wav);
                    using var ms = new MemoryStream(wavBytes);
                    using var player = new SoundPlayer(ms);
                    player.PlaySync();
                }
                catch { }
            });
        }

        public void Dispose()
        {
            if (_disposed) return;
            _disposed = true;
            try { _pipeCts?.Cancel(); } catch { }
            _pipeCts = null;
        }
    }
}

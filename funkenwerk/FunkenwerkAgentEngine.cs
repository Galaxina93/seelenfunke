using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
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
    public sealed class FunkenwerkAgentActionButton
    {
        public string Label { get; set; } = "";
        public string ActionType { get; set; } = ""; // "archive_preview" | "archive_pdf" | "archive_open_native" | "archive_reveal" | "archive_copy_path" | "switch_space" | "launch_app"
        public string Argument { get; set; } = "";
    }

    public sealed class FunkenwerkAgentMessage
    {
        public string Role { get; set; } = "user"; // "user" | "assistant"
        public string Text { get; set; } = "";
        public string Timestamp { get; set; } = DateTime.Now.ToString("HH:mm");
        public List<string> ActionBadges { get; set; } = new();
        public List<FunkenwerkAgentActionButton> ActionButtons { get; set; } = new();
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

        // Zero-Token Secretary & Archive Extensions
        List<ArchiveSearchHit> SearchArchiveDocuments(string query, int maxResults = 6);
        List<ArchiveSearchHit> GetRecentScannedDocuments(int maxResults = 6, string? filterHint = null);
        Task<int> SyncArchiveChangesZeroTokenAsync();
        string GetArchiveStatisticsSummary();
        Task<LocalBridgeResult> ExecuteArchiveFileActionAsync(string actionType, string argument);
        Task<LocalBridgeResult> RunDateitrichterZeroTokenAsync();
        Task<string> QueryLocalSecretaryDataAsync(string scope, string? query = null);
        Task<LocalBridgeResult> CreateTaskQuickAsync(string title, string priority = "medium");
        Task<LocalBridgeResult> CompleteTaskQuickAsync(string titleQuery);
        Task<LocalBridgeResult> CreateCalendarEventQuickAsync(string title, DateTime start, DateTime end, bool isAllDay);
    }

    /// <summary>
    /// Integrated PC AI Agent ("Funkira") + 0-Token Millisecond Notebook Secretary +
    /// CurrentUser-ACL Named Pipe IPC Server ("Funkenwerk_ControlPipe_v1") +
    /// Native JSON-RPC 2.0 MCP Client Bridge to FunkenwerkMcp.exe (v1.4).
    /// </summary>
    public sealed class FunkenwerkAgentEngine : IDisposable
    {
        public const string PipeName = "Funkenwerk_ControlPipe_v1";
        private const int MaxPipePayloadBytes = 65536;

        public static readonly IReadOnlyList<string> SupportedSshPresets = new[]
        {
            "disk_usage",
            "uptime_and_load",
            "php_version",
            "artisan_about",
            "git_status",
            "git_log",
            "git_branch",
            "list_dir",
            "stage_git_pull"
        };

        private static string ResolveMcpBinaryPath()
        {
            string mcpRoot = CollectionModuleRegistry.ResolveModuleDirectory("Funkenwerk MCP");
            string stagedMcp = Path.Combine(mcpRoot, "staged_release", "FunkenwerkMcp.exe");
            if (File.Exists(stagedMcp)) return stagedMcp;
            string modularMcp = Path.Combine(mcpRoot, "FunkenwerkMcp.exe");
            if (File.Exists(modularMcp)) return modularMcp;
            if (File.Exists(@"C:\Funkenwerk Collection\Module\Funkenwerk MCP\FunkenwerkMcp.exe")) return @"C:\Funkenwerk Collection\Module\Funkenwerk MCP\FunkenwerkMcp.exe";
            if (File.Exists(@"C:\Funkenwerk Collection\Funkenwerk MCP\FunkenwerkMcp.exe")) return @"C:\Funkenwerk Collection\Funkenwerk MCP\FunkenwerkMcp.exe";
            return modularMcp;
        }

        private readonly IFunkenwerkLocalBridge _bridge;
        private readonly string _sessionsFilePath;
        private readonly object _sessionsLock = new();
        private CancellationTokenSource? _pipeCts;
        private bool _disposed;

        public List<FunkenwerkAgentSession> Sessions { get; private set; } = new();
        public FunkenwerkAgentSession ActiveSession { get; private set; }
        public bool TtsEnabled { get; set; } = false; // Strict User Opt-In per Seelenfunke Rule #7
        public Func<string, string, bool>? ConfirmCloudActionOverride { get; set; }

        public FunkenwerkAgentEngine(IFunkenwerkLocalBridge bridge)
        {
            _bridge = bridge;
            _sessionsFilePath = FunkenwerkSecurityVault.GetAppDataFilePath("agent_sessions.json", migrateFromBaseDir: true);

            LoadSessions();
            lock (_sessionsLock)
            {
                ActiveSession = Sessions.FirstOrDefault() ?? CreateNewSession(save: true);
            }
        }

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
                    StandardOutputEncoding = new UTF8Encoding(false),
                    StandardErrorEncoding = new UTF8Encoding(false)
                };

                using var proc = Process.Start(psi);
                if (proc == null)
                    return (false, "", "MCP-Prozess konnte nicht gestartet werden.");

                Task<string> stderrDrainTask = Task.Run(async () =>
                {
                    try { return await proc.StandardError.ReadToEndAsync().ConfigureAwait(false); }
                    catch { return ""; }
                });

                using var cts = new CancellationTokenSource(timeoutMs);

                string initReq = "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2025-03-26\",\"capabilities\":{},\"clientInfo\":{\"name\":\"funkenwerk-funkira\",\"version\":\"2.4\"}}}";
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

                JsonDocument? matchedDoc = null;
                try
                {
                    while (!cts.IsCancellationRequested)
                    {
                        string? line = await proc.StandardOutput.ReadLineAsync(cts.Token).ConfigureAwait(false);
                        if (line == null) break;
                        string trimmedLine = line.Trim();
                        if (!trimmedLine.StartsWith("{", StringComparison.Ordinal)) continue;
                        try
                        {
                            var candidateDoc = JsonDocument.Parse(trimmedLine);
                            if (candidateDoc.RootElement.TryGetProperty("id", out var idEl) &&
                                ((idEl.ValueKind == JsonValueKind.Number && idEl.TryGetInt32(out int idNum) && idNum == 2) ||
                                 (idEl.ValueKind == JsonValueKind.String && idEl.GetString() == "2")))
                            {
                                matchedDoc = candidateDoc;
                                break;
                            }
                            candidateDoc.Dispose();
                        }
                        catch { }
                    }
                }
                catch (OperationCanceledException)
                {
                    try { if (!proc.HasExited) proc.Kill(entireProcessTree: true); } catch { }
                    return (false, "", "Zeitüberschreitung beim Aufruf von FunkenwerkMcp.exe.");
                }

                try { if (!proc.HasExited) proc.Kill(entireProcessTree: true); } catch { }
                _ = await Task.WhenAny(stderrDrainTask, Task.Delay(250)).ConfigureAwait(false);

                if (matchedDoc == null)
                    return (false, "", "Keine Antwort (id=2) vom Funkenwerk MCP Server erhalten.");

                using (matchedDoc)
                {
                    var root = matchedDoc.RootElement;
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
                    string activePipeName = FunkenwerkSecurityVault.IsUsingTemporaryAppData
                        ? $"{PipeName}_selftest_{Environment.ProcessId}"
                        : PipeName;
                    server = new NamedPipeServerStream(
                        activePipeName,
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
                    if (completed != lineTask) continue;

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
                bool isAdminSession = FunkenwerkSharedSession.IsAdminSession();
                if (!isAdminSession && domain is "music" or "smarthome" or "archive")
                {
                    res = new LocalBridgeResult
                    {
                        Ok = false,
                        Badge = "Gesperrt",
                        Summary = "Dieser Bereich ist ausschließlich für die Geschäftsleitung freigegeben."
                    };
                }
                else if (domain == "music")
                    res = await _bridge.ExecuteMusicCommandAsync(action, query, volume).ConfigureAwait(false);
                else if (domain == "smarthome")
                    res = await _bridge.ExecuteSmartHomeCommandAsync(action, target ?? query, direction).ConfigureAwait(false);
                else if (domain == "suite")
                    res = await _bridge.ExecuteSuiteCommandAsync(action, target ?? query).ConfigureAwait(false);
                else if (domain == "archive")
                {
                    var hits = _bridge.SearchArchiveDocuments(query ?? target ?? "", 5);
                    res = new LocalBridgeResult { Ok = true, Badge = "Archiv", Summary = $"{hits.Count} Treffer", Data = hits };
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
            lock (_sessionsLock)
            {
                try
                {
                    if (File.Exists(_sessionsFilePath))
                    {
                        var loaded = JsonSerializer.Deserialize<List<FunkenwerkAgentSession>>(File.ReadAllText(_sessionsFilePath));
                        if (loaded != null && loaded.Count > 0)
                        {
                            Sessions = loaded;
                            return;
                        }
                    }
                }
                catch { }

                Sessions = new List<FunkenwerkAgentSession>();
            }
        }

        public void SaveSessions()
        {
            lock (_sessionsLock)
            {
                try
                {
                    if (Sessions.Count > 30)
                        Sessions = Sessions.Take(30).ToList();
                    string json = JsonSerializer.Serialize(Sessions, new JsonSerializerOptions { WriteIndented = true });
                    FunkenwerkSecurityVault.WriteSecureFileText(_sessionsFilePath, json);
                }
                catch { }
            }
        }

        public FunkenwerkAgentSession CreateNewSession(bool save = true)
        {
            lock (_sessionsLock)
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
                            Text = "Hallo Alina! Ich bin Funkira, deine persönliche Sekretärin auf dem Notebook (verbunden mit deinem Meine-Daten-Archiv, Postfach, Kalender, Aufgaben, Smart-Home & allen 14 Funkenwerk-Modulen). Frag mich sofort nach jedem Dokument, Termin, Kontakt oder PC-Befehl!",
                            Timestamp = DateTime.Now.ToString("HH:mm")
                        }
                    }
                };
                Sessions.Insert(0, s);
                ActiveSession = s;
                if (save) SaveSessions();
                return s;
            }
        }

        public void SelectSession(string id)
        {
            lock (_sessionsLock)
            {
                var found = Sessions.FirstOrDefault(s => s.Id == id);
                if (found != null) ActiveSession = found;
            }
        }

        public void DeleteSession(string id)
        {
            lock (_sessionsLock)
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
        }

        public async Task<FunkenwerkAgentMessage> SendPromptAsync(
            string rawPrompt,
            HttpClient http,
            string apiBaseUrl,
            string apiToken,
            CancellationToken ct = default)
        {
            string prompt = (rawPrompt ?? "").Trim();
            List<(string Role, string Text)> priorMessagesSnapshot;
            var userMsg = new FunkenwerkAgentMessage
            {
                Role = "user",
                Text = prompt,
                Timestamp = DateTime.Now.ToString("HH:mm")
            };

            lock (_sessionsLock)
            {
                priorMessagesSnapshot = ActiveSession.Messages
                    .TakeLast(10)
                    .Select(m => (m.Role == "assistant" ? "assistant" : "user", m.Text ?? ""))
                    .ToList();

                ActiveSession.Messages.Add(userMsg);
                if (ActiveSession.Title == "Neuer Chat" && prompt.Length > 0)
                {
                    ActiveSession.Title = prompt.Length > 34 ? prompt.Substring(0, 34).Trim() + "..." : prompt;
                }
                ActiveSession.UpdatedAt = DateTime.Now.ToString("dd.MM.yyyy HH:mm");
            }

            // 1. ZERO-TOKEN FAST PATH: Millisecond Archive Knowledge Base + Notebook Secretary + Local PC Actions
            var zeroTokenReply = await TryExecuteZeroTokenSecretaryAsync(prompt).ConfigureAwait(false);
            if (zeroTokenReply != null)
            {
                lock (_sessionsLock)
                {
                    ActiveSession.Messages.Add(zeroTokenReply);
                }
                SaveSessions();
                return zeroTokenReply;
            }

            // 2. Direct Local Hardware / Module Commands (Music, SmartHome, Suite, PDF Autofill)
            var (executedActions, locallyExecutedKeys, isPureDirectCommand) = await DetectAndRunLocalPcActionsAsync(prompt).ConfigureAwait(false);
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
                lock (_sessionsLock)
                {
                    ActiveSession.Messages.Add(directAssistantMsg);
                }
                SaveSessions();
                return directAssistantMsg;
            }

            // 3. Synchronize any freshly kDrive-synced files (0 Tokens) & build live desktop + fulltext archive context
            try { await _bridge.SyncArchiveChangesZeroTokenAsync().ConfigureAwait(false); } catch { }

            string pcContext = await _bridge.BuildLivePcContextSummaryAsync().ConfigureAwait(false);
            string qLow = prompt.ToLowerInvariant().Trim();
            bool isFollowUp = Regex.IsMatch(qLow, @"^(?:prüf(?:e)?\s*nochmal|pruef(?:e)?\s*nochmal|schau\s*nochmal|nochmal\s*prüfen|nochmal\s*checken|was\s+steht\s+(?:genau\s+)?drin|erklär(?:e)?\s*(?:das|mir)?|fass(?:e)?\s*(?:das\s+)?zusammen|vergleich|genauer|mehr\s+infos|details)\b");
            var recentScans = _bridge.GetRecentScannedDocuments(5);
            var archiveHints = isFollowUp ? new List<ArchiveSearchHit>() : _bridge.SearchArchiveDocuments(prompt, 4);

            string archiveContextBlock = "";
            var hintButtons = new List<FunkenwerkAgentActionButton>();
            var sbArch = new StringBuilder();

            if (recentScans.Count > 0)
            {
                sbArch.AppendLine("\n[NEUESTE EINGESCANNTE / ERFASSTE DOKUMENTE IN ALINAS LOKALEM ARCHIV (NACH SCAN-ZEITPUNKT)]:");
                for (int rIdx = 0; rIdx < recentScans.Count; rIdx++)
                {
                    var rec = recentScans[rIdx].Record;
                    string scanStamp = !string.IsNullOrWhiteSpace(rec.FileMtime) ? rec.FileMtime : rec.ExtractedDate;
                    string safeFile = FunkenwerkSecurityVault.SanitizeUntrustedAiText(rec.Filename, 140);
                    string safeTags = FunkenwerkSecurityVault.SanitizeUntrustedAiText(string.Join(", ", rec.Tags), 140);
                    string rawBody = !string.IsNullOrWhiteSpace(rec.SearchText) ? rec.SearchText : rec.Summary;
                    int maxBodyLen = rIdx == 0 ? 1800 : 650;
                    string safeBody = FunkenwerkSecurityVault.SanitizeUntrustedAiText(rawBody, maxBodyLen);
                    sbArch.AppendLine($"#{rIdx + 1}: {safeFile} | Scan/Änderungszeit: {scanStamp} | Dokumentdatum: {rec.ExtractedDate} | Pfad: {rec.RelPath} | Tags: {safeTags}");
                    if (!string.IsNullOrWhiteSpace(safeBody))
                        sbArch.AppendLine($"   Inhalt/OCR: {safeBody}");
                }
            }

            if (archiveHints.Count > 0 && archiveHints[0].Score >= 45)
            {
                sbArch.AppendLine("\n[THEMATISCHE TREFFER AUS ALINAS LOKALEM MEINE-DATEN-ARCHIV]:");
                foreach (var h in archiveHints.Where(x => x.Score >= 35).Take(4))
                {
                    string safeFile = FunkenwerkSecurityVault.SanitizeUntrustedAiText(h.Record.Filename, 140);
                    string safeTags = FunkenwerkSecurityVault.SanitizeUntrustedAiText(string.Join(", ", h.Record.Tags), 140);
                    string safeBody = FunkenwerkSecurityVault.SanitizeUntrustedAiText(!string.IsNullOrWhiteSpace(h.Record.SearchText) ? h.Record.SearchText : h.Snippet, 1200);
                    sbArch.AppendLine($"- {safeFile} (Datum: {h.Record.ExtractedDate}, Pfad: {h.Record.RelPath}, Tags: {safeTags}): {safeBody}");
                }
            }
            archiveContextBlock = sbArch.ToString();

            if (archiveHints.Count > 0 && archiveHints[0].Score >= 90 && !string.IsNullOrWhiteSpace(archiveHints[0].Record.RelPath))
            {
                hintButtons = BuildDocumentButtons(archiveHints[0].Record);
            }
            else if (recentScans.Count > 0 &&
                     (isFollowUp || Regex.IsMatch(qLow, @"\b(scan|eingescannt|gescannt|dokument|datei|brief|bescheid|rente|rechnung|beleg|vertrag)\b")) &&
                     !string.IsNullOrWhiteSpace(recentScans[0].Record.RelPath))
            {
                hintButtons = BuildDocumentButtons(recentScans[0].Record);
            }

            string executedNote = executedActions.Count > 0
                ? "\nBereits lokal auf Alinas Notebook ausgeführt:\n" + string.Join("\n", executedActions.Select(a => "- " + a.Summary))
                : "";

            string sshPresetsJoined = string.Join("\"|\"", SupportedSshPresets);
            string mcpToolSpec =
                "\n[FUNKENWERK DESKTOP-SEKRETÄRIN KONTEXT]\n" +
                "Du bist Funkira aus dem Mein-Seelenfunke Webportal, direkt als persönliche Notebook-Sekretärin in Funkenwerk integriert. Antworte herzlich, taktsicher, präzise auf Deutsch und ohne lange Gedankenstriche.\n" +
                "WICHTIGE ARCHIV-REGEL: Bei Fragen zu Alinas Dateien, Scans, Dokumentdaten oder Prüfbitten (z. B. 'prüf nochmal') stütze dich AUSSCHLIESSLICH auf die unten mitgelieferten verifizierten Einträge aus Alinas lokalem Meine-Daten-Gehirn! Erfinde oder zitiere NIEMALS ältere Webportal-Dokumente (wie Dr. Schmidt vom 05.10.2026), wenn neuere Dateien vorliegen.\n" +
                "Aktuelle 9-Säulen-Datenablageschablone (kDrive/Meine Daten): 00_Eingang_Trichter -> 01_Mein-Seelenfunke (Anleitungen, Banken/FYRST, Businessplan, Design, Hosting/Mittwald, Markenschutz_DPMA, Marketing, Medien, Rechnungen, Steuern, Versicherungen, Zertifikate), 02_Finanzen_Steuern_Banken, 03_Haus, 04_Versicherungen, 05_Familie_Ausweise_Vorsorge, 05_Verträge (Handy, Internet), 06_Gesundheit (Befunde, Krankenkasse, Krankmeldungen, Transition), 07_Behoerden_Recht_Berufshistorie, 08_Tresor_Backups.\n" +
                "Aktueller Live-Status auf Alinas Notebook:\n" + pcContext + archiveContextBlock + executedNote +
                "\nBei Bedarf kannst du am Ende deiner Antwort lokale Aktionen als <fw_action>{\"tool\":\"NAME\",\"args\":{...}}</fw_action> anhängen:\n" +
                "- archive_open: {\"action\":\"preview\"|\"pdf\"|\"reveal\",\"relPath\":\"...\"}\n" +
                "- suite_control: {\"action\":\"switch_space\",\"target\":\"launcher\"|\"mail\"|\"contacts\"|\"calendar\"|\"todo\"|\"orders\"|\"files\"|\"agent\"}\n" +
                "- pdf_autofill_stammdaten: {\"inputPath\":\"C:\\\\Users\\\\konta\\\\Downloads\\\\datei.pdf\",\"openInFunkenwerkPdf\":true}\n" +
                "- tasks_manage: {\"action\":\"create\",\"title\":\"...\",\"priority\":\"high\"}\n" +
                "- calendar_create: {\"title\":\"...\",\"start\":\"YYYY-MM-DD HH:mm\",\"category\":\"Geschäftlich\"}\n" +
                "- shopping_manage: {\"action\":\"add\",\"name\":\"...\",\"quantity\":\"1\"}\n" +
                "- music_control: {\"action\":\"play\"|\"pause\"|\"next\"|\"prev\"|\"set_volume\"|\"focus_on\"|\"focus_off\",\"query\":\"...\",\"volume\":50}\n" +
                "- smarthome_control: {\"action\":\"shutter_open\"|\"shutter_close\"|\"shutter_stop\"|\"shutter_my\"|\"shutter_position\"|\"group_open\"|\"group_close\"|\"group_stop\"|\"group_my\"|\"group_position\"|\"group_on\"|\"group_off\"|\"plug_on\"|\"plug_off\"|\"camera_focus\"|\"camera_ptz\"|\"camera_snapshot\",\"target\":\"Gruppenname, Gerätename, 'Wohnzimmer und Terrassentür' oder 'alle'\",\"direction\":\"left\"|\"right\"|\"up\"|\"down\"|\"50\"}\n" +
                "- collection_control: {\"action\":\"launch\",\"app\":\"3d\"|\"browser\"|\"craft\"|\"drucker\"|\"ftp\"|\"kamera\"|\"marketing\"|\"os\"|\"pdf\"|\"player\"|\"smarthome\"|\"ssh\"|\"tresor\"|\"video\"}\n" +
                $"- ssh_exec_safe_readonly: {{\"profileName\":\"Stage\",\"preset\":\"{sshPresetsJoined}\"}}";

            // 4. Query Seelenfunke Portal AI (/api/ai/chat -> Funkira, including userMsg in history!)
            string? cloudReply = null;
            string? base64WavAudio = null;

            if (!string.IsNullOrWhiteSpace(apiBaseUrl) && !string.IsNullOrWhiteSpace(apiToken))
            {
                try
                {
                    string safeApiUrl = FunkenwerkSecurityVault.NormalizeSecureApiUrl(apiBaseUrl);
                    var historyList = new List<object>
                    {
                        new { role = "system", content = mcpToolSpec }
                    };

                    foreach (var (role, text) in priorMessagesSnapshot)
                    {
                        if (!string.IsNullOrWhiteSpace(text))
                        {
                            historyList.Add(new { role, content = text });
                        }
                    }

                    // Always include current prompt as the final user turn!
                    historyList.Add(new { role = "user", content = prompt });

                    string bodyJson = JsonSerializer.Serialize(new
                    {
                        agent_name = "Funkira",
                        channel = "funkenwerk_desktop",
                        prompt = prompt,
                        skip_tts = !TtsEnabled,
                        history = historyList
                    });

                    using var cts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                    cts.CancelAfter(TimeSpan.FromSeconds(28));

                    using var req = new HttpRequestMessage(HttpMethod.Post, $"{safeApiUrl.TrimEnd('/')}/ai/chat")
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
                            cloudReply = rEl.GetString();
                        if (TtsEnabled && root.TryGetProperty("audio", out var audEl) && audEl.ValueKind == JsonValueKind.String)
                            base64WavAudio = audEl.GetString();
                    }
                }
                catch { }
            }

            if (!string.IsNullOrWhiteSpace(cloudReply))
            {
                var (cleanedReply, aiActionResults) = await ParseAndExecuteStructuredFwActionsAsync(cloudReply!, locallyExecutedKeys).ConfigureAwait(false);
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
                    "Ich bin lokal bereit (Webportal gerade offline). Live-Status:\n" + pcContext +
                    "\n\nFrag mich z. B. \"Gib mir den letzten Rentenbescheid\", \"Was steht heute an?\" oder \"Dateitrichter starten\".");
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
                ActionBadges = badges,
                ActionButtons = hintButtons
            };
            lock (_sessionsLock)
            {
                ActiveSession.Messages.Add(assistantMsg);
            }
            SaveSessions();
            return assistantMsg;
        }

        internal async Task<FunkenwerkAgentMessage?> TryExecuteZeroTokenSecretaryAsync(string prompt)
        {
            if (string.IsNullOrWhiteSpace(prompt)) return null;
            string q = prompt.ToLowerInvariant().Trim();
            string normQ = FunkenwerkArchiveKnowledgeIndex.Normalize(prompt);

            // A. Friendly Wake / Presence / Help Check (0 Tokens!)
            if (Regex.IsMatch(q, @"^(?:funkira\??|hallo(?:\s+funkira)?[!?.]*|hey(?:\s+funkira)?[!?.]*|bist\s+du\s+da\??|guten\s+(?:morgen|tag|abend)(?:\s+funkira)?[!?.]*|moin[!?.]*)$"))
            {
                string stats = _bridge.GetArchiveStatisticsSummary();
                string brief = await _bridge.QueryLocalSecretaryDataAsync("pulse").ConfigureAwait(false);
                return MakeLocalMsg(
                    $"Ja, Alina! Ich bin da. {brief} · {stats}\nSag mir einfach, welche Datei, welchen Termin, welche Mail oder welche Funkenwerk-Steuerung ich sofort für dich übernehmen soll.",
                    new[] { "0-Token Sekretärin" });
            }

            if (Regex.IsMatch(q, @"^(?:was\s+kannst\s+du|hilfe|funktionen|befehle)\??$"))
            {
                return MakeLocalMsg(
                    "Ich bin deine 0-Token Notebook-Sekretärin mit Webportal-Anbindung:\n" +
                    "• Archiv-Suche in Millisekunden: \"Gib mir bitte mal den letzten Rentenbescheid\", \"Wo liegt der Hauskaufvertrag?\", \"Letzte AU\"\n" +
                    "• Dateitrichter: \"Dateitrichter starten\" oder automatisch bei neuen Dateien in 00_Eingang_Trichter\n" +
                    "• Organizer & Postfach: \"Was steht heute an?\", \"Ungelesene Mails\", \"Suche Kontakt ...\", \"Neue Aufgabe: ...\", \"Neuer Termin: ...\"\n" +
                    "• Suite & Smart-Home: Musik, Fokus-Modus, Steckdosen, Rollläden, Kameras und alle 14 Funkenwerk-Module.",
                    new[] { "0-Token Hilfe" });
            }

            // B. Dateitrichter & Archive Statistics (0 Tokens!)
            if (Regex.IsMatch(q, @"\b(dateitrichter|eingang[\s_\-]*trichter)\b") &&
                Regex.IsMatch(q, @"\b(start|ausf|sortier|leer|verarbeit|lauf|scan|jetzt)\b"))
            {
                var res = await _bridge.RunDateitrichterZeroTokenAsync().ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(res.Summary), new[] { res.Badge ?? "⚡ Dateitrichter (0 Tokens)" });
            }

            if (Regex.IsMatch(q, @"\b(wie\s+viele|anzahl|übersicht|statistik|status)\b") &&
                Regex.IsMatch(q, @"\b(dateien|dokumente|archiv|meine\s+daten|wissensdatenbank)\b"))
            {
                string stats = _bridge.GetArchiveStatisticsSummary();
                return MakeLocalMsg(SanitizeCleanDesignText(stats), new[] { "⚡ Archiv-Index (0 Tokens)" });
            }

            // C. Direct Space Navigation (0 Tokens!)
            var mSpace = Regex.Match(q, @"^(?:wechsle\s+(?:zu|auf|in)\s+|zeig(?:e)?\s+(?:mir\s+)?(?:die\s+|den\s+|das\s+)?|gehe\s+(?:zu|auf)\s+)(?:modul\s+|bereich\s+|tab\s+)?(launcher|kachel[\s\-]*deck|start|mail|mails|postfach|e[\s\-]*mails?|kontakte?|adressbuch|kalender|termine|todo|to[\s\-]*do|aufgaben?|orders|bestellungen|drucker|label|files|dateien|dateiverwaltung|archiv)$");
            if (mSpace.Success)
            {
                string targetRaw = mSpace.Groups[1].Value;
                string spaceKey = targetRaw switch
                {
                    var x when x.Contains("mail") || x.Contains("postfach") => "mail",
                    var x when x.Contains("kontakt") || x.Contains("adress") => "contacts",
                    var x when x.Contains("kalender") || x.Contains("termin") => "calendar",
                    var x when x.Contains("todo") || x.Contains("to-do") || x.Contains("aufgab") => "todo",
                    var x when x.Contains("order") || x.Contains("bestell") || x.Contains("druck") || x.Contains("label") => "orders",
                    var x when x.Contains("file") || x.Contains("datei") || x.Contains("archiv") => "files",
                    _ => "launcher"
                };
                var res = await _bridge.ExecuteSuiteCommandAsync("switch_space", spaceKey).ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(res.Summary), new[] { res.Badge ?? "Suite Navigation" });
            }

            // D. Organizer: Today's Briefing, Tasks, Calendar, Contacts, Mail Queries & Quick Actions (0 Tokens!)
            if (Regex.IsMatch(q, @"\b(was\s+steht\s+heute|tagesbriefing|heute\s+auf\s+meiner|meine\s+aufgaben|offene\s+aufgaben|meine\s+termine|kalender\s+heute|was\s+ist\s+zu\s+tun)\b") &&
                !q.Contains("datei") && !q.Contains("dokument"))
            {
                string scope = q.Contains("kalender") || q.Contains("termin") ? (q.Contains("aufgab") || q.Contains("to-do") || q.Contains("todo") ? "briefing" : "calendar")
                    : (q.Contains("aufgab") || q.Contains("to-do") || q.Contains("todo") ? "tasks" : "briefing");
                string txt = await _bridge.QueryLocalSecretaryDataAsync(scope).ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(txt), new[] { "📋 Live-Briefing (0 Tokens)" },
                    new List<FunkenwerkAgentActionButton>
                    {
                        new() { Label = "📅 Kalender öffnen", ActionType = "switch_space", Argument = "calendar" },
                        new() { Label = "✅ Aufgaben öffnen", ActionType = "switch_space", Argument = "todo" }
                    });
            }

            var mTaskDone = Regex.Match(prompt, @"^(?:aufgabe\s+(?:erledigt|abhaken|abgeschlossen)|hake\s+(?:die\s+aufgabe\s+)?)\s*:?\s*(.+?)(?:\s+ab)?$", RegexOptions.IgnoreCase);
            if (mTaskDone.Success && !q.Contains("datei"))
            {
                string tq = mTaskDone.Groups[1].Value.Trim();
                var res = await _bridge.CompleteTaskQuickAsync(tq).ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(res.Summary), new[] { res.Badge ?? "✅ Aufgabe" });
            }

            var mTodo = Regex.Match(prompt, @"^(?:neue\s+aufgabe|aufgabe\s+anlegen|erstelle\s+(?:eine\s+)?aufgabe|todo|to-do)\s*:?\s*(.+)$", RegexOptions.IgnoreCase);
            if (mTodo.Success)
            {
                string title = mTodo.Groups[1].Value.Trim();
                var res = await _bridge.CreateTaskQuickAsync(title, q.Contains("wichtig") || q.Contains("dringend") ? "high" : "medium").ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(res.Summary), new[] { res.Badge ?? "✅ Neue Aufgabe" });
            }

            var mCal = Regex.Match(prompt, @"^(?:neuer\s+termin|termin\s+(?:anlegen|erstellen|eintragen)|trage\s+(?:einen\s+)?termin\s+ein)\s*:?\s*(.+)$", RegexOptions.IgnoreCase);
            if (mCal.Success)
            {
                var (evTitle, evStart, evEnd, allDay) = ParseQuickCalendarSpec(mCal.Groups[1].Value.Trim());
                var res = await _bridge.CreateCalendarEventQuickAsync(evTitle, evStart, evEnd, allDay).ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(res.Summary), new[] { res.Badge ?? "📅 Termin erstellt" });
            }

            var mContact = Regex.Match(prompt, @"(?:suche\s+kontakt|kontakt\s+(?:von|zu)|telefonnummer\s+von|nummer\s+von|e-?mail\s+(?:adresse\s+)?von)\s+(.+)$", RegexOptions.IgnoreCase);
            if (mContact.Success && !q.Contains("datei") && !q.Contains("dokument") && !q.Contains("bescheid"))
            {
                string cQuery = mContact.Groups[1].Value.Trim().Trim('?', '.', '!');
                string txt = await _bridge.QueryLocalSecretaryDataAsync("contacts", cQuery).ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(txt), new[] { "👤 Kontakte (0 Tokens)" });
            }

            if (Regex.IsMatch(q, @"\b(ungelesene\s+mails|wichtige\s+mails|postfach\s+status|letzte\s+mails|welche\s+mails|neue\s+mails)\b") ||
                Regex.IsMatch(q, @"^(?:suche?\s+mail|mails?\s+(?:von|zu|über))\s+(.+)$"))
            {
                string? mailFilter = null;
                var mMail = Regex.Match(prompt, @"(?:suche?\s+mail|mails?\s+(?:von|zu|über))\s+(.+)$", RegexOptions.IgnoreCase);
                if (mMail.Success) mailFilter = mMail.Groups[1].Value.Trim().Trim('?', '.');
                string scope = q.Contains("ungelesen") ? "unread_mails" : "mails";
                string txt = await _bridge.QueryLocalSecretaryDataAsync(scope, mailFilter).ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(txt), new[] { "✉ Postfach (0 Tokens)" },
                    new List<FunkenwerkAgentActionButton>
                    {
                        new() { Label = "✉ Postfach öffnen", ActionType = "switch_space", Argument = "mail" }
                    });
            }

            if (Regex.IsMatch(q, @"\b(druckauftr|labeldrucker|versandlabel|warteschlange)\b") && !q.Contains("datei"))
            {
                string txt = await _bridge.QueryLocalSecretaryDataAsync("orders").ConfigureAwait(false);
                return MakeLocalMsg(SanitizeCleanDesignText(txt), new[] { "🖨 Druck-Bridge (0 Tokens)" });
            }

            // E. MILLISECOND ARCHIVE KNOWLEDGE SEARCH & REAL-TIME SCAN TRACKING (0 Tokens!)
            // If Alina asks for analysis, explanation, verification ("prüf nochmal") or summary -> escalate cleanly to Cloud AI (Tier 2) with fulltext!
            if (NeedsDeepAiReasoning(q, normQ))
                return null;

            if (IsRecentScanQuery(q, normQ))
            {
                await _bridge.SyncArchiveChangesZeroTokenAsync().ConfigureAwait(false);
                var recentHits = _bridge.GetRecentScannedDocuments(5);
                if (recentHits.Count > 0)
                {
                    return await BuildArchiveResponseMessageAsync(
                        recentHits,
                        headingPrefix: "Zuletzt erfasstes Dokument",
                        badgeLabel: $"⚡ Neueste Scans ({recentHits[0].QueryLatencyMs:0.##} ms · 0 Tokens)",
                        showScanTimestamps: true,
                        rawLowerPrompt: q).ConfigureAwait(false);
                }
            }

            if (IsArchiveDocumentQuery(q, normQ))
            {
                await _bridge.SyncArchiveChangesZeroTokenAsync().ConfigureAwait(false);
                var hits = _bridge.SearchArchiveDocuments(prompt, 5);
                if (hits.Count > 0)
                {
                    return await BuildArchiveResponseMessageAsync(
                        hits,
                        headingPrefix: "Treffer",
                        badgeLabel: $"⚡ Archiv ({hits[0].QueryLatencyMs:0.##} ms · 0 Tokens)",
                        showScanTimestamps: false,
                        rawLowerPrompt: q).ConfigureAwait(false);
                }
            }

            return null;
        }

        private async Task<FunkenwerkAgentMessage> BuildArchiveResponseMessageAsync(
            List<ArchiveSearchHit> hits,
            string headingPrefix,
            string badgeLabel,
            bool showScanTimestamps,
            string rawLowerPrompt)
        {
            var top = hits[0];
            var rec = top.Record;
            string rootDir = DefaultArchiveDisplayRoot();
            string winFull = Path.Combine(rootDir, rec.RelPath.Replace('/', Path.DirectorySeparatorChar));
            string formattedDocDate = FormatDateGermanSafe(rec.ExtractedDate);
            string formattedScanTime = FormatDateTimeGermanSafe(rec.FileMtime, rec.IndexedAt);

            var sb = new StringBuilder();
            sb.AppendLine($"{headingPrefix} in {top.QueryLatencyMs:0.##} ms (0 Tokens):");
            sb.AppendLine();
            sb.AppendLine($"• Dokument: {rec.Filename} ({rec.Ext.ToUpperInvariant()})");
            if (showScanTimestamps && !string.IsNullOrWhiteSpace(formattedScanTime))
                sb.AppendLine($"• Erfasst / Gescannt: {formattedScanTime} · Dokumentdatum: {formattedDocDate} · Größe: {FormatSizeHuman(rec.Size)}");
            else
                sb.AppendLine($"• Datum: {formattedDocDate} · Größe: {FormatSizeHuman(rec.Size)}");
            sb.AppendLine($"• Speicherort: {rec.MainFolder} › {rec.SubFolder.Replace("/", " › ")}");
            sb.AppendLine($"• Pfad: {winFull}");
            if (rec.Tags.Count > 0)
                sb.AppendLine($"• Tags: {string.Join(", ", rec.Tags.Take(8))}");
            if (rec.KeyFacts != null && rec.KeyFacts.Count > 0)
                sb.AppendLine($"• Eckdaten: {string.Join(" · ", rec.KeyFacts.Select(kv => $"{kv.Key}: {kv.Value}"))}");

            string excerpt = !string.IsNullOrWhiteSpace(top.Snippet)
                ? top.Snippet
                : (!string.IsNullOrWhiteSpace(rec.SearchText)
                    ? (rec.SearchText.Length > 260 ? rec.SearchText.Substring(0, 257) + "..." : rec.SearchText)
                    : rec.Summary);
            if (!string.IsNullOrWhiteSpace(excerpt))
                sb.AppendLine($"• Inhalt/Auszug: {SanitizeCleanDesignText(excerpt)}");

            if (hits.Count > 1)
            {
                sb.AppendLine();
                sb.AppendLine(showScanTimestamps ? "Weitere kürzlich erfasste Dokumente:" : "Weitere passende Dokumente:");
                foreach (var other in hits.Skip(1).Take(3))
                {
                    string otherTime = showScanTimestamps
                        ? FormatDateTimeGermanSafe(other.Record.FileMtime, other.Record.ExtractedDate)
                        : FormatDateGermanSafe(other.Record.ExtractedDate);
                    sb.AppendLine($"• {other.Record.Filename} ({otherTime}) · {other.Record.RelPath}");
                }
            }

            bool autoOpen = Regex.IsMatch(rawLowerPrompt, @"\b(öffne|oeffne|aufmachen|mach\s+.+\s+auf|starte)\b");
            if (autoOpen && !string.IsNullOrWhiteSpace(rec.RelPath))
            {
                string act = string.Equals(rec.Ext, "pdf", StringComparison.OrdinalIgnoreCase) ? "archive_pdf" : "archive_preview";
                await _bridge.ExecuteArchiveFileActionAsync(act, rec.RelPath).ConfigureAwait(false);
            }

            return new FunkenwerkAgentMessage
            {
                Role = "assistant",
                Text = sb.ToString().Trim(),
                Timestamp = DateTime.Now.ToString("HH:mm"),
                ActionBadges = new List<string> { badgeLabel },
                ActionButtons = BuildDocumentButtons(rec)
            };
        }

        private static string FormatDateTimeGermanSafe(string? primary, string? fallback)
        {
            string raw = !string.IsNullOrWhiteSpace(primary) ? primary : (fallback ?? "");
            if (string.IsNullOrWhiteSpace(raw)) return "";
            if (DateTime.TryParse(raw, out var dt))
            {
                return (dt.Hour == 0 && dt.Minute == 0 && dt.Second == 0)
                    ? dt.ToString("dd.MM.yyyy")
                    : dt.ToString("dd.MM.yyyy HH:mm");
            }
            return raw;
        }

        private static bool NeedsDeepAiReasoning(string q, string normQ)
        {
            return Regex.IsMatch(q, @"\b(prüf(?:e)?\s*nochmal|pruef(?:e)?\s*nochmal|nochmal\s*(?:prüf|pruef|check|schau)|was\s+steht\s+(?:genau\s+)?drin|erklär(?:e|st)?\s+mir|fass(?:e)?\s+(?:mir\s+|das\s+)?zusammen|zusammenfassung|analysier|vergleich|berechne|wie\s+hoch|wieviel|wie\s+viel|stimmt\s+das|warum|was\s+rätst|empfehlung|widerspruch|schreib(?:e)?\s+(?:eine|einen)|formulier)\b") ||
                   Regex.IsMatch(normQ, @"\b(pruef nochmal|erklare mir|fasse zusammen|genauer anschauen|inhaltlich)\b");
        }

        private static bool IsRecentScanQuery(string q, string normQ)
        {
            if (q.Contains("letzte") && (q.Contains("scan") || q.Contains("eingescannt") || q.Contains("gescannt") || q.Contains("hochgeladen") || Regex.IsMatch(q, @"letzte[rn]?\s+(?:\w+\s+)?(?:dokument|datei|beleg)\b")))
                return !Regex.IsMatch(normQ, @"\b(renten|steuer|krank|bkk|vertrag|kaufvertrag|zaehler|vodafone|telekom|gehalt|zeugnis|moemax)\b");

            if (Regex.IsMatch(q, @"\b(was\s+(?:habe\s+ich|wurde|kam)\s+(?:gerade|eben|soeben|zuletzt|heute)?\s*(?:über\s+die\s+app\s+)?(?:eingescannt|gescannt|hochgeladen|erfasst|reingekommen|rein))\b") ||
                Regex.IsMatch(q, @"\b(neueste[rn]?|aktuelle[rn]?|heutige[rn]?|gerade\s+eingescannt)\s+(?:scans?|eingänge|eingaenge|dokumente?|dateien?)\b"))
                return true;

            return false;
        }

        private static bool IsArchiveDocumentQuery(string q, string normQ)
        {
            if (Regex.IsMatch(q, @"\b(spiel|musik|lied|song|fokus|lautstärke|rollladen|rollläden|rolladen|jalousie|steckdose|packtisch|spiegel|lichterkette|alle\s+lichter|kamera|terasse|terrasse|einfahrt|garage)\b"))
                return false;

            if (Regex.IsMatch(q, @"^(?:öffne|starte)\s+(?:funkenwerk\s+)?(3d|os|leitstand|festung|tresor|craft|pdf|drucker|scanner|ftp|ssh|player|musikplayer|video|smarthome|kamera|marketing|browser)\s*$"))
                return false;

            if (Regex.IsMatch(normQ, @"\b(rentenbescheid|renteninfo|renteninformation|renteninformationen|rentenversicherung|rente|drv|versorgungsausgleich|kontenklaerung|sozialversicherungsausweis|steuerbescheid|steuererklaerung|lohnsteuerbescheinigung|elster|finanzamt|kaufvertrag|notar|grundbuch|hauskredit|lastenzuschuss|darlehen|schufa|kontoauszug|kontoauszuege|volksbank|umsaetze|fyrst|personalausweis|fuehrerschein|geburtsurkunde|eheurkunde|namensaenderung|elterngeld|kindergeld|testament|vorsorgevollmacht|todesfallcheckliste|krankschreibung|krankschreibungen|krankmeldung|arbeitsunfaehigkeit|eau|krankengeld|bkk|firmus|sozialgericht|eilantrag|befund|befunde|arbeitsamt|agentur fuer arbeit|bundesagentur|bewilligungsbescheid|aufhebungsbescheid|aenderungsbescheid|gruendungszuschuss|tragfaehigkeit|gewerbeanmeldung|laserschutzbeauftragte|businessplan|liquiditaetsplanung|dpma|markenschutz|mittwald|hosting|anleitung|anleitungen|zertifikat|zertifikate|betriebshaftpflicht|gebaeudeversicherung|haftpflicht|haftpflichversicherung|zahnzusatz|zahnzusatzversicherung|unfallversicherung|autoversicherung|hiscox|arag|barmenia|allianz|axa|devk|ergo|neodigital|allsafe|kravag|verti|adac|keepass|kdbx|zaehlerstand|zaehlerstaende|wasserzaehler|drehstromzaehler|stromzaehler|photovoltaik|waermepumpe|arbeitszeugnis|gehaltsabrechnung|telekom|vodafone|o2|glasfaser|simplytel|handyvertrag|internetvertrag|rundfunk|lsw|vattenfall)\b"))
                return true;

            if (Regex.IsMatch(q, @"\b(wo\s+liegt|wo\s+finde|gib\s+mir|zeig\s+mir|such(?:e)?\s+(?:nach|die|den|das)|finde\s+(?:die|den|das)|öffne\s+(?:die|den|das)|welche\s+datei|welches\s+dokument|letzte[rn]?\s+(?:\w+\s+)?(?:bescheid|rechnung|vertrag|brief|auszug|attest|befund|dokument|datei|scan|police|versicherung|anleitung|zertifikat|urkunde|abrechnung))\b") &&
                !q.Contains("umsatz") && !q.Contains("shop") && !q.Contains("stripe") && !q.Contains("server") && !q.Contains("live-server"))
            {
                return true;
            }

            return false;
        }

        private static List<FunkenwerkAgentActionButton> BuildDocumentButtons(FunkenwerkFileDbRecord rec)
        {
            var btns = new List<FunkenwerkAgentActionButton>();
            if (string.IsNullOrWhiteSpace(rec.RelPath)) return btns;

            btns.Add(new FunkenwerkAgentActionButton
            {
                Label = "👁 Vorschau",
                ActionType = "archive_preview",
                Argument = rec.RelPath
            });

            if (string.Equals(rec.Ext, "pdf", StringComparison.OrdinalIgnoreCase))
            {
                btns.Add(new FunkenwerkAgentActionButton
                {
                    Label = "📄 In Funkenwerk PDF",
                    ActionType = "archive_pdf",
                    Argument = rec.RelPath
                });
            }
            else
            {
                btns.Add(new FunkenwerkAgentActionButton
                {
                    Label = "📂 Datei öffnen",
                    ActionType = "archive_open_native",
                    Argument = rec.RelPath
                });
            }

            btns.Add(new FunkenwerkAgentActionButton
            {
                Label = "📁 Ordner zeigen",
                ActionType = "archive_reveal",
                Argument = rec.RelPath
            });

            btns.Add(new FunkenwerkAgentActionButton
            {
                Label = "📋 Pfad kopieren",
                ActionType = "archive_copy_path",
                Argument = rec.RelPath
            });

            return btns;
        }

        private static string DefaultArchiveDisplayRoot()
        {
            if (Directory.Exists(MainWindow.DefaultKDriveArchivePath))
                return MainWindow.DefaultKDriveArchivePath;
            return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "kDrive", "Meine Daten");
        }

        private static string FormatDateGermanSafe(string? rawDate)
        {
            if (!string.IsNullOrWhiteSpace(rawDate) && DateTime.TryParse(rawDate, out var dt))
                return dt.ToString("dd.MM.yyyy", CultureInfo.InvariantCulture);
            return rawDate ?? "-";
        }

        private static string FormatSizeHuman(long bytes)
        {
            if (bytes >= 1048576) return $"{(bytes / 1048576.0):0.00} MB".Replace('.', ',');
            if (bytes >= 1024) return $"{(bytes / 1024.0):0.0} KB".Replace('.', ',');
            return $"{bytes} B";
        }

        private static (string Title, DateTime Start, DateTime End, bool IsAllDay) ParseQuickCalendarSpec(string raw)
        {
            DateTime baseDate = DateTime.Today;
            string work = raw;
            if (Regex.IsMatch(work, @"\bmorgen\b", RegexOptions.IgnoreCase))
            {
                baseDate = DateTime.Today.AddDays(1);
                work = Regex.Replace(work, @"\bmorgen\b", "", RegexOptions.IgnoreCase);
            }
            else if (Regex.IsMatch(work, @"\bübermorgen\b", RegexOptions.IgnoreCase))
            {
                baseDate = DateTime.Today.AddDays(2);
                work = Regex.Replace(work, @"\bübermorgen\b", "", RegexOptions.IgnoreCase);
            }
            else if (Regex.IsMatch(work, @"\bheute\b", RegexOptions.IgnoreCase))
            {
                work = Regex.Replace(work, @"\bheute\b", "", RegexOptions.IgnoreCase);
            }
            else
            {
                var dm = Regex.Match(work, @"\b(\d{1,2})\.(\d{1,2})\.(20\d\d)?\b");
                if (dm.Success && int.TryParse(dm.Groups[1].Value, out int d) && int.TryParse(dm.Groups[2].Value, out int m))
                {
                    int y = dm.Groups[3].Success && int.TryParse(dm.Groups[3].Value, out int py) ? py : DateTime.Today.Year;
                    try { baseDate = new DateTime(y, m, d); } catch { }
                    work = work.Remove(dm.Index, dm.Length);
                }
            }

            bool isAllDay = true;
            DateTime startDt = baseDate.AddHours(9);
            var tm = Regex.Match(work, @"\b(?:um\s+)?(\d{1,2})(?::(\d{2}))?\s*(?:uhr)?\b", RegexOptions.IgnoreCase);
            if (tm.Success && int.TryParse(tm.Groups[1].Value, out int hh) && hh >= 0 && hh <= 23)
            {
                int mm = tm.Groups[2].Success && int.TryParse(tm.Groups[2].Value, out int pmm) ? Math.Clamp(pmm, 0, 59) : 0;
                startDt = baseDate.AddHours(hh).AddMinutes(mm);
                isAllDay = false;
                work = work.Remove(tm.Index, tm.Length);
            }

            string cleanTitle = Regex.Replace(work, @"\b(am|um)\b", " ", RegexOptions.IgnoreCase);
            cleanTitle = Regex.Replace(cleanTitle, @"\s+", " ").Trim(' ', ',', ':', '-');
            if (string.IsNullOrWhiteSpace(cleanTitle)) cleanTitle = "Termin";
            return (cleanTitle, startDt, startDt.AddHours(1), isAllDay);
        }

        private static FunkenwerkAgentMessage MakeLocalMsg(string text, IEnumerable<string> badges, List<FunkenwerkAgentActionButton>? buttons = null)
        {
            return new FunkenwerkAgentMessage
            {
                Role = "assistant",
                Text = text,
                Timestamp = DateTime.Now.ToString("HH:mm"),
                ActionBadges = badges.Where(b => !string.IsNullOrWhiteSpace(b)).Distinct().ToList(),
                ActionButtons = buttons ?? new List<FunkenwerkAgentActionButton>()
            };
        }

        public static bool RequiresCloudActionConfirmation(string tool, JsonObject args, out string promptSummary)
        {
            promptSummary = "";
            string normTool = (tool ?? "").Trim().ToLowerInvariant();
            string act = args["action"]?.GetValue<string>()?.Trim().ToLowerInvariant() ?? "";
            string preset = args["preset"]?.GetValue<string>()?.Trim().ToLowerInvariant() ?? "";

            if (normTool == "stage_git_pull" || (normTool == "ssh_exec_safe_readonly" && preset == "stage_git_pull"))
            {
                promptSummary = "Cloud-KI möchte 'stage_git_pull' per SSH ausführen. Jetzt zulassen?";
                return true;
            }
            if (normTool is "pdf_fill_form" or "pdf_autofill_stammdaten")
            {
                string p = args["inputPath"]?.GetValue<string>() ?? "PDF-Datei";
                promptSummary = $"Cloud-KI möchte PDF-Formular '{Path.GetFileName(p)}' beschreiben ({normTool}). Jetzt zulassen?";
                return true;
            }
            if (normTool == "smarthome_control" && act.StartsWith("plug_", StringComparison.OrdinalIgnoreCase))
            {
                string tgt = args["target"]?.GetValue<string>() ?? "Steckdose";
                promptSummary = $"Cloud-KI möchte Smart-Home-Steckdose '{tgt}' schalten ({act}). Jetzt zulassen?";
                return true;
            }
            if (normTool is "collection_control" or "suite_control")
            {
                // Allow safe in-window space switching without popup, confirm external app launches
                if (normTool == "suite_control" && act == "switch_space")
                    return false;
                string app = args["app"]?.GetValue<string>() ?? args["target"]?.GetValue<string>() ?? act;
                promptSummary = $"Cloud-KI möchte lokale Funkenwerk-Anwendung/Aktion '{app}' starten. Jetzt zulassen?";
                return true;
            }
            if (normTool == "craft_render_social_video" ||
                (normTool == "craft_project_manage" && act is "save" or "import_media") ||
                (normTool == "craft_storytold_engine" && act is "launch" or "export_fcproj"))
            {
                promptSummary = $"Cloud-KI möchte Funkenwerk-Craft-Aktion '{normTool}' ({act}) ausführen. Jetzt zulassen?";
                return true;
            }
            return false;
        }

        private bool ConfirmMutatingCloudAction(string tool, string summary)
        {
            if (ConfirmCloudActionOverride != null)
                return ConfirmCloudActionOverride(tool, summary);

            try
            {
                var app = System.Windows.Application.Current;
                if (app?.Dispatcher != null)
                {
                    return app.Dispatcher.Invoke(() =>
                        System.Windows.MessageBox.Show(
                            summary,
                            "Funkira Sicherheits-Bestätigung",
                            System.Windows.MessageBoxButton.YesNo,
                            System.Windows.MessageBoxImage.Question,
                            System.Windows.MessageBoxResult.No) == System.Windows.MessageBoxResult.Yes);
                }
            }
            catch { }
            return false;
        }

        public Task<(string CleanedText, List<LocalBridgeResult> ActionResults)> ParseAndExecuteStructuredFwActionsForTestAsync(
            string replyText,
            HashSet<string>? alreadyExecutedKeys = null)
            => ParseAndExecuteStructuredFwActionsAsync(replyText, alreadyExecutedKeys ?? new HashSet<string>(StringComparer.OrdinalIgnoreCase));

        private async Task<(string CleanedText, List<LocalBridgeResult> ActionResults)> ParseAndExecuteStructuredFwActionsAsync(
            string replyText,
            HashSet<string> alreadyExecutedKeys)
        {
            var actionResults = new List<LocalBridgeResult>();
            var matches = Regex.Matches(replyText, @"<fw_action>\s*(\{.*?\})\s*</fw_action>", RegexOptions.Singleline | RegexOptions.IgnoreCase);
            if (matches.Count == 0)
                return (replyText, actionResults);

            string stripped = Regex.Replace(replyText, @"<fw_action>\s*\{.*?\}\s*</fw_action>", "", RegexOptions.Singleline | RegexOptions.IgnoreCase).Trim();

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
                    JsonObject args = (node["args"] as JsonObject) ?? node;
                    if (string.IsNullOrWhiteSpace(tool)) continue;

                    string dedupKey = tool;
                    if (tool == "smarthome_control")
                    {
                        string shAct = args["action"]?.GetValue<string>() ?? "";
                        dedupKey = shAct.StartsWith("plug_", StringComparison.OrdinalIgnoreCase) || shAct is "group_on" or "group_off"
                            ? "smarthome_control:plug"
                            : (shAct.StartsWith("shutter_", StringComparison.OrdinalIgnoreCase) || shAct.StartsWith("group_", StringComparison.OrdinalIgnoreCase)
                                ? "smarthome_control:shutter"
                                : "smarthome_control:camera");
                    }
                    else if (tool is "collection_control" or "suite_control")
                    {
                        dedupKey = "suite_control";
                    }

                    if (alreadyExecutedKeys.Contains(dedupKey))
                        continue;

                    if (RequiresCloudActionConfirmation(tool, args, out string confirmMsg))
                    {
                        if (!ConfirmMutatingCloudAction(tool, confirmMsg))
                        {
                            actionResults.Add(new LocalBridgeResult
                            {
                                Ok = false,
                                Badge = "🛡 Abgelehnt",
                                Summary = $"Cloud-Aktion '{tool}' wurde nicht freigegeben."
                            });
                            continue;
                        }
                    }

                    alreadyExecutedKeys.Add(dedupKey);

                    if (tool == "archive_open")
                    {
                        string act = args["action"]?.GetValue<string>() ?? "preview";
                        string relPath = args["relPath"]?.GetValue<string>() ?? "";
                        string mapped = act switch
                        {
                            "pdf" => "archive_pdf",
                            "reveal" => "archive_reveal",
                            "native" => "archive_open_native",
                            _ => "archive_preview"
                        };
                        actionResults.Add(await _bridge.ExecuteArchiveFileActionAsync(mapped, relPath).ConfigureAwait(false));
                    }
                    else if (tool == "music_control")
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
                        if (string.IsNullOrWhiteSpace(dir) && args["position"] != null)
                            dir = args["position"]!.ToString();
                        actionResults.Add(await _bridge.ExecuteSmartHomeCommandAsync(act, tgt, dir).ConfigureAwait(false));
                    }
                    else if (tool is "collection_control" or "suite_control")
                    {
                        string act = args["action"]?.GetValue<string>() ?? "launch";
                        string app = args["app"]?.GetValue<string>() ?? args["target"]?.GetValue<string>() ?? "pdf";
                        if (act == "launch")
                            actionResults.Add(await _bridge.ExecuteSuiteCommandAsync("launch_collection_app", app).ConfigureAwait(false));
                        else
                            actionResults.Add(await _bridge.ExecuteSuiteCommandAsync(act, app).ConfigureAwait(false));
                    }
                    else
                    {
                        if (tool == "ssh_exec_safe_readonly")
                        {
                            string reqPreset = args["preset"]?.GetValue<string>()?.Trim() ?? "";
                            if (!SupportedSshPresets.Contains(reqPreset, StringComparer.OrdinalIgnoreCase))
                            {
                                actionResults.Add(new LocalBridgeResult
                                {
                                    Ok = false,
                                    Badge = "🖥 SSH Blockiert",
                                    Summary = $"Unbekanntes SSH-Preset '{reqPreset}' abgelehnt."
                                });
                                continue;
                            }
                        }

                        int mcpTimeout = tool == "craft_render_social_video" ? 180000 : 20000;
                        var (ok, payload, err) = await CallFunkenwerkMcpToolAsync(tool, args, mcpTimeout).ConfigureAwait(false);
                        if (tool is "tasks_manage" or "calendar_create")
                            await _bridge.ExecuteSuiteCommandAsync("refresh_organizer").ConfigureAwait(false);

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

        private async Task<(List<LocalBridgeResult> Results, HashSet<string> ExecutedKeys, bool IsPureDirectCommand)> DetectAndRunLocalPcActionsAsync(string prompt)
        {
            var results = new List<LocalBridgeResult>();
            var executedKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            if (string.IsNullOrWhiteSpace(prompt)) return (results, executedKeys, false);

            string q = prompt.ToLowerInvariant();
            bool hasQuestionMark = q.Contains('?') ||
                                   Regex.IsMatch(q, @"\b(warum|wieso|wie viele|welche ip|wie lautet|erkläre|analysiere|umsatz|steuer|rechnung)\b");

            // 1. PDF Stammdaten Autofill in Downloads / Documents / Desktop / Meine Daten
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
                    executedKeys.Add("pdf_autofill_stammdaten");
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

            // 2. Direct Shopping Item Addition via MCP
            var mShop = Regex.Match(prompt, @"^(?:einkaufsliste|auf\s+die\s+einkaufsliste(?:\s+setzen)?)\s*:\s*(.+)$", RegexOptions.IgnoreCase);
            if (mShop.Success)
            {
                string itemTitle = mShop.Groups[1].Value.Trim();
                var (ok, _, err) = await CallFunkenwerkMcpToolAsync("shopping_manage", new
                {
                    action = "add",
                    name = itemTitle
                }).ConfigureAwait(false);
                executedKeys.Add("shopping_manage");
                results.Add(new LocalBridgeResult
                {
                    Ok = ok,
                    Badge = "🛒 Einkaufsliste",
                    Summary = ok ? $"'{itemTitle}' auf die Einkaufsliste gesetzt." : $"Einkaufsliste-Fehler: {err}"
                });
            }

            // 3. Volume Control (0..100 in 1-unit steps)
            var volMatch = Regex.Match(q, @"(?:lautstärke|volume|musik\s+auf|ton\s+auf)[^\d]{0,18}(\d{1,3})\s*%?");
            if (volMatch.Success && int.TryParse(volMatch.Groups[1].Value, out int targetVol))
            {
                executedKeys.Add("music_control");
                results.Add(await _bridge.ExecuteMusicCommandAsync("set_volume", null, Math.Clamp(targetVol, 0, 100)).ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(leiser|etwas leiser)\b") && (q.Contains("musik") || q.Contains("ton") || q.Contains("player") || q.Length < 25))
            {
                executedKeys.Add("music_control");
                results.Add(await _bridge.ExecuteMusicCommandAsync("volume_delta", null, -10).ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(lauter|etwas lauter)\b") && (q.Contains("musik") || q.Contains("ton") || q.Contains("player") || q.Length < 25))
            {
                executedKeys.Add("music_control");
                results.Add(await _bridge.ExecuteMusicCommandAsync("volume_delta", null, +10).ConfigureAwait(false));
            }

            // 4. Music Focus Mode ("Fokus-Modus")
            if (Regex.IsMatch(q, @"fokus[\s\-]*modus|fokus[\s\-]*animation"))
            {
                bool off = Regex.IsMatch(q, @"\b(aus|beenden|stopp|stop|schließen|verlassen)\b");
                executedKeys.Add("music_control");
                results.Add(await _bridge.ExecuteMusicCommandAsync(off ? "focus_off" : "focus_on").ConfigureAwait(false));
            }

            // 5. Music Play / Pause / Next / Prev / Track Search
            if (Regex.IsMatch(q, @"\b(nächster song|nächstes lied|weiter|next track|überspringen|skip)\b") && !q.Contains("mail"))
            {
                executedKeys.Add("music_control");
                results.Add(await _bridge.ExecuteMusicCommandAsync("next").ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(vorheriger song|vorheriges lied|zurück|letztes lied)\b") && (q.Contains("lied") || q.Contains("song") || q.Contains("musik")))
            {
                executedKeys.Add("music_control");
                results.Add(await _bridge.ExecuteMusicCommandAsync("prev").ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(musik\s+(?:pausieren|pause|stopp|stoppen|aus|anhalten)|pausiere\s+(?:die\s+)?musik|stopp(?:e)?\s+(?:die\s+)?musik|^pause$)\b"))
            {
                executedKeys.Add("music_control");
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
                        songQuery = candidate;
                }
                executedKeys.Add("music_control");
                results.Add(await _bridge.ExecuteMusicCommandAsync("play", songQuery).ConfigureAwait(false));
            }

            // 6A. SmartHome Connexoon Somfy Rollläden & Geräte-Gruppen (inkl. "Wohnzimmer und Terrassentür" oder "Gruppe X")
            var localSmartHome = LoadLocalSmartHomeInventorySnapshot();
            var matchedGroupInPrompt = localSmartHome.Groups.FirstOrDefault(g =>
                !string.IsNullOrWhiteSpace(g.Name) &&
                SmartHomeGroupResolver.NamesMatch(q, g.Name));
            bool mentionsKnownShutter = localSmartHome.ShutterLabels.Any(lbl =>
                !string.IsNullOrWhiteSpace(lbl) &&
                SmartHomeGroupResolver.NamesMatch(q, lbl));
            bool mentionsShutterOrGroup =
                matchedGroupInPrompt != null ||
                mentionsKnownShutter ||
                Regex.IsMatch(q, @"\b(rollladen|rolladen|rollläden|rollaeden|jalousie|tahoma|connexoon|connecoon|somfy|beschattung|terrassentür|terassentür|terrassentuer|terassentuer|gruppe\s+\w+)\b") ||
                Regex.IsMatch(q, @"\bwohnzimmer\s+(?:und|\+|&)\s+ter+assent(?:ür|uer)\b");

            if (mentionsShutterOrGroup && !hasQuestionMark)
            {
                string resolvedTarget = ResolveNaturalLanguageShutterOrGroupTarget(prompt, localSmartHome);
                bool isGroupAction = matchedGroupInPrompt != null || q.Contains("gruppe ");
                var pctMatch = Regex.Match(q, @"(\d{1,3})\s*%");

                if (matchedGroupInPrompt != null && matchedGroupInPrompt.PlugTargets.Count > 0 && matchedGroupInPrompt.ShutterTargets.Count == 0 &&
                    Regex.IsMatch(q, @"\b(ein|an|einschalten|aus|ausschalten)\b"))
                {
                    bool grpOff = Regex.IsMatch(q, @"\b(aus|ausschalten)\b");
                    executedKeys.Add("smarthome_control:plug");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(grpOff ? "group_off" : "group_on", matchedGroupInPrompt.Name).ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(stopp|stop|halt|anhalten)\b"))
                {
                    executedKeys.Add("smarthome_control:shutter");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(isGroupAction ? "group_stop" : "shutter_stop", resolvedTarget).ConfigureAwait(false));
                }
                else if (pctMatch.Success && int.TryParse(pctMatch.Groups[1].Value, out int pctVal))
                {
                    executedKeys.Add("smarthome_control:shutter");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(isGroupAction ? "group_position" : "shutter_position", resolvedTarget, Math.Clamp(pctVal, 0, 100).ToString()).ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(my[\s\-]*position|favoritenposition|\bmy\b)\b"))
                {
                    executedKeys.Add("smarthome_control:shutter");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(isGroupAction ? "group_my" : "shutter_my", resolvedTarget).ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(runter|zu|schließen|schliessen|ab|dunkel)\b"))
                {
                    executedKeys.Add("smarthome_control:shutter");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(isGroupAction ? "group_close" : "shutter_close", resolvedTarget).ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(hoch|auf|öffnen|oeffnen|hell)\b"))
                {
                    executedKeys.Add("smarthome_control:shutter");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(isGroupAction ? "group_open" : "shutter_open", resolvedTarget).ConfigureAwait(false));
                }
            }

            // 6B. SmartHome Plugs
            if (Regex.IsMatch(q, @"\b(steckdose|packtisch|fußleiste|fussleiste|spiegel|lichterkette|alle\s+lichter|smart\s*plug)\b") &&
                !executedKeys.Contains("smarthome_control:plug"))
            {
                bool turnOff = Regex.IsMatch(q, @"\b(aus|ausschalten|deaktivieren|off)\b");
                bool turnOn = Regex.IsMatch(q, @"\b(ein|an|einschalten|aktivieren|on)\b");
                if (turnOn || turnOff)
                {
                    string target = "alle";
                    var matchedPlug = localSmartHome.Plugs.FirstOrDefault(p => !string.IsNullOrWhiteSpace(p.Name) && SmartHomeGroupResolver.NamesMatch(q, p.Name));
                    if (matchedPlug != null) target = matchedPlug.Name;
                    else if (q.Contains("packtisch") || q.Contains("warm")) target = "Packtisch Warm Licht";
                    else if (q.Contains("fußleiste") || q.Contains("fussleiste")) target = "Lichterkette Fußleiste";
                    else if (q.Contains("spiegel")) target = "Lichterkette Spiegel";

                    executedKeys.Add("smarthome_control:plug");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync(turnOff ? "plug_off" : "plug_on", target).ConfigureAwait(false));
                }
            }

            // 7. SmartHome Cameras
            if (Regex.IsMatch(q, @"\b(terasse|terrasse|einfahrt|garage|gewerbe|kamera)\b"))
            {
                string camTarget = "Terasse";
                if (q.Contains("einfahrt")) camTarget = "Einfahrt";
                else if (q.Contains("garage")) camTarget = "Garage";
                else if (q.Contains("gewerbe")) camTarget = "Gewerbe";

                if (Regex.IsMatch(q, @"\b(nach\s+links|links\s+drehen)\b"))
                {
                    executedKeys.Add("smarthome_control:camera");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "left").ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(nach\s+rechts|rechts\s+drehen)\b"))
                {
                    executedKeys.Add("smarthome_control:camera");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "right").ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(nach\s+oben|hoch\s+neigen)\b"))
                {
                    executedKeys.Add("smarthome_control:camera");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "up").ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(nach\s+unten|runter\s+neigen)\b"))
                {
                    executedKeys.Add("smarthome_control:camera");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_ptz", camTarget, "down").ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(foto|snapshot|schnappschuss)\b"))
                {
                    executedKeys.Add("smarthome_control:camera");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_snapshot", camTarget).ConfigureAwait(false));
                }
                else if (Regex.IsMatch(q, @"\b(groß|gross|einzelansicht|fokus|zeig(?:e)?\s+mir\s+die\s+kamera)\b") && !hasQuestionMark)
                {
                    executedKeys.Add("smarthome_control:camera");
                    results.Add(await _bridge.ExecuteSmartHomeCommandAsync("camera_focus", camTarget).ConfigureAwait(false));
                }
            }

            // 8. Launch Funkenwerk Collection Apps or Mail Sync
            if (results.Count == 0 && Regex.IsMatch(q, @"\b(öffne|starte)\s+(?:funkenwerk\s+)?(3d|os|leitstand|festung|tresor|craft|artcraft|filmcraft|soundcraft|pdf|drucker|scanner|ftp|ssh|player|musikplayer|video|smarthome|smart\s*home|kamera|streaming|marketing|content|browser)\b"))
            {
                string app = "pdf";
                if (q.Contains("3d")) app = "3d";
                else if (q.Contains("os") || q.Contains("leitstand") || q.Contains("festung")) app = "os";
                else if (q.Contains("tresor")) app = "tresor";
                else if (q.Contains("craft")) app = "craft";
                else if (q.Contains("scanner") || q.Contains("drucker")) app = "drucker";
                else if (q.Contains("ftp")) app = "ftp";
                else if (q.Contains("ssh")) app = "ssh";
                else if (q.Contains("smarthome") || q.Contains("smart home")) app = "smarthome";
                else if (q.Contains("kamera") || q.Contains("streaming")) app = "kamera";
                else if (q.Contains("marketing") || q.Contains("content")) app = "marketing";
                else if (q.Contains("video")) app = "video";
                else if (q.Contains("browser")) app = "browser";
                else if (q.Contains("player") || q.Contains("musik")) app = "player";

                executedKeys.Add("suite_control");
                results.Add(await _bridge.ExecuteSuiteCommandAsync("launch_collection_app", app).ConfigureAwait(false));
            }
            else if (Regex.IsMatch(q, @"\b(ki[\s\-]*trichter|mails?\s+sortieren|mails?\s+synchronisieren)\b"))
            {
                executedKeys.Add("suite_control");
                results.Add(await _bridge.ExecuteSuiteCommandAsync("ai_sort_mails").ConfigureAwait(false));
            }

            bool isPureDirect = results.Count > 0 && !hasQuestionMark && prompt.Length <= 120;
            return (results, executedKeys, isPureDirect);
        }

        internal sealed class LocalSmartHomeInventorySnapshot
        {
            public List<SmartHomeDeviceGroup> Groups { get; init; } = new();
            public List<SmartPlugConfig> Plugs { get; init; } = new();
            public List<string> ShutterLabels { get; init; } = new();
        }

        internal static LocalSmartHomeInventorySnapshot LoadLocalSmartHomeInventorySnapshot()
        {
            var snap = new LocalSmartHomeInventorySnapshot();
            try
            {
                string shPath = FunkenwerkSecurityVault.GetAppDataFilePath("smarthome.json", migrateFromBaseDir: false);
                if (File.Exists(shPath))
                {
                    var shDisk = JsonSerializer.Deserialize<SmartHomePersistedData>(File.ReadAllText(shPath, Encoding.UTF8));
                    if (shDisk != null)
                    {
                        if (shDisk.Groups != null) snap.Groups.AddRange(shDisk.Groups);
                        if (shDisk.Plugs != null) snap.Plugs.AddRange(shDisk.Plugs);
                    }
                }
            }
            catch { }

            try
            {
                string thPath = FunkenwerkSecurityVault.GetAppDataFilePath("tahoma_vault.json", migrateFromBaseDir: false);
                if (File.Exists(thPath))
                {
                    string thDec = FunkenwerkSecurityVault.UnprotectSecret(File.ReadAllText(thPath, Encoding.UTF8));
                    if (!string.IsNullOrWhiteSpace(thDec))
                    {
                        using var thDoc = JsonDocument.Parse(thDec);
                        if (thDoc.RootElement.TryGetProperty("CachedShutters", out var csEl) && csEl.ValueKind == JsonValueKind.Array)
                        {
                            foreach (var el in csEl.EnumerateArray())
                            {
                                string? lbl = el.TryGetProperty("Label", out var lEl) ? lEl.GetString() : null;
                                if (!string.IsNullOrWhiteSpace(lbl) && !snap.ShutterLabels.Contains(lbl, StringComparer.OrdinalIgnoreCase))
                                {
                                    snap.ShutterLabels.Add(lbl);
                                }
                            }
                        }
                    }
                }
            }
            catch { }

            return snap;
        }

        internal static string ResolveNaturalLanguageShutterOrGroupTarget(string prompt, LocalSmartHomeInventorySnapshot snapshot)
        {
            if (string.IsNullOrWhiteSpace(prompt)) return "alle";

            var matchedGroup = SmartHomeGroupResolver.FindMatchingGroup(snapshot.Groups, prompt)
                               ?? snapshot.Groups.FirstOrDefault(g => !string.IsNullOrWhiteSpace(g.Name) && SmartHomeGroupResolver.NamesMatch(prompt, g.Name));
            if (matchedGroup != null)
            {
                return matchedGroup.Name;
            }

            string q = prompt.ToLowerInvariant();
            if (Regex.IsMatch(q, @"\b(alle|überall|ueberall|komplett|ganzes?\s+haus)\b"))
            {
                return "alle";
            }

            var matchedTargets = new List<string>();
            foreach (string lbl in snapshot.ShutterLabels)
            {
                if (!string.IsNullOrWhiteSpace(lbl) &&
                    SmartHomeGroupResolver.NamesMatch(prompt, lbl) &&
                    !matchedTargets.Contains(lbl, StringComparer.OrdinalIgnoreCase))
                {
                    matchedTargets.Add(lbl);
                }
            }

            string[] commonShutterRooms =
            {
                "Wohnzimmer", "Terrassentür", "Schlafzimmer", "Küche", "Büro",
                "Werkstatt", "Bad", "Badezimmer", "Kinderzimmer", "Gästezimmer", "Esszimmer", "Flur"
            };
            foreach (string room in commonShutterRooms)
            {
                if (SmartHomeGroupResolver.NamesMatch(prompt, room) &&
                    !matchedTargets.Any(existing => SmartHomeGroupResolver.NamesMatch(existing, room)))
                {
                    matchedTargets.Add(room);
                }
            }

            if (matchedTargets.Count > 1)
            {
                return string.Join(" und ", matchedTargets);
            }
            if (matchedTargets.Count == 1)
            {
                return matchedTargets[0];
            }

            var groupPrefixMatch = Regex.Match(prompt, @"\bgruppe\s+([A-Za-zÄÖÜäöüß0-9_\-]+)", RegexOptions.IgnoreCase);
            if (groupPrefixMatch.Success)
            {
                return groupPrefixMatch.Groups[1].Value.Trim();
            }

            return "alle";
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
            catch { return null; }
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
            return text.Replace(" \u2013 ", " · ").Replace(" \u2014 ", " · ").Trim();
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

using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading.Tasks;

namespace FunkenwerkMcp
{
    public static class McpOrganizerClient
    {
        private static readonly HttpClient Http = McpSecurityGuard.CreateHardenedApiClient(TimeSpan.FromSeconds(15));
        private static readonly Regex SafeResourceIdRegex = new(@"^[A-Za-z0-9_\-]+$", RegexOptions.Compiled);
        private static readonly Regex SafeIsoDateRegex = new(@"^[0-9]{4}-[0-9]{2}-[0-9]{2}(?:[T ][0-9]{2}:[0-9]{2}(?::[0-9]{2})?(?:\.[0-9]+)?(?:Z|[+\-][0-9]{2}:?[0-9]{2})?)?$", RegexOptions.Compiled);

        internal static HttpClient? TestHttpClientOverride { get; set; }
        internal static string? TestBaseUrlOverride { get; set; }

        public static string? NormalizeTaskPriority(string? priority)
        {
            if (string.IsNullOrWhiteSpace(priority)) return null;
            string low = priority.Trim().ToLowerInvariant();
            return low switch
            {
                "low" or "niedrig" => "niedrig",
                "normal" or "medium" or "mittel" => "mittel",
                "high" or "urgent" or "dringend" or "hoch" => "hoch",
                _ => throw new ArgumentException($"Ungueltige Aufgaben-Prioritaet '{priority}'. Erlaubt: low, normal, high, urgent, niedrig, mittel, hoch.")
            };
        }

        private static async Task<JsonDocument> SendJsonAsync(
            HttpMethod method,
            string relativePath,
            object? payload = null,
            string? environment = "active")
        {
            string baseUrl = !string.IsNullOrWhiteSpace(TestBaseUrlOverride)
                ? TestBaseUrlOverride!
                : McpSecurityGuard.ResolveApiBaseUrlForEnv(environment);
            if (!McpSecurityGuard.IsAllowedApiUrl(baseUrl, out string normalizedBase))
                throw new UnauthorizedAccessException("Nicht vertrauenswuerdige API-Basis-URL blockiert (SSRF-Schutz).");

            string fullUrl = $"{normalizedBase}/{relativePath.TrimStart('/')}";
            using var req = new HttpRequestMessage(method, fullUrl);
            if (TestHttpClientOverride == null)
            {
                McpSecurityGuard.AttachZeroSecretAdminAuth(req, normalizedBase);
            }
            else
            {
                req.Headers.TryAddWithoutValidation("Authorization", "Bearer test-mock-token");
                req.Headers.TryAddWithoutValidation("Accept", "application/json");
            }

            if (payload != null)
            {
                string jsonBody = JsonSerializer.Serialize(payload);
                req.Content = new StringContent(jsonBody, new UTF8Encoding(false), "application/json");
            }

            HttpClient client = TestHttpClientOverride ?? Http;
            using var resp = await client.SendAsync(req);
            string body = await resp.Content.ReadAsStringAsync();

            if (!resp.IsSuccessStatusCode)
            {
                string safeErr = McpSecurityGuard.ScrubSecrets(body);
                if (safeErr.Length > 300) safeErr = safeErr.Substring(0, 300);
                throw new InvalidOperationException($"API-Antwort HTTP {(int)resp.StatusCode} auf '{relativePath}': {safeErr}");
            }

            return JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body, new JsonDocumentOptions { MaxDepth = 32 });
        }

        public static async Task<object> ProbeEcosystemHealthAsync()
        {
            string activeBase = McpSecurityGuard.ResolveApiBaseUrlForEnv("active");
            long latencyMs = -1;
            bool apiOnline = false;
            string apiDetail = "";

            try
            {
                var sw = Stopwatch.StartNew();
                using var doc = await SendJsonAsync(HttpMethod.Get, "funki/tasks/lists", null, "active");
                sw.Stop();
                latencyMs = sw.ElapsedMilliseconds;
                apiOnline = doc.RootElement.TryGetProperty("success", out var s) && s.GetBoolean();
                apiDetail = "OK";
            }
            catch (Exception ex)
            {
                apiDetail = McpSecurityGuard.ScrubSecrets(ex.Message);
            }

            var collectionApps = new (string Key, string Name, string SubFolder, string ExeName, string ProcessName)[]
            {
                ("funkenwerk", "Funkenwerk", "Funkenwerk", "Funkenwerk.exe", "Funkenwerk"),
                ("3d", "Funkenwerk 3D", "Funkenwerk 3D", "Funkenwerk3D.exe", "Funkenwerk3D"),
                ("browser", "Funkenwerk Browser", "Funkenwerk Browser", "FunkenwerkBrowser.exe", "FunkenwerkBrowser"),
                ("craft", "Funkenwerk Craft", "Funkenwerk Craft", "FunkenwerkCraft.exe", "FunkenwerkCraft"),
                ("drucker", "Funkenwerk Drucker", "Funkenwerk Drucker", "FunkenwerkDrucker.exe", "FunkenwerkDrucker"),
                ("ftp", "Funkenwerk FTP", "Funkenwerk FTP", "FunkenwerkFtp.exe", "FunkenwerkFtp"),
                ("kamera", "Funkenwerk Kamera", "Funkenwerk Kamera", "FunkenwerkKamera.exe", "FunkenwerkKamera"),
                ("marketing", "Funkenwerk Marketing", "Funkenwerk Marketing", "FunkenwerkMarketing.exe", "FunkenwerkMarketing"),
                ("os", "Funkenwerk OS", "Funkenwerk OS", "FunkenwerkOS.exe", "FunkenwerkOS"),
                ("pdf", "Funkenwerk PDF", "Funkenwerk PDF", "FunkenwerkPdf.exe", "FunkenwerkPdf"),
                ("player", "Funkenwerk Player", "Funkenwerk Player", "FunkenwerkPlayer.exe", "FunkenwerkPlayer"),
                ("remote", "Funkenwerk Remote", "Funkenwerk Remote", "FunkenwerkRemote.exe", "FunkenwerkRemote"),
                ("smarthome", "Funkenwerk SmartHome", "Funkenwerk SmartHome", "FunkenwerkSmartHome.exe", "FunkenwerkSmartHome"),
                ("ssh", "Funkenwerk SSH", "Funkenwerk SSH", "FunkenwerkSsh.exe", "FunkenwerkSsh"),
                ("tresor", "Funkenwerk Tresor", "Funkenwerk Tresor", "FunkenwerkTresor.exe", "FunkenwerkTresor"),
                ("video", "Funkenwerk Video", "Funkenwerk Video", "FunkenwerkVideo.exe", "FunkenwerkVideo")
            };

            var appsReport = new List<object>();
            foreach (var app in collectionApps)
            {
                string exePath = System.IO.Path.Combine(McpSecurityGuard.ResolveCollectionSubDir(app.SubFolder), app.ExeName);
                bool installed = System.IO.File.Exists(exePath);
                int runningInstances = 0;
                try { runningInstances = Process.GetProcessesByName(app.ProcessName).Length; } catch { }

                appsReport.Add(new
                {
                    key = app.Key,
                    name = app.Name,
                    installed,
                    isRunning = runningInstances > 0,
                    runningInstances,
                    exePath
                });
            }

            return new
            {
                timestampLocal = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"),
                activeApiBaseUrl = activeBase,
                apiOnline,
                apiLatencyMs = latencyMs,
                apiDetail,
                collectionApps = appsReport,
                securityAudit = McpSecurityGuard.RunSecuritySelfAudit()
            };
        }

        public static async Task<object> ListTasksAsync(
            bool onlyOpen = true,
            bool? onlyMine = null,
            string? priority = null,
            string? status = null,
            string? listName = null,
            string? search = null,
            string? environment = "active")
        {
            using var listsDoc = await SendJsonAsync(HttpMethod.Get, "funki/tasks/lists", null, environment);
            using var tasksDoc = await SendJsonAsync(HttpMethod.Get, "funki/tasks", null, environment);

            var lists = new List<object>();
            if (listsDoc.RootElement.TryGetProperty("data", out var lArr) && lArr.ValueKind == JsonValueKind.Array)
            {
                foreach (var l in lArr.EnumerateArray())
                {
                    lists.Add(new
                    {
                        id = l.TryGetProperty("id", out var id) ? id.GetString() : "",
                        name = l.TryGetProperty("name", out var nm) ? nm.GetString() : "",
                        openCount = l.TryGetProperty("open_count", out var oc) && oc.TryGetInt32(out int ocv) ? ocv : 0,
                        completedCount = l.TryGetProperty("completed_count", out var cc) && cc.TryGetInt32(out int ccv) ? ccv : 0
                    });
                }
            }

            string? normalizedFilterPrio = NormalizeTaskPriority(priority);
            string? normalizedFilterStatus = string.IsNullOrWhiteSpace(status) ? null : status.Trim().ToLowerInvariant();
            var tasks = new List<object>();
            if (tasksDoc.RootElement.TryGetProperty("data", out var tArr) && tArr.ValueKind == JsonValueKind.Array)
            {
                foreach (var t in tArr.EnumerateArray())
                {
                    bool isCompleted = t.TryGetProperty("is_completed", out var ic) && ic.ValueKind == JsonValueKind.True;
                    if (onlyOpen && isCompleted && normalizedFilterStatus != "completed") continue;

                    bool isMine = t.TryGetProperty("is_mine", out var imProp) && imProp.ValueKind == JsonValueKind.True;
                    if (onlyMine.HasValue && isMine != onlyMine.Value) continue;

                    string rawStatus = t.TryGetProperty("status", out var stProp) && stProp.ValueKind == JsonValueKind.String
                        ? (stProp.GetString() ?? (isCompleted ? "completed" : "open"))
                        : (isCompleted ? "completed" : "open");
                    bool isEscalated = (t.TryGetProperty("is_escalated", out var escProp) && escProp.ValueKind == JsonValueKind.True)
                                       || string.Equals(rawStatus, "escalated", StringComparison.OrdinalIgnoreCase);

                    if (!string.IsNullOrWhiteSpace(normalizedFilterStatus))
                    {
                        if (normalizedFilterStatus == "escalated" && !isEscalated) continue;
                        if (normalizedFilterStatus == "waiting" && !string.Equals(rawStatus, "waiting", StringComparison.OrdinalIgnoreCase)) continue;
                        if (normalizedFilterStatus == "in_progress" && !string.Equals(rawStatus, "in_progress", StringComparison.OrdinalIgnoreCase)) continue;
                        if (normalizedFilterStatus == "open" && !string.Equals(rawStatus, "open", StringComparison.OrdinalIgnoreCase)) continue;
                        if (normalizedFilterStatus == "completed" && !isCompleted) continue;
                    }

                    string prio = t.TryGetProperty("priority", out var pr) ? (pr.GetString() ?? "niedrig") : "niedrig";
                    if (!string.IsNullOrWhiteSpace(normalizedFilterPrio) && !string.Equals(prio, normalizedFilterPrio, StringComparison.OrdinalIgnoreCase))
                        continue;

                    string lName = t.TryGetProperty("list_name", out var ln) ? (ln.GetString() ?? "Aufgaben") : "Aufgaben";
                    if (!string.IsNullOrWhiteSpace(listName) && !lName.Contains(listName.Trim(), StringComparison.OrdinalIgnoreCase))
                        continue;

                    string title = t.TryGetProperty("title", out var tt) ? (tt.GetString() ?? "") : "";
                    if (!string.IsNullOrWhiteSpace(search) && !title.Contains(search.Trim(), StringComparison.OrdinalIgnoreCase))
                        continue;

                    tasks.Add(new
                    {
                        id = t.TryGetProperty("id", out var tid) ? tid.GetString() : "",
                        title = McpSecurityGuard.ScrubSecrets(title),
                        isMine,
                        assigneeName = t.TryGetProperty("assignee_name", out var anProp) && anProp.ValueKind == JsonValueKind.String ? anProp.GetString() : null,
                        assigneeKey = t.TryGetProperty("assignee_key", out var akProp) && akProp.ValueKind == JsonValueKind.String ? akProp.GetString() : null,
                        status = rawStatus,
                        priority = prio,
                        isCompleted,
                        isEscalated,
                        escalationReason = t.TryGetProperty("escalation_reason", out var er) && er.ValueKind == JsonValueKind.String ? er.GetString() : null,
                        listName = lName,
                        parentId = t.TryGetProperty("parent_id", out var pid) && pid.ValueKind == JsonValueKind.String ? pid.GetString() : null,
                        relevantFrom = t.TryGetProperty("relevant_from", out var rf) && rf.ValueKind == JsonValueKind.String ? rf.GetString() : null,
                        dueTime = t.TryGetProperty("due_time", out var dtProp) && dtProp.ValueKind == JsonValueKind.String ? dtProp.GetString() : null,
                        durationMinutes = t.TryGetProperty("duration_minutes", out var dmProp) && dmProp.TryGetInt32(out int dmv) ? dmv : 60,
                        messagesCount = t.TryGetProperty("messages_count", out var mcProp) && mcProp.TryGetInt32(out int mcv) ? mcv : 0
                    });
                }
            }

            return new
            {
                environment = McpSecurityGuard.ResolveApiBaseUrlForEnv(environment),
                totalReturned = tasks.Count,
                lists,
                tasks
            };
        }

        public static async Task<object> ManageTaskAsync(
            string action,
            string? taskId = null,
            string? title = null,
            string? priority = null,
            string? status = null,
            bool? isMine = null,
            string? assigneeKey = null,
            string? messageId = null,
            int? position = null,
            string? listIdOrAlias = "default",
            string? listName = null,
            string? relevantFrom = null,
            string? dueTime = null,
            int? durationMinutes = null,
            string? notes = null,
            bool? isCompleted = null,
            string? environment = "active")
        {
            string act = (action ?? "").Trim().ToLowerInvariant();
            switch (act)
            {
                case "create":
                {
                    if (string.IsNullOrWhiteSpace(title))
                        throw new ArgumentException("Fuer 'create' ist 'title' erforderlich.");

                    string cleanList = string.IsNullOrWhiteSpace(listIdOrAlias) ? "default" : listIdOrAlias.Trim();
                    ValidateSafeResourceId(cleanList, "listIdOrAlias");
                    string targetList = Uri.EscapeDataString(cleanList);
                    var body = new Dictionary<string, object?>
                    {
                        ["title"] = title.Trim(),
                        ["status"] = NormalizeTaskStatus(status) ?? "open",
                        ["is_mine"] = isMine ?? false,
                        ["assignee_key"] = string.IsNullOrWhiteSpace(assigneeKey) ? null : assigneeKey.Trim(),
                        ["priority"] = NormalizeTaskPriority(priority) ?? "mittel",
                        ["list_name"] = listName,
                        ["relevant_from"] = string.IsNullOrWhiteSpace(relevantFrom) ? null : ValidateIsoDateString(relevantFrom, "relevantFrom"),
                        ["due_time"] = ValidateOptionalHmTimeString(dueTime, "dueTime"),
                        ["duration_minutes"] = durationMinutes.HasValue ? Math.Clamp(durationMinutes.Value, 15, 720) : 60,
                        ["ai_plan"] = notes,
                        ["is_completed"] = isCompleted ?? false
                    };
                    using var doc = await SendJsonAsync(HttpMethod.Post, $"funki/tasks/lists/{targetList}/tasks", body, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "update":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    var body = new Dictionary<string, object?>();
                    if (!string.IsNullOrWhiteSpace(title)) body["title"] = title.Trim();
                    if (!string.IsNullOrWhiteSpace(priority)) body["priority"] = NormalizeTaskPriority(priority);
                    if (!string.IsNullOrWhiteSpace(status)) body["status"] = NormalizeTaskStatus(status);
                    if (isMine.HasValue) body["is_mine"] = isMine.Value;
                    if (position.HasValue) body["position"] = Math.Max(0, position.Value);
                    if (isCompleted.HasValue) body["is_completed"] = isCompleted.Value;
                    if (!string.IsNullOrWhiteSpace(relevantFrom)) body["relevant_from"] = ValidateIsoDateString(relevantFrom, "relevantFrom");
                    if (dueTime != null) body["due_time"] = ValidateOptionalHmTimeString(dueTime, "dueTime");
                    if (durationMinutes.HasValue) body["duration_minutes"] = Math.Clamp(durationMinutes.Value, 15, 720);
                    if (notes != null) body["ai_plan"] = notes;

                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/tasks/{Uri.EscapeDataString(taskId!)}", body, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "set_status":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    string? normStatus = NormalizeTaskStatus(status);
                    if (string.IsNullOrWhiteSpace(normStatus))
                        throw new ArgumentException("Fuer 'set_status' ist 'status' ('open', 'waiting', 'in_progress' oder 'completed') erforderlich.");
                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/status", new { status = normStatus }, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "set_mine":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    var minePayload = new Dictionary<string, object?>
                    {
                        ["is_mine"] = isMine ?? true
                    };
                    if (position.HasValue) minePayload["position"] = Math.Max(0, position.Value);
                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/mine", minePayload, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "assign":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/assign", new { assignee_key = assigneeKey }, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "get_messages":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    using var doc = await SendJsonAsync(HttpMethod.Get, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/messages", null, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "add_message":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    if (string.IsNullOrWhiteSpace(notes))
                        throw new ArgumentException("Fuer 'add_message' ist der Nachrichtentext in 'notes' erforderlich.");
                    using var doc = await SendJsonAsync(HttpMethod.Post, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/messages", new { content = notes.Trim() }, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "edit_message":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    ValidateSafeResourceId(messageId, "messageId");
                    if (string.IsNullOrWhiteSpace(notes))
                        throw new ArgumentException("Fuer 'edit_message' ist der angepasste Text in 'notes' erforderlich (5-Minuten-Fenster).");
                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/messages/{Uri.EscapeDataString(messageId!)}", new { content = notes.Trim() }, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "resolve_escalation":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    if (string.IsNullOrWhiteSpace(relevantFrom) || string.IsNullOrWhiteSpace(notes))
                        throw new ArgumentException("Fuer 'resolve_escalation' sind 'relevantFrom' (neues Zieldatum) UND 'notes' (Status-Update fuer den Ticket-Chat) erforderlich.");
                    var body = new Dictionary<string, object?>
                    {
                        ["relevant_from"] = ValidateIsoDateString(relevantFrom, "relevantFrom"),
                        ["due_time"] = ValidateOptionalHmTimeString(dueTime, "dueTime"),
                        ["duration_minutes"] = durationMinutes.HasValue ? Math.Clamp(durationMinutes.Value, 15, 720) : null,
                        ["status"] = NormalizeTaskStatus(status) ?? "in_progress",
                        ["content"] = notes.Trim()
                    };
                    using var doc = await SendJsonAsync(HttpMethod.Post, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/resolve-escalation", body, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "toggle":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/toggle", null, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "subtask":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    if (string.IsNullOrWhiteSpace(title))
                        throw new ArgumentException("Für 'subtask' ist 'title' erforderlich.");
                    using var doc = await SendJsonAsync(HttpMethod.Post, $"funki/tasks/{Uri.EscapeDataString(taskId!)}/subtask", new { title = title.Trim() }, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }

                case "delete":
                {
                    ValidateSafeResourceId(taskId, "taskId");
                    using var doc = await SendJsonAsync(HttpMethod.Delete, $"funki/tasks/{Uri.EscapeDataString(taskId!)}", null, environment);
                    return new { success = true, deletedTaskId = taskId };
                }

                default:
                    throw new ArgumentException($"Unbekannte Aufgaben-Aktion '{action}'. Erlaubt: create, update, set_status, set_mine, get_messages, add_message, edit_message, resolve_escalation, toggle, subtask, delete.");
            }
        }

        public static async Task<object> ListCalendarEventsAsync(
            string? startDate = null,
            string? endDate = null,
            int daysAhead = 14,
            string? environment = "active",
            string? date = null)
        {
            string start;
            string end;
            if (!string.IsNullOrWhiteSpace(date))
            {
                string exactDate = ValidateIsoDateString(date!, "date");
                start = exactDate;
                end = exactDate;
            }
            else
            {
                start = string.IsNullOrWhiteSpace(startDate)
                    ? DateTime.Today.ToString("yyyy-MM-dd")
                    : ValidateIsoDateString(startDate!, "startDate");
                end = string.IsNullOrWhiteSpace(endDate)
                    ? DateTime.Today.AddDays(Math.Clamp(daysAhead, 1, 365)).ToString("yyyy-MM-dd")
                    : ValidateIsoDateString(endDate!, "endDate");
            }

            string path = $"funki/calendar/events?start={Uri.EscapeDataString(start)}&end={Uri.EscapeDataString(end)}";
            using var doc = await SendJsonAsync(HttpMethod.Get, path, null, environment);

            var events = new List<object>();
            if (doc.RootElement.TryGetProperty("data", out var arr) && arr.ValueKind == JsonValueKind.Array)
            {
                foreach (var ev in arr.EnumerateArray())
                {
                    events.Add(new
                    {
                        id = ev.TryGetProperty("id", out var id) ? id.GetString() : "",
                        title = McpSecurityGuard.ScrubSecrets(ev.TryGetProperty("title", out var t) ? t.GetString() : ""),
                        start = ev.TryGetProperty("start", out var st) ? st.GetString() : "",
                        end = ev.TryGetProperty("end", out var en) ? en.GetString() : "",
                        isAllDay = ev.TryGetProperty("is_all_day", out var ad) && ad.ValueKind == JsonValueKind.True,
                        category = ev.TryGetProperty("category", out var cat) ? cat.GetString() : "general",
                        priority = ev.TryGetProperty("priority", out var pr) ? pr.GetString() : "low",
                        isTask = ev.TryGetProperty("is_task", out var it) && it.ValueKind == JsonValueKind.True
                    });
                }
            }

            return new { start, end, count = events.Count, events };
        }

        public static async Task<object> CreateCalendarEventAsync(
            string title,
            string start,
            string? end = null,
            bool isAllDay = false,
            string category = "general",
            string priority = "low",
            string? description = null,
            string? environment = "active")
        {
            if (string.IsNullOrWhiteSpace(title) || string.IsNullOrWhiteSpace(start))
                throw new ArgumentException("Für 'calendar_create' sind 'title' und 'start' (ISO-Datum/Zeit) erforderlich.");

            var payload = new Dictionary<string, object?>
            {
                ["title"] = title.Trim(),
                ["start"] = ValidateIsoDateString(start, "start"),
                ["end"] = string.IsNullOrWhiteSpace(end) ? null : ValidateIsoDateString(end, "end"),
                ["is_all_day"] = isAllDay,
                ["category"] = category,
                ["priority"] = (priority == "high" || priority == "medium") ? priority : "low",
                ["description"] = description,
                ["send_email"] = false
            };

            using var doc = await SendJsonAsync(HttpMethod.Post, "funki/calendar/events", payload, environment);
            return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
        }

        public static async Task<object> ManageShoppingAsync(
            string action,
            string? itemId = null,
            string? name = null,
            int quantity = 1,
            string? statusFilter = null,
            string? environment = "active",
            string? category = null)
        {
            string act = (action ?? "list").Trim().ToLowerInvariant();
            switch (act)
            {
                case "list":
                {
                    string q = (statusFilter == "needed" || statusFilter == "stocked")
                        ? $"funki/shopping/items?status={statusFilter}"
                        : "funki/shopping/items";
                    using var doc = await SendJsonAsync(HttpMethod.Get, q, null, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }
                case "add":
                {
                    if (string.IsNullOrWhiteSpace(name))
                        throw new ArgumentException("Fuer 'add' ist 'name' erforderlich.");

                    string? resolvedCategoryId = await ResolveShoppingCategoryIdAsync(category, environment);
                    var payload = new Dictionary<string, object?>
                    {
                        ["name"] = name.Trim(),
                        ["quantity"] = Math.Max(1, quantity)
                    };
                    if (!string.IsNullOrWhiteSpace(resolvedCategoryId))
                    {
                        payload["shopping_category_id"] = resolvedCategoryId;
                        payload["category_id"] = resolvedCategoryId;
                    }
                    else if (!string.IsNullOrWhiteSpace(category))
                    {
                        payload["category"] = category.Trim();
                    }

                    using var doc = await SendJsonAsync(HttpMethod.Post, "funki/shopping/items", payload, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }
                case "toggle":
                {
                    ValidateSafeResourceId(itemId, "itemId");
                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/shopping/items/{Uri.EscapeDataString(itemId!)}/toggle", null, environment);
                    return JsonSerializer.Deserialize<object>(doc.RootElement.GetRawText())!;
                }
                case "delete":
                {
                    ValidateSafeResourceId(itemId, "itemId");
                    using var doc = await SendJsonAsync(HttpMethod.Delete, $"funki/shopping/items/{Uri.EscapeDataString(itemId!)}", null, environment);
                    return new { success = true, deletedItemId = itemId };
                }
                default:
                    throw new ArgumentException("Erlaubte shopping_manage Aktionen: list, add, toggle, delete.");
            }
        }

        private static async Task<string?> ResolveShoppingCategoryIdAsync(string? rawCategory, string? environment)
        {
            if (string.IsNullOrWhiteSpace(rawCategory)) return null;
            string trimmed = rawCategory.Trim();

            if (Guid.TryParse(trimmed, out _) || Regex.IsMatch(trimmed, @"^\d+$"))
            {
                return trimmed;
            }

            try
            {
                using var doc = await SendJsonAsync(HttpMethod.Get, "funki/shopping/items", null, environment);
                var root = doc.RootElement;

                string? TryFindInCategoriesArray(JsonElement arr)
                {
                    if (arr.ValueKind != JsonValueKind.Array) return null;
                    string? partialId = null;
                    foreach (var cat in arr.EnumerateArray())
                    {
                        string? catName = cat.TryGetProperty("name", out var nm) ? nm.GetString() : null;
                        string? catId = cat.TryGetProperty("id", out var idEl)
                            ? (idEl.ValueKind == JsonValueKind.Number ? idEl.GetRawText() : idEl.GetString())
                            : null;
                        if (string.IsNullOrWhiteSpace(catName) || string.IsNullOrWhiteSpace(catId)) continue;

                        if (string.Equals(catName.Trim(), trimmed, StringComparison.OrdinalIgnoreCase))
                            return catId;
                        if (partialId == null && catName.Contains(trimmed, StringComparison.OrdinalIgnoreCase))
                            partialId = catId;
                    }
                    return partialId;
                }

                if (root.TryGetProperty("categories", out var topCats))
                {
                    string? found = TryFindInCategoriesArray(topCats);
                    if (!string.IsNullOrWhiteSpace(found)) return found;
                }
                if (root.TryGetProperty("data", out var dataEl))
                {
                    if (dataEl.ValueKind == JsonValueKind.Object && dataEl.TryGetProperty("categories", out var nestedCats))
                    {
                        string? found = TryFindInCategoriesArray(nestedCats);
                        if (!string.IsNullOrWhiteSpace(found)) return found;
                    }
                    else if (dataEl.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var item in dataEl.EnumerateArray())
                        {
                            if (item.TryGetProperty("category", out var cObj) && cObj.ValueKind == JsonValueKind.Object)
                            {
                                string? cName = cObj.TryGetProperty("name", out var nm) ? nm.GetString() : null;
                                string? cId = cObj.TryGetProperty("id", out var idEl)
                                    ? (idEl.ValueKind == JsonValueKind.Number ? idEl.GetRawText() : idEl.GetString())
                                    : null;
                                if (!string.IsNullOrWhiteSpace(cName) && !string.IsNullOrWhiteSpace(cId) &&
                                    string.Equals(cName.Trim(), trimmed, StringComparison.OrdinalIgnoreCase))
                                {
                                    return cId;
                                }
                            }
                        }
                    }
                }

                // Fallback: probe dedicated shopping/categories endpoint if items payload had no matching category list
                using var catDoc = await SendJsonAsync(HttpMethod.Get, "funki/shopping/categories", null, environment);
                if (catDoc.RootElement.TryGetProperty("data", out var catData))
                {
                    string? found = TryFindInCategoriesArray(catData);
                    if (!string.IsNullOrWhiteSpace(found)) return found;
                }
            }
            catch { }

            return null;
        }

        private static string? NormalizeTaskStatus(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return null;
            return raw.Trim().ToLowerInvariant() switch
            {
                "open" or "offen" => "open",
                "waiting" or "warten" or "wartend" => "waiting",
                "in_progress" or "in bearbeitung" or "mine" or "meine_tickets" or "active" => "in_progress",
                "completed" or "done" or "erledigt" => "completed",
                _ => throw new ArgumentException($"Ungueltiger Ticket-Status '{raw}'. Erlaubt: open, waiting, in_progress, completed.")
            };
        }

        private static string? ValidateOptionalHmTimeString(string? raw, string paramName)
        {
            if (string.IsNullOrWhiteSpace(raw)) return null;
            string trimmed = raw.Trim();
            var m = Regex.Match(trimmed, @"^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$");
            if (!m.Success)
                throw new ArgumentException($"Ungueltiges Uhrzeit-Format fuer '{paramName}' (erwartet HH:MM).");
            return $"{int.Parse(m.Groups[1].Value):D2}:{int.Parse(m.Groups[2].Value):D2}";
        }

        private static string ValidateIsoDateString(string raw, string paramName)
        {
            string trimmed = raw.Trim();
            if (!SafeIsoDateRegex.IsMatch(trimmed))
                throw new ArgumentException($"Ungültiges ISO-Datumsformat für '{paramName}' (erwartet: YYYY-MM-DD oder ISO-8601).");
            return trimmed;
        }

        private static void ValidateSafeResourceId(string? id, string paramName)
        {
            if (string.IsNullOrWhiteSpace(id))
                throw new ArgumentException($"Parameter '{paramName}' ist erforderlich.");
            string trimmed = id.Trim();
            if (trimmed.Length > 128 || !SafeResourceIdRegex.IsMatch(trimmed))
                throw new UnauthorizedAccessException($"Ungültige Zeichen in '{paramName}' blockiert.");
        }
    }
}

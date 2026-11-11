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

        private static async Task<JsonDocument> SendJsonAsync(
            HttpMethod method,
            string relativePath,
            object? payload = null,
            string? environment = "active")
        {
            string baseUrl = McpSecurityGuard.ResolveApiBaseUrlForEnv(environment);
            if (!McpSecurityGuard.IsAllowedApiUrl(baseUrl, out string normalizedBase))
                throw new UnauthorizedAccessException("Nicht vertrauenswürdige API-Basis-URL blockiert (SSRF-Schutz).");

            string fullUrl = $"{normalizedBase}/{relativePath.TrimStart('/')}";
            using var req = new HttpRequestMessage(method, fullUrl);
            McpSecurityGuard.AttachZeroSecretAdminAuth(req, normalizedBase);

            if (payload != null)
            {
                string jsonBody = JsonSerializer.Serialize(payload);
                req.Content = new StringContent(jsonBody, new UTF8Encoding(false), "application/json");
            }

            using var resp = await Http.SendAsync(req);
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
                ("tresor", "Funkenwerk Tresor", "Funkenwerk Tresor", "FunkenwerkTresor.exe", "FunkenwerkTresor"),
                ("player", "Funkenwerk Player", "Funkenwerk Player", "FunkenwerkPlayer.exe", "FunkenwerkPlayer"),
                ("video", "Funkenwerk Video", "Funkenwerk Video", "FunkenwerkVideo.exe", "FunkenwerkVideo"),
                ("ftp", "Funkenwerk FTP", "Funkenwerk FTP", "FunkenwerkFtp.exe", "FunkenwerkFtp"),
                ("ssh", "Funkenwerk SSH", "Funkenwerk SSH", "FunkenwerkSsh.exe", "FunkenwerkSsh"),
                ("pdf", "Funkenwerk PDF", "Funkenwerk PDF", "FunkenwerkPdf.exe", "FunkenwerkPdf"),
                ("drucker", "Funkenwerk Drucker", "Funkenwerk Drucker", "FunkenwerkDrucker.exe", "FunkenwerkDrucker"),
                ("os", "Funkenwerk OS", "Funkenwerk OS", "FunkenwerkOS.exe", "FunkenwerkOS"),
                ("browser", "Funkenwerk Browser", "Funkenwerk Browser", "FunkenwerkBrowser.exe", "FunkenwerkBrowser")
            };

            var appsReport = new List<object>();
            foreach (var app in collectionApps)
            {
                string exePath = System.IO.Path.Combine(McpSecurityGuard.CollectionRootDir, app.SubFolder, app.ExeName);
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
            string? priority = null,
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

            var tasks = new List<object>();
            if (tasksDoc.RootElement.TryGetProperty("data", out var tArr) && tArr.ValueKind == JsonValueKind.Array)
            {
                foreach (var t in tArr.EnumerateArray())
                {
                    bool isCompleted = t.TryGetProperty("is_completed", out var ic) && ic.ValueKind == JsonValueKind.True;
                    if (onlyOpen && isCompleted) continue;

                    string prio = t.TryGetProperty("priority", out var pr) ? (pr.GetString() ?? "niedrig") : "niedrig";
                    if (!string.IsNullOrWhiteSpace(priority) && !string.Equals(prio, priority.Trim(), StringComparison.OrdinalIgnoreCase))
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
                        priority = prio,
                        isCompleted,
                        listName = lName,
                        parentId = t.TryGetProperty("parent_id", out var pid) && pid.ValueKind == JsonValueKind.String ? pid.GetString() : null,
                        relevantFrom = t.TryGetProperty("relevant_from", out var rf) && rf.ValueKind == JsonValueKind.String ? rf.GetString() : null
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
            string? listIdOrAlias = "default",
            string? listName = null,
            string? relevantFrom = null,
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
                        throw new ArgumentException("Für 'create' ist 'title' erforderlich.");

                    string cleanList = string.IsNullOrWhiteSpace(listIdOrAlias) ? "default" : listIdOrAlias.Trim();
                    ValidateSafeResourceId(cleanList, "listIdOrAlias");
                    string targetList = Uri.EscapeDataString(cleanList);
                    var body = new Dictionary<string, object?>
                    {
                        ["title"] = title.Trim(),
                        ["priority"] = ValidatePriority(priority) ?? "niedrig",
                        ["list_name"] = listName,
                        ["relevant_from"] = string.IsNullOrWhiteSpace(relevantFrom) ? null : ValidateIsoDateString(relevantFrom, "relevantFrom"),
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
                    if (!string.IsNullOrWhiteSpace(priority)) body["priority"] = ValidatePriority(priority);
                    if (isCompleted.HasValue) body["is_completed"] = isCompleted.Value;
                    if (!string.IsNullOrWhiteSpace(relevantFrom)) body["relevant_from"] = ValidateIsoDateString(relevantFrom, "relevantFrom");
                    if (notes != null) body["ai_plan"] = notes;

                    using var doc = await SendJsonAsync(HttpMethod.Put, $"funki/tasks/{Uri.EscapeDataString(taskId!)}", body, environment);
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
                    throw new ArgumentException($"Unbekannte Aufgaben-Aktion '{action}'. Erlaubt: create, update, toggle, subtask, delete.");
            }
        }

        public static async Task<object> ListCalendarEventsAsync(
            string? startDate = null,
            string? endDate = null,
            int daysAhead = 14,
            string? environment = "active")
        {
            string start = string.IsNullOrWhiteSpace(startDate)
                ? DateTime.Today.ToString("yyyy-MM-dd")
                : ValidateIsoDateString(startDate!, "startDate");
            string end = string.IsNullOrWhiteSpace(endDate)
                ? DateTime.Today.AddDays(Math.Clamp(daysAhead, 1, 365)).ToString("yyyy-MM-dd")
                : ValidateIsoDateString(endDate!, "endDate");

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
            string? environment = "active")
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
                        throw new ArgumentException("Für 'add' ist 'name' erforderlich.");
                    using var doc = await SendJsonAsync(HttpMethod.Post, "funki/shopping/items", new { name = name.Trim(), quantity = Math.Max(1, quantity) }, environment);
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

        private static string? ValidatePriority(string? p)
        {
            if (string.IsNullOrWhiteSpace(p)) return null;
            string low = p.Trim().ToLowerInvariant();
            return low switch
            {
                "hoch" or "high" => "hoch",
                "mittel" or "medium" => "mittel",
                _ => "niedrig"
            };
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

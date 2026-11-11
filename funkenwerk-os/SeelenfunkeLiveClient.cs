using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.Authentication;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace FunkenwerkOS
{
    public sealed class OsLoginResult
    {
        public bool Success { get; set; }
        public bool TwoFactorRequired { get; set; }
        public string Message { get; set; } = "";
    }

    public static class SeelenfunkeLiveClient
    {
        private static readonly CultureInfo DeCulture = new("de-DE");

        private static readonly string[] AllowedHosts =
        {
            "127.0.0.1",
            "localhost",
            "seelenfunke.test",
            "stage.mein-seelenfunke.de",
            "mein-seelenfunke.de"
        };

        private static readonly HttpClient Http = CreateHardenedHttpClient();

        private static HttpClient CreateHardenedHttpClient()
        {
            var handler = new SocketsHttpHandler
            {
                AllowAutoRedirect = false,
                UseProxy = false,
                PooledConnectionLifetime = TimeSpan.FromMinutes(3),
                ConnectTimeout = TimeSpan.FromSeconds(8),
                SslOptions =
                {
                    EnabledSslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13
                }
            };

            return new HttpClient(handler)
            {
                Timeout = TimeSpan.FromSeconds(14),
                MaxResponseContentBufferSize = 4L * 1024L * 1024L
            };
        }

        public static string GetBaseUrlForEnvironment(string? env)
        {
            string norm = (env ?? "live").Trim().ToLowerInvariant();
            return norm switch
            {
                "local" => "http://127.0.0.1/api",
                "stage" => "https://stage.mein-seelenfunke.de/api",
                _ => "https://mein-seelenfunke.de/api"
            };
        }

        public static bool IsTrustedApiUrl(string rawUrl, out string normalizedBase)
        {
            normalizedBase = "";
            if (string.IsNullOrWhiteSpace(rawUrl)) return false;
            if (!Uri.TryCreate(rawUrl.Trim(), UriKind.Absolute, out Uri? uri)) return false;
            if (!string.IsNullOrEmpty(uri.UserInfo)) return false;

            bool hostAllowed = AllowedHosts.Any(h => string.Equals(h, uri.Host, StringComparison.OrdinalIgnoreCase));
            if (!hostAllowed) return false;

            bool isLocal = uri.Host.Equals("127.0.0.1", StringComparison.OrdinalIgnoreCase)
                        || uri.Host.Equals("localhost", StringComparison.OrdinalIgnoreCase)
                        || uri.Host.Equals("seelenfunke.test", StringComparison.OrdinalIgnoreCase);

            if (!isLocal && !uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                return false;

            if (isLocal && !uri.Scheme.Equals("http", StringComparison.OrdinalIgnoreCase) && !uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                return false;

            normalizedBase = uri.GetLeftPart(UriPartial.Authority) + uri.AbsolutePath.TrimEnd('/');
            return true;
        }

        public static async Task<OsLoginResult> LoginAsync(OsSettings settings, string env, string email, string password, string? twoFactorCode)
        {
            string baseUrl = GetBaseUrlForEnvironment(env);
            if (!IsTrustedApiUrl(baseUrl, out string safeBase))
                return new OsLoginResult { Success = false, Message = "Ungültige Ziel-URL blockiert." };

            try
            {
                var payload = new Dictionary<string, string>
                {
                    ["email"] = email.Trim(),
                    ["password"] = password,
                    ["device_name"] = $"FunkenwerkOS ({Environment.MachineName})"
                };

                if (!string.IsNullOrWhiteSpace(twoFactorCode))
                    payload["two_factor_code"] = twoFactorCode.Trim();

                using var req = new HttpRequestMessage(HttpMethod.Post, $"{safeBase}/login");
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                req.Content = new StringContent(JsonSerializer.Serialize(payload), new UTF8Encoding(false), "application/json");

                using var resp = await Http.SendAsync(req);
                string body = await resp.Content.ReadAsStringAsync();

                using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
                var root = doc.RootElement;

                if (resp.IsSuccessStatusCode && root.TryGetProperty("token", out var tokProp))
                {
                    string token = tokProp.GetString() ?? "";
                    if (!string.IsNullOrWhiteSpace(token))
                    {
                        OsVaultAndStorage.StoreBearerTokenUnified(settings, env, token);
                        return new OsLoginResult { Success = true, Message = "Verbunden" };
                    }
                }

                if (root.TryGetProperty("status", out var stProp) && string.Equals(stProp.GetString(), "two_factor_required", StringComparison.OrdinalIgnoreCase))
                {
                    return new OsLoginResult { Success = false, TwoFactorRequired = true, Message = "Bitte 2FA-Code eingeben." };
                }

                string msg = root.TryGetProperty("message", out var mProp) ? (mProp.GetString() ?? "Anmeldung fehlgeschlagen.") : $"HTTP {(int)resp.StatusCode}";
                return new OsLoginResult { Success = false, Message = msg };
            }
            catch (Exception ex)
            {
                return new OsLoginResult { Success = false, Message = $"Netzwerkfehler: {ex.Message}" };
            }
        }

        public static async Task<OsDashboardSnapshot> FetchDashboardSnapshotAsync(OsSettings settings)
        {
            var snap = new OsDashboardSnapshot();
            string env = settings.ApiEnvironment;
            string baseUrl = GetBaseUrlForEnvironment(env);
            if (!IsTrustedApiUrl(baseUrl, out string safeBase))
            {
                snap.StatusSummary = "Ungültige API";
                return snap;
            }

            string bearer = OsVaultAndStorage.ResolveBearerToken(settings, env);
            if (string.IsNullOrWhiteSpace(bearer))
            {
                snap.StatusSummary = "Anmeldung erforderlich";
                return snap;
            }

            var sw = Stopwatch.StartNew();
            try
            {
                var ordersTask = SendGetAsync($"{safeBase}/shop/orders?status=all", bearer);
                var kpisTask = SendGetAsync($"{safeBase}/funki/financials/kpis", bearer);
                var tasksTask = SendGetAsync($"{safeBase}/funki/tasks", bearer);

                await Task.WhenAll(ordersTask, kpisTask, tasksTask);
                sw.Stop();
                snap.LatencyMs = sw.ElapsedMilliseconds;

                string? ordersBody = ordersTask.Result;
                string? kpisBody = kpisTask.Result;
                string? tasksBody = tasksTask.Result;

                if (ordersBody == null && kpisBody == null && tasksBody == null)
                {
                    snap.IsConnected = false;
                    snap.StatusSummary = "Offline / Token prüfen";
                    return snap;
                }

                snap.IsConnected = true;
                snap.StatusSummary = $"{env.ToUpperInvariant()} · {snap.LatencyMs} ms";

                if (!string.IsNullOrWhiteSpace(ordersBody))
                    ParseOrdersPayload(ordersBody, snap);

                if (!string.IsNullOrWhiteSpace(kpisBody))
                    ParseFinancialKpisPayload(kpisBody, snap);

                if (!string.IsNullOrWhiteSpace(tasksBody))
                    ParseTasksPayload(tasksBody, snap);
            }
            catch
            {
                snap.IsConnected = false;
                snap.StatusSummary = "Verbindung unterbrochen";
            }

            return snap;
        }

        public static async Task<bool> ToggleTaskAsync(OsSettings settings, int taskId, bool isCompleted)
        {
            string baseUrl = GetBaseUrlForEnvironment(settings.ApiEnvironment);
            if (!IsTrustedApiUrl(baseUrl, out string safeBase)) return false;
            string bearer = OsVaultAndStorage.ResolveBearerToken(settings, settings.ApiEnvironment);
            if (string.IsNullOrWhiteSpace(bearer)) return false;

            try
            {
                using var req = new HttpRequestMessage(HttpMethod.Put, $"{safeBase}/funki/tasks/{taskId}");
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                string json = JsonSerializer.Serialize(new { is_completed = isCompleted });
                req.Content = new StringContent(json, new UTF8Encoding(false), "application/json");
                using var resp = await Http.SendAsync(req);
                return resp.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        public static async Task<bool> CreateTaskAsync(OsSettings settings, string title)
        {
            if (string.IsNullOrWhiteSpace(title)) return false;
            string baseUrl = GetBaseUrlForEnvironment(settings.ApiEnvironment);
            if (!IsTrustedApiUrl(baseUrl, out string safeBase)) return false;
            string bearer = OsVaultAndStorage.ResolveBearerToken(settings, settings.ApiEnvironment);
            if (string.IsNullOrWhiteSpace(bearer)) return false;

            try
            {
                using var req = new HttpRequestMessage(HttpMethod.Post, $"{safeBase}/funki/tasks");
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                string json = JsonSerializer.Serialize(new { title = title.Trim(), priority = "medium" });
                req.Content = new StringContent(json, new UTF8Encoding(false), "application/json");
                using var resp = await Http.SendAsync(req);
                return resp.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private static async Task<string?> SendGetAsync(string url, string bearer)
        {
            try
            {
                using var req = new HttpRequestMessage(HttpMethod.Get, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                using var resp = await Http.SendAsync(req);
                if (!resp.IsSuccessStatusCode) return null;
                return await resp.Content.ReadAsStringAsync();
            }
            catch
            {
                return null;
            }
        }

        private static void ParseOrdersPayload(string json, OsDashboardSnapshot snap)
        {
            try
            {
                using var doc = JsonDocument.Parse(json);
                var root = doc.RootElement;

                if (root.TryGetProperty("priority_order", out var prioEl) && prioEl.ValueKind == JsonValueKind.Object)
                {
                    int cents = prioEl.TryGetProperty("total_price", out var tp) && tp.TryGetInt32(out int cVal) ? cVal : 0;
                    snap.PriorityOrder = new OsPriorityOrderDto
                    {
                        HasPriorityOrder = true,
                        OrderNumber = GetStr(prioEl, "order_number", "Auftrag"),
                        CustomerName = GetStr(prioEl, "customer_name", ""),
                        ItemCount = prioEl.TryGetProperty("item_count", out var ic) && ic.TryGetInt32(out int iVal) ? iVal : 1,
                        IsExpress = prioEl.TryGetProperty("is_express", out var ex) && ex.ValueKind == JsonValueKind.True,
                        TotalFormatted = FormatEuroFromCents(cents),
                        TipSummary = GetStr(prioEl, "priority_tip", "").Split('\n')[0].Trim()
                    };
                }

                JsonElement ordersArr = default;
                bool hasArr = false;
                if (root.TryGetProperty("orders", out var ordersNode))
                {
                    if (ordersNode.ValueKind == JsonValueKind.Object && ordersNode.TryGetProperty("data", out var dataArr) && dataArr.ValueKind == JsonValueKind.Array)
                    {
                        ordersArr = dataArr;
                        hasArr = true;
                    }
                    else if (ordersNode.ValueKind == JsonValueKind.Array)
                    {
                        ordersArr = ordersNode;
                        hasArr = true;
                    }
                }
                else if (root.TryGetProperty("data", out var rootData) && rootData.ValueKind == JsonValueKind.Array)
                {
                    ordersArr = rootData;
                    hasArr = true;
                }

                if (hasArr)
                {
                    int openCount = 0;
                    int expressCount = 0;
                    foreach (var o in ordersArr.EnumerateArray())
                    {
                        string rawStatus = GetStr(o, "status", "pending").ToLowerInvariant();
                        bool isClosed = rawStatus is "completed" or "cancelled" or "refunded" or "shipped";
                        bool isExpress = o.TryGetProperty("is_express", out var exProp) && exProp.ValueKind == JsonValueKind.True;

                        if (!isClosed)
                        {
                            openCount++;
                            if (isExpress) expressCount++;
                        }

                        if (snap.ActiveOrders.Count < 14)
                        {
                            int cents = o.TryGetProperty("total_price", out var tp) && tp.TryGetInt32(out int cVal) ? cVal : 0;
                            snap.ActiveOrders.Add(new OsOrderItemDto
                            {
                                Id = o.TryGetProperty("id", out var idProp) && idProp.TryGetInt32(out int idVal) ? idVal : 0,
                                OrderNumber = GetStr(o, "order_number", "#"),
                                CustomerName = GetStr(o, "customer_name", ""),
                                StatusLabel = TranslateOrderStatus(rawStatus),
                                StatusBadgeHex = GetStatusHex(rawStatus),
                                ItemCount = o.TryGetProperty("item_count", out var ic) && ic.TryGetInt32(out int icVal) ? icVal : 1,
                                TotalFormatted = FormatEuroFromCents(cents),
                                IsExpress = isExpress
                            });
                        }
                    }

                    snap.OpenOrdersCount = openCount;
                    snap.ExpressOrdersCount = expressCount;
                }
            }
            catch { }
        }

        private static void ParseFinancialKpisPayload(string json, OsDashboardSnapshot snap)
        {
            try
            {
                using var doc = JsonDocument.Parse(json);
                var root = doc.RootElement;
                double rev = GetDouble(root, "shop_revenue");
                double avail = GetDouble(root, "available");
                snap.ShopRevenueFormatted = rev.ToString("N2", DeCulture) + " €";
                snap.AvailableLiquidityFormatted = avail.ToString("N2", DeCulture) + " €";
                snap.MonthLabel = GetStr(root, "month_label", "");
            }
            catch { }
        }

        private static void ParseTasksPayload(string json, OsDashboardSnapshot snap)
        {
            try
            {
                using var doc = JsonDocument.Parse(json);
                var root = doc.RootElement;

                JsonElement arr = default;
                if (root.ValueKind == JsonValueKind.Array)
                    arr = root;
                else if (root.TryGetProperty("data", out var dProp) && dProp.ValueKind == JsonValueKind.Array)
                    arr = dProp;
                else
                    return;

                int open = 0;
                foreach (var t in arr.EnumerateArray())
                {
                    bool done = (t.TryGetProperty("is_completed", out var cProp) && (cProp.ValueKind == JsonValueKind.True || (cProp.ValueKind == JsonValueKind.Number && cProp.GetInt32() == 1)))
                             || (t.TryGetProperty("completed", out var c2) && c2.ValueKind == JsonValueKind.True);

                    if (!done)
                    {
                        open++;
                        if (snap.OpenTasks.Count < 18)
                        {
                            string prio = GetStr(t, "priority", "medium").ToLowerInvariant();
                            snap.OpenTasks.Add(new OsTaskItemDto
                            {
                                Id = t.TryGetProperty("id", out var idP) && idP.TryGetInt32(out int tid) ? tid : 0,
                                Title = GetStr(t, "title", "Aufgabe"),
                                IsCompleted = false,
                                PriorityBadge = prio switch { "high" or "urgent" => "HOCH", "low" => "NIEDRIG", _ => "NORMAL" },
                                PriorityColorHex = prio switch { "high" or "urgent" => "#EF4444", "low" => "#6B7280", _ => "#C5A059" }
                            });
                        }
                    }
                }
                snap.OpenTasksCount = open;
            }
            catch { }
        }

        private static string TranslateOrderStatus(string raw) => raw switch
        {
            "pending" => "Offen",
            "processing" => "Produktion",
            "completed" => "Abgeschlossen",
            "shipped" => "Versendet",
            "cancelled" => "Storniert",
            "refunded" => "Erstattet",
            _ => raw
        };

        private static string GetStatusHex(string raw) => raw switch
        {
            "pending" => "#F59E0B",
            "processing" => "#C5A059",
            "completed" or "shipped" => "#10B981",
            "cancelled" or "refunded" => "#6B7280",
            _ => "#9CA3AF"
        };

        private static string FormatEuroFromCents(int cents) => (cents / 100.0).ToString("N2", DeCulture) + " €";

        private static string GetStr(JsonElement el, string prop, string fallback)
        {
            if (el.TryGetProperty(prop, out var p) && p.ValueKind == JsonValueKind.String)
                return p.GetString() ?? fallback;
            return fallback;
        }

        private static double GetDouble(JsonElement el, string prop)
        {
            if (el.TryGetProperty(prop, out var p))
            {
                if (p.ValueKind == JsonValueKind.Number && p.TryGetDouble(out double d)) return d;
                if (p.ValueKind == JsonValueKind.String && double.TryParse(p.GetString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double ds)) return ds;
            }
            return 0.0;
        }
    }
}

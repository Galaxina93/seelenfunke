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
            "192.168.188.119",
            "seelenfunke.test",
            "stage.mein-seelenfunke.de",
            "mein-seelenfunke.de"
        };

        private static string s_preferredLocalBaseUrl = "http://127.0.0.1/api";

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

            bool isLanDev = uri.Host.StartsWith("192.168.188.", StringComparison.OrdinalIgnoreCase);
            bool hostAllowed = isLanDev || AllowedHosts.Any(h => string.Equals(h, uri.Host, StringComparison.OrdinalIgnoreCase));
            if (!hostAllowed) return false;

            bool isLocal = isLanDev
                        || uri.Host.Equals("127.0.0.1", StringComparison.OrdinalIgnoreCase)
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

        public static Task<OsDashboardSnapshot> FetchDashboardSnapshotAsync(OsSettings settings)
        {
            return Task.Run(async () =>
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

                    await Task.WhenAll(ordersTask, kpisTask, tasksTask).ConfigureAwait(false);
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
            });
        }

        public static async Task<OsDashboardSnapshot> FetchOrdersSnapshotForEnvAsync(OsSettings settings, string env)
        {
            var snap = new OsDashboardSnapshot();
            string normEnv = (env ?? "live").Trim().ToLowerInvariant();
            string baseUrl = GetBaseUrlForEnvironment(normEnv);
            if (!IsTrustedApiUrl(baseUrl, out string safeBase))
            {
                snap.StatusSummary = "Ungültige API";
                return snap;
            }

            string bearer = OsVaultAndStorage.ResolveBearerToken(settings, normEnv);
            if (string.IsNullOrWhiteSpace(bearer))
            {
                snap.StatusSummary = "Anmeldung erforderlich";
                return snap;
            }

            var sw = Stopwatch.StartNew();
            try
            {
                string? ordersBody = normEnv == "local"
                    ? await SendGetLocalWithLanFallbackAsync("/shop/orders?status=all", bearer).ConfigureAwait(false)
                    : await SendGetAsync($"{safeBase}/shop/orders?status=all", bearer).ConfigureAwait(false);
                sw.Stop();
                snap.LatencyMs = sw.ElapsedMilliseconds;

                if (string.IsNullOrWhiteSpace(ordersBody))
                {
                    snap.IsConnected = false;
                    snap.StatusSummary = "Offline / Token prüfen";
                    return snap;
                }

                snap.IsConnected = true;
                snap.StatusSummary = $"{normEnv.ToUpperInvariant()} · {snap.LatencyMs} ms";
                ParseOrdersPayload(ordersBody, snap);
            }
            catch
            {
                snap.IsConnected = false;
                snap.StatusSummary = "Verbindung unterbrochen";
            }

            return snap;
        }

        public static Task<OsDashboardSnapshot> FetchActiveEnvOrdersPulseAsync(OsSettings settings)
        {
            return Task.Run(async () =>
            {
                string primaryEnv = (settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant();
                return await FetchOrdersSnapshotForEnvAsync(settings, primaryEnv).ConfigureAwait(false);
            });
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

        private static async Task<string?> SendGetLocalWithLanFallbackAsync(string relativePath, string bearer)
        {
            string primaryLocal = s_preferredLocalBaseUrl;
            string fallbackLocal = string.Equals(primaryLocal, "http://127.0.0.1/api", StringComparison.OrdinalIgnoreCase)
                ? "http://192.168.188.119/api"
                : "http://127.0.0.1/api";

            string? body = await SendGetAsync($"{primaryLocal}{relativePath}", bearer, timeoutSec: 2.5).ConfigureAwait(false);
            if (!string.IsNullOrWhiteSpace(body))
                return body;

            string? fallbackBody = await SendGetAsync($"{fallbackLocal}{relativePath}", bearer, timeoutSec: 3.5).ConfigureAwait(false);
            if (!string.IsNullOrWhiteSpace(fallbackBody))
            {
                s_preferredLocalBaseUrl = fallbackLocal;
                return fallbackBody;
            }
            return null;
        }

        private static async Task<string?> SendGetAsync(string url, string bearer, double timeoutSec = 10.0)
        {
            try
            {
                using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(timeoutSec));
                using var req = new HttpRequestMessage(HttpMethod.Get, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                if (!resp.IsSuccessStatusCode) return null;
                return await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
            }
            catch
            {
                return null;
            }
        }

        private static int ComputeStablePositiveId(string key)
        {
            if (string.IsNullOrWhiteSpace(key)) return 0;
            if (int.TryParse(key, NumberStyles.Integer, CultureInfo.InvariantCulture, out int direct) && direct > 0)
                return direct;

            uint hash = 2166136261u;
            foreach (char c in key)
            {
                hash ^= c;
                hash *= 16777619u;
            }
            int pos = (int)(hash & 0x7FFFFFFFu);
            return pos == 0 ? 1 : pos;
        }

        private static void ExtractOrderIdentity(JsonElement el, out string orderKey, out int numericId)
        {
            orderKey = "";
            if (el.TryGetProperty("id", out var idProp))
            {
                if (idProp.ValueKind == JsonValueKind.String)
                    orderKey = idProp.GetString() ?? "";
                else if (idProp.ValueKind == JsonValueKind.Number && idProp.TryGetInt32(out int nVal))
                    orderKey = nVal.ToString(CultureInfo.InvariantCulture);
            }
            if (string.IsNullOrWhiteSpace(orderKey))
                orderKey = GetStr(el, "order_number", "");

            numericId = ComputeStablePositiveId(orderKey);
        }

        private static int GetSafeInt32(JsonElement obj, string propName, int fallback = 0)
        {
            if (obj.TryGetProperty(propName, out var p))
            {
                if (p.ValueKind == JsonValueKind.Number && p.TryGetInt32(out int v))
                    return v;
                if (p.ValueKind == JsonValueKind.String && int.TryParse(p.GetString(), NumberStyles.Integer, CultureInfo.InvariantCulture, out int sv))
                    return sv;
            }
            return fallback;
        }

        private static void ParseOrdersPayload(string json, OsDashboardSnapshot snap)
        {
            try
            {
                using var doc = JsonDocument.Parse(json);
                var root = doc.RootElement;

                int maxSeenId = 0;
                string maxSeenKey = "";
                snap.TotalOrdersCount = GetSafeInt32(root, "total", 0);

                if (root.TryGetProperty("priority_order", out var prioEl) && prioEl.ValueKind == JsonValueKind.Object)
                {
                    ExtractOrderIdentity(prioEl, out string pKey, out int pidVal);
                    if (!string.IsNullOrWhiteSpace(pKey))
                    {
                        if (!snap.AllPageOrderKeys.Contains(pKey, StringComparer.OrdinalIgnoreCase))
                            snap.AllPageOrderKeys.Add(pKey);
                        if (string.CompareOrdinal(pKey, maxSeenKey) > 0)
                            maxSeenKey = pKey;
                    }
                    if (pidVal > 0 && !snap.AllPageOrderIds.Contains(pidVal))
                        snap.AllPageOrderIds.Add(pidVal);

                    int cents = GetSafeInt32(prioEl, "total_price", 0);
                    snap.PriorityOrder = new OsPriorityOrderDto
                    {
                        HasPriorityOrder = true,
                        OrderNumber = GetStr(prioEl, "order_number", "Auftrag"),
                        CustomerName = GetStr(prioEl, "customer_name", ""),
                        ItemCount = GetSafeInt32(prioEl, "item_count", 1),
                        IsExpress = prioEl.TryGetProperty("is_express", out var ex) && ex.ValueKind == JsonValueKind.True,
                        TotalFormatted = FormatEuroFromCents(cents),
                        TipSummary = GetStr(prioEl, "priority_tip", "").Split('\n')[0].Trim()
                    };
                }

                JsonElement ordersArr = default;
                bool hasArr = false;
                if (root.TryGetProperty("orders", out var ordersNode))
                {
                    if (ordersNode.ValueKind == JsonValueKind.Object)
                    {
                        int otVal = GetSafeInt32(ordersNode, "total", -1);
                        if (otVal >= 0) snap.TotalOrdersCount = otVal;
                        if (ordersNode.TryGetProperty("data", out var dataArr) && dataArr.ValueKind == JsonValueKind.Array)
                        {
                            ordersArr = dataArr;
                            hasArr = true;
                        }
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
                        ExtractOrderIdentity(o, out string oKey, out int idVal);
                        if (!string.IsNullOrWhiteSpace(oKey))
                        {
                            if (!snap.AllPageOrderKeys.Contains(oKey, StringComparer.OrdinalIgnoreCase))
                                snap.AllPageOrderKeys.Add(oKey);
                            if (string.CompareOrdinal(oKey, maxSeenKey) > 0)
                                maxSeenKey = oKey;
                        }
                        if (idVal > 0)
                        {
                            if (!snap.AllPageOrderIds.Contains(idVal))
                                snap.AllPageOrderIds.Add(idVal);
                            if (idVal > maxSeenId)
                                maxSeenId = idVal;
                        }

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
                            int cents = GetSafeInt32(o, "total_price", 0);
                            snap.ActiveOrders.Add(new OsOrderItemDto
                            {
                                Id = idVal,
                                OrderUuid = oKey,
                                OrderNumber = GetStr(o, "order_number", "#"),
                                CustomerName = GetStr(o, "customer_name", ""),
                                StatusLabel = TranslateOrderStatus(rawStatus),
                                StatusBadgeHex = GetStatusHex(rawStatus),
                                ItemCount = GetSafeInt32(o, "item_count", 1),
                                TotalFormatted = FormatEuroFromCents(cents),
                                IsExpress = isExpress
                            });
                        }
                    }

                    snap.OpenOrdersCount = openCount;
                    snap.ExpressOrdersCount = expressCount;
                }

                int explicitOpen = GetSafeInt32(root, "open_orders_count", -1);
                if (explicitOpen >= 0)
                    snap.OpenOrdersCount = explicitOpen;

                if (root.TryGetProperty("latest_order_id", out var explicitLatestProp))
                {
                    if (explicitLatestProp.ValueKind == JsonValueKind.String)
                    {
                        string lStr = explicitLatestProp.GetString() ?? "";
                        if (!string.IsNullOrWhiteSpace(lStr) && string.CompareOrdinal(lStr, maxSeenKey) > 0)
                            maxSeenKey = lStr;
                    }
                    else if (explicitLatestProp.ValueKind == JsonValueKind.Number && explicitLatestProp.TryGetInt32(out int lInt) && lInt > 0)
                    {
                        maxSeenId = Math.Max(maxSeenId, lInt);
                    }
                }

                snap.LatestOrderKey = maxSeenKey;
                snap.LatestOrderId = !string.IsNullOrWhiteSpace(maxSeenKey) ? ComputeStablePositiveId(maxSeenKey) : maxSeenId;
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

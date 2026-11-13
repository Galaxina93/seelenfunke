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

        private static readonly System.Collections.Concurrent.ConcurrentDictionary<string, DateTime> s_lastMonitorScopeCheckUtc = new(StringComparer.OrdinalIgnoreCase);
        private static readonly System.Collections.Concurrent.ConcurrentDictionary<string, bool> s_monitorScopeVerified = new(StringComparer.OrdinalIgnoreCase);

        private static readonly HttpClient Http = CreateHardenedHttpClient();

        public static bool IsDedicatedMiniPcKiosk() =>
            System.IO.File.Exists(@"C:\Funkenwerk Collection\Funkenwerk OS\FunkenwerkOS.exe");

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
                    EnabledSslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13,
                    CertificateRevocationCheckMode = System.Security.Cryptography.X509Certificates.X509RevocationMode.Online
                }
            };

            return new HttpClient(handler)
            {
                Timeout = TimeSpan.FromSeconds(14),
                MaxResponseContentBufferSize = 2L * 1024L * 1024L
            };
        }

        public static string GetBaseUrlForEnvironment(string? env)
        {
            string norm = (env ?? "live").Trim().ToLowerInvariant();
            if (IsDedicatedMiniPcKiosk() && norm == "local")
                norm = "stage";

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

            bool isLoopback = uri.Host.Equals("127.0.0.1", StringComparison.OrdinalIgnoreCase)
                           || uri.Host.Equals("localhost", StringComparison.OrdinalIgnoreCase)
                           || uri.Host.Equals("seelenfunke.test", StringComparison.OrdinalIgnoreCase);

            // Security: Never allow plaintext HTTP on the Manufactory Mini-PC or to any non-loopback host!
            if (IsDedicatedMiniPcKiosk())
            {
                if (isLoopback || !uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                    return false;
            }
            else
            {
                if (!isLoopback && !uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                    return false;
                if (isLoopback && !uri.Scheme.Equals("http", StringComparison.OrdinalIgnoreCase) && !uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                    return false;
            }

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
                bool monitorOnly = IsDedicatedMiniPcKiosk();
                var payload = new Dictionary<string, string>
                {
                    ["email"] = email.Trim(),
                    ["password"] = password,
                    ["device_name"] = monitorOnly ? $"FunkenwerkMonitor-{Environment.MachineName}" : $"FunkenwerkOS ({Environment.MachineName})"
                };

                if (monitorOnly)
                    payload["scope"] = "monitor_only";

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
            if (IsDedicatedMiniPcKiosk() && normEnv == "local")
                normEnv = "stage";

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

            if (IsDedicatedMiniPcKiosk())
            {
                bearer = await TryEnsureMonitorOnlyTokenScopeAsync(settings, normEnv, safeBase, bearer).ConfigureAwait(false);
            }

            var sw = Stopwatch.StartNew();
            try
            {
                var (pulseStatus, pulseBody) = await SendGetWithStatusAsync($"{safeBase}/shop/monitor-pulse", bearer).ConfigureAwait(false);
                string? ordersBody = pulseBody;

                // Fallback only if server has not received /shop/monitor-pulse route deployment yet (HTTP 404)
                if (pulseStatus == 404 && string.IsNullOrWhiteSpace(ordersBody))
                {
                    ordersBody = await SendGetAsync($"{safeBase}/shop/orders?status=all", bearer).ConfigureAwait(false);
                }

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

        private static async Task<string> TryEnsureMonitorOnlyTokenScopeAsync(OsSettings settings, string normEnv, string safeBase, string currentBearer)
        {
            if (s_monitorScopeVerified.TryGetValue(normEnv, out bool verified) && verified)
                return currentBearer;

            DateTime nowUtc = DateTime.UtcNow;
            if (s_lastMonitorScopeCheckUtc.TryGetValue(normEnv, out DateTime lastCheck) && (nowUtc - lastCheck).TotalMinutes < 15.0)
                return currentBearer;

            s_lastMonitorScopeCheckUtc[normEnv] = nowUtc;

            try
            {
                using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(6));
                using var req = new HttpRequestMessage(HttpMethod.Post, $"{safeBase}/auth/downgrade-to-monitor-token");
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", currentBearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                string payload = JsonSerializer.Serialize(new { device_name = Environment.MachineName });
                req.Content = new StringContent(payload, new UTF8Encoding(false), "application/json");

                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                if (!resp.IsSuccessStatusCode)
                    return currentBearer;

                string body = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(body) ? "{}" : body);
                var root = doc.RootElement;
                string status = GetStr(root, "status", "");

                if (string.Equals(status, "already_scoped", StringComparison.OrdinalIgnoreCase))
                {
                    s_monitorScopeVerified[normEnv] = true;
                    return currentBearer;
                }

                if (string.Equals(status, "downgraded", StringComparison.OrdinalIgnoreCase))
                {
                    string newTok = GetStr(root, "token", "").Trim();
                    if (!string.IsNullOrWhiteSpace(newTok))
                    {
                        OsVaultAndStorage.StoreBearerTokenUnified(settings, normEnv, newTok, switchActiveEnv: false);
                        s_monitorScopeVerified[normEnv] = true;
                        return newTok;
                    }
                }
            }
            catch { }

            return currentBearer;
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

        private static async Task<(int StatusCode, string? Body)> SendGetWithStatusAsync(string url, string bearer, double timeoutSec = 10.0)
        {
            try
            {
                using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(timeoutSec));
                using var req = new HttpRequestMessage(HttpMethod.Get, url);
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                using var resp = await Http.SendAsync(req, cts.Token).ConfigureAwait(false);
                int status = (int)resp.StatusCode;
                if (!resp.IsSuccessStatusCode) return (status, null);
                string body = await resp.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                return (status, body);
            }
            catch
            {
                return (0, null);
            }
        }

        private static async Task<string?> SendGetAsync(string url, string bearer, double timeoutSec = 10.0)
        {
            var (_, body) = await SendGetWithStatusAsync(url, bearer, timeoutSec).ConfigureAwait(false);
            return body;
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

                var fallbackTopMap = new Dictionary<string, (string DisplayName, long TotalCents, int Count)>(StringComparer.OrdinalIgnoreCase);

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
                        bool isCancelledOrRefunded = rawStatus is "cancelled" or "refunded";
                        bool isExpress = o.TryGetProperty("is_express", out var exProp) && exProp.ValueKind == JsonValueKind.True;
                        int orderCents = GetSafeInt32(o, "total_price", 0);
                        string rawCustName = GetStr(o, "customer_name", "");

                        if (!isCancelledOrRefunded && !string.IsNullOrWhiteSpace(rawCustName))
                        {
                            string custKey = GetStr(o, "email", rawCustName).Trim();
                            string gdprName = AnonymizeCustomerNameGdpr(rawCustName);
                            if (!string.IsNullOrWhiteSpace(gdprName))
                            {
                                if (fallbackTopMap.TryGetValue(custKey, out var prev))
                                    fallbackTopMap[custKey] = (prev.DisplayName, prev.TotalCents + orderCents, prev.Count + 1);
                                else
                                    fallbackTopMap[custKey] = (gdprName, orderCents, 1);
                            }
                        }

                        if (!isClosed)
                        {
                            openCount++;
                            if (isExpress) expressCount++;
                        }

                        if (snap.ActiveOrders.Count < 14)
                        {
                            snap.ActiveOrders.Add(new OsOrderItemDto
                            {
                                Id = idVal,
                                OrderUuid = oKey,
                                OrderNumber = GetStr(o, "order_number", "#"),
                                CustomerName = rawCustName,
                                StatusLabel = TranslateOrderStatus(rawStatus),
                                StatusBadgeHex = GetStatusHex(rawStatus),
                                ItemCount = GetSafeInt32(o, "item_count", 1),
                                TotalFormatted = FormatEuroFromCents(orderCents),
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

                int explicitExpress = GetSafeInt32(root, "express_orders_count", -1);
                if (explicitExpress >= 0)
                    snap.ExpressOrdersCount = explicitExpress;

                int explicitCarts = GetSafeInt32(root, "open_carts_count", -1);
                if (explicitCarts >= 0)
                    snap.OpenCartsCount = explicitCarts;

                if (root.TryGetProperty("top_customers", out var topCustProp) && topCustProp.ValueKind == JsonValueKind.Array)
                {
                    foreach (var tc in topCustProp.EnumerateArray())
                    {
                        string rawDisp = tc.ValueKind == JsonValueKind.String
                            ? (tc.GetString() ?? "")
                            : GetStr(tc, "display_name", "");
                        string safeName = AnonymizeCustomerNameGdpr(rawDisp);
                        if (!string.IsNullOrWhiteSpace(safeName) && snap.TopCustomersGdpr.Count < 3)
                            snap.TopCustomersGdpr.Add(safeName);
                    }
                }

                if (snap.TopCustomersGdpr.Count == 0 && fallbackTopMap.Count > 0)
                {
                    foreach (var item in fallbackTopMap.Values
                        .OrderByDescending(x => x.TotalCents)
                        .ThenByDescending(x => x.Count)
                        .Take(3))
                    {
                        snap.TopCustomersGdpr.Add(item.DisplayName);
                    }
                }

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

        private static string AnonymizeCustomerNameGdpr(string raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return "";
            string[] parts = raw.Trim().Split(new[] { ' ', '\t' }, StringSplitOptions.RemoveEmptyEntries);
            if (parts.Length == 0) return "";
            string first = parts[0];
            if (first.Length > 18) first = first.Substring(0, 18);
            if (parts.Length == 1) return first;
            string last = parts[parts.Length - 1].Trim('.');
            if (string.IsNullOrEmpty(last)) return first;
            return $"{first} {char.ToUpperInvariant(last[0])}.";
        }

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

using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Sockets;
using System.Net.WebSockets;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows.Media;
using System.Windows.Media.Imaging;

namespace FunkenwerkMcp
{
    // =========================================================================
    // STAGE <-> WINDOWS DESKTOP FAILOVER LASER RELAY
    // Strictly passive (0 Stage polls) whenever Werkstatt Mini-PC (192.168.188.151:22)
    // is awake. Automatically takes over in the background (~2.5s) when the Mini-PC
    // sleeps in energy-saving mode, bridging Stage directly to 192.168.188.150:28900.
    // =========================================================================
    public static class McpStageLaserFailoverRelay
    {
        public const string MiniPcHost = "192.168.188.151";
        public const int MiniPcSshPort = 22;
        public const int MiniPcProbeTimeoutMs = 350;
        public const double MiniPcProbeCacheSeconds = 10.0;

        public const string DefaultLaserIp = "192.168.188.150";
        public const int DefaultV2Port = 28900;
        public const string DefaultParityToken = "2F18DD055BEAF251DF165868202036E8";
        public const string DefaultUserId = "mk-guest";
        public const string DefaultUserKey = "bWFrZWJsb2NrLXh0b29s";
        public const string DeviceExtId = "GS009-CLASS-4";
        public const string DeviceSnCode = "MXFK007003260530H0194A6";

        public const string PinnedStageApiBaseUrl = "https://stage.mein-seelenfunke.de/api";
        public const int DefaultDaemonIdleDelayMs = 2500;

        private static readonly Regex CalibPairRegex = new(@"\[(\d+),(\d+)\]", RegexOptions.Compiled);

        private static readonly HttpClient RelayHttp = new(new SocketsHttpHandler
        {
            AllowAutoRedirect = false,
            UseCookies = false,
            UseProxy = false,
            ConnectTimeout = TimeSpan.FromSeconds(5),
            PooledConnectionLifetime = TimeSpan.FromMinutes(2),
            SslOptions =
            {
                EnabledSslProtocols = SslProtocols.Tls12 | SslProtocols.Tls13
            }
        })
        {
            Timeout = TimeSpan.FromSeconds(24),
            MaxResponseContentBufferSize = 36L * 1024L * 1024L
        };

        private static readonly HttpClient LanCameraHttp = new(new SocketsHttpHandler
        {
            AllowAutoRedirect = false,
            UseCookies = false,
            UseProxy = false,
            ConnectTimeout = TimeSpan.FromMilliseconds(900),
            PooledConnectionLifetime = TimeSpan.FromSeconds(30)
        })
        {
            Timeout = TimeSpan.FromMilliseconds(2800),
            MaxResponseContentBufferSize = 20L * 1024L * 1024L
        };

        private static readonly object s_miniPcCacheLock = new();
        private static bool s_cachedMiniPcOnline;
        private static DateTime s_cachedMiniPcCheckedAtUtc = DateTime.MinValue;

        private static readonly object s_laserCacheLock = new();
        private static bool s_cachedLaserOnline;
        private static DateTime s_cachedLaserCheckedAtUtc = DateTime.MinValue;

        private static readonly object s_bootstrapLock = new();
        private static DateTime s_lastTokenBootstrapAttemptUtc = DateTime.MinValue;

        private static readonly ConcurrentDictionary<string, DateTime> s_recentlyHandledJobs = new(StringComparer.Ordinal);

        // Deterministic unit-test hooks (Self-Test #63)
        internal static Func<Task<bool>>? TestMiniPcProbeOverride { get; set; }
        internal static Func<string, int, int, Task<bool>>? TestLaserPortProbeOverride { get; set; }
        internal static Func<LaserRelayJobDto, Task<LaserRelayCompletionPayload>>? TestJobExecutorOverride { get; set; }
        internal static HttpClient? TestStageHttpClientOverride { get; set; }
        internal static string? TestStageBearerTokenOverride { get; set; }

        internal static void ResetCachesForTests()
        {
            lock (s_miniPcCacheLock)
            {
                s_cachedMiniPcOnline = false;
                s_cachedMiniPcCheckedAtUtc = DateTime.MinValue;
            }
            lock (s_laserCacheLock)
            {
                s_cachedLaserOnline = false;
                s_cachedLaserCheckedAtUtc = DateTime.MinValue;
            }
            lock (s_bootstrapLock)
            {
                s_lastTokenBootstrapAttemptUtc = DateTime.MinValue;
            }
            s_recentlyHandledJobs.Clear();
        }

        public sealed class LaserRelayJobDto
        {
            public string JobId { get; set; } = "";
            public string Action { get; set; } = "";
            public string LaserIp { get; set; } = DefaultLaserIp;
            public string LightMode { get; set; } = "normal";
            public double? FocusMm { get; set; }
            public string? XfBase64 { get; set; }
            public string? Gcode { get; set; }
            public double CenterXMm { get; set; } = 100.0;
            public double CenterYMm { get; set; } = 100.0;
            public double WidthMm { get; set; } = 80.0;
            public double HeightMm { get; set; } = 80.0;
            public double AngleDeg { get; set; } = 0.0;
        }

        public sealed class LaserRelayCompletionPayload
        {
            public string JobId { get; set; } = "";
            public bool Ok { get; set; }
            public bool LaserOnline { get; set; }
            public string State { get; set; } = "OFFLINE";
            public string? Error { get; set; }
            public string? WarpedJpegBase64 { get; set; }
            public string? RawJpegBase64 { get; set; }
            public double? FocusMm { get; set; }
            public double? RawZMm { get; set; }

            public JsonObject ToJsonObject()
            {
                var obj = new JsonObject
                {
                    ["job_id"] = JobId,
                    ["ok"] = Ok,
                    ["laser_online"] = LaserOnline,
                    ["state"] = State,
                    ["error"] = Error,
                    ["warped_jpeg_base64"] = WarpedJpegBase64,
                    ["raw_jpeg_base64"] = RawJpegBase64
                };
                if (FocusMm.HasValue) obj["focus_mm"] = Math.Round(FocusMm.Value, 2);
                if (RawZMm.HasValue) obj["raw_z_mm"] = Math.Round(RawZMm.Value, 2);
                return obj;
            }
        }

        public sealed class LaserCameraCaptureResult
        {
            public bool Ok { get; set; }
            public bool LaserOnline { get; set; }
            public byte[]? WarpedJpeg { get; set; }
            public byte[]? RawJpeg { get; set; }
            public string Source { get; set; } = "";
            public bool CalibratedGridUsed { get; set; }
            public string? Error { get; set; }
        }

        public sealed class CameraDiagnosticDto
        {
            [JsonPropertyName("ok")]
            public bool Ok { get; set; }

            [JsonPropertyName("laser_online")]
            public bool LaserOnline { get; set; }

            [JsonPropertyName("source")]
            public string Source { get; set; } = "";

            [JsonPropertyName("calibrated_grid_21x21")]
            public bool CalibratedGridUsed { get; set; }

            [JsonPropertyName("width_px")]
            public int WidthPx { get; set; }

            [JsonPropertyName("height_px")]
            public int HeightPx { get; set; }

            [JsonPropertyName("jpeg_bytes")]
            public int JpegBytes { get; set; }

            [JsonPropertyName("error")]
            public string? Error { get; set; }
        }

        public sealed class FailoverCycleReportDto
        {
            [JsonPropertyName("status")]
            public string Status { get; set; } = "ok";

            [JsonPropertyName("mode")]
            public string Mode { get; set; } = "passive_minipc_online";

            [JsonPropertyName("timestamp_utc")]
            public string TimestampUtc { get; set; } = DateTime.UtcNow.ToString("O");

            [JsonPropertyName("minipc_endpoint")]
            public string MiniPcEndpoint { get; set; } = $"{MiniPcHost}:{MiniPcSshPort}";

            [JsonPropertyName("minipc_online")]
            public bool MiniPcOnline { get; set; }

            [JsonPropertyName("laser_endpoint")]
            public string LaserEndpoint { get; set; } = $"{DefaultLaserIp}:{DefaultV2Port}";

            [JsonPropertyName("laser_online")]
            public bool LaserOnline { get; set; }

            [JsonPropertyName("polled_stage")]
            public bool PolledStage { get; set; }

            [JsonPropertyName("heartbeat_sent")]
            public bool HeartbeatSent { get; set; }

            [JsonPropertyName("stage_http_status")]
            public int StageHttpStatus { get; set; }

            [JsonPropertyName("token_bootstrapped")]
            public bool TokenBootstrapped { get; set; }

            [JsonPropertyName("claimed_job_id")]
            public string? ClaimedJobId { get; set; }

            [JsonPropertyName("claimed_action")]
            public string? ClaimedAction { get; set; }

            [JsonPropertyName("job_completed_ok")]
            public bool? JobCompletedOk { get; set; }

            [JsonPropertyName("job_result_state")]
            public string? JobResultState { get; set; }

            [JsonPropertyName("camera_test")]
            public CameraDiagnosticDto? CameraTest { get; set; }

            [JsonPropertyName("error")]
            public string? Error { get; set; }
        }

        // =====================================================================
        // 1. STRICT RFC1918 PRIVATE LAN IPv4 SSRF GUARD (1:1 WITH LINUX CHECK 45)
        // =====================================================================
        public static bool IsValidPrivateLanIpv4(string? host)
        {
            if (string.IsNullOrWhiteSpace(host)) return false;
            string trimmed = host.Trim();
            string[] parts = trimmed.Split('.');
            if (parts.Length != 4) return false;

            foreach (string part in parts)
            {
                if (part.Length == 0 || part.Length > 3 || !part.All(char.IsAsciiDigit))
                    return false;
                if (part.Length > 1 && part[0] == '0')
                    return false;
            }

            if (!IPAddress.TryParse(trimmed, out var ip) || ip.AddressFamily != AddressFamily.InterNetwork)
                return false;

            byte[] b = ip.GetAddressBytes();
            if (b.Length != 4) return false;
            if (b[0] == 10) return true;                                // 10.0.0.0/8
            if (b[0] == 172 && b[1] >= 16 && b[1] <= 31) return true;   // 172.16.0.0/12
            if (b[0] == 192 && b[1] == 168) return true;                // 192.168.0.0/16
            return false;
        }

        public static bool TryValidateRelayLaserIp(string? rawLaserIp, out string safeIp, out string? error)
        {
            if (string.IsNullOrWhiteSpace(rawLaserIp))
            {
                safeIp = DefaultLaserIp;
                error = null;
                return true;
            }

            string trimmed = rawLaserIp.Trim();
            if (!IsValidPrivateLanIpv4(trimmed))
            {
                safeIp = "";
                error = "Ungültige Laser-IP blockiert (SSRF-Schutz: nur private RFC1918 LAN-IPv4 erlaubt).";
                return false;
            }

            safeIp = trimmed;
            error = null;
            return true;
        }

        private static bool ValidatePinnedStageRelayUrl(string url)
        {
            if (!McpSecurityGuard.IsAllowedApiUrl(url, out _))
                return false;

            return Uri.TryCreate(url, UriKind.Absolute, out Uri? u)
                && u.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase)
                && u.Host.Equals("stage.mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase);
        }

        // =====================================================================
        // 2. FAST TCP PROBES (MINI-PC 192.168.188.151:22 PRIORITY & LASER :28900)
        // =====================================================================
        public static async Task<bool> ProbeTcpPortFastAsync(string ip, int port, int timeoutMs)
        {
            if (!IsValidPrivateLanIpv4(ip) || port <= 0 || port > 65535)
                return false;

            if (!IPAddress.TryParse(ip.Trim(), out var addr))
                return false;

            using var socket = new Socket(AddressFamily.InterNetwork, SocketType.Stream, ProtocolType.Tcp)
            {
                NoDelay = true
            };
            using var cts = new CancellationTokenSource(Math.Clamp(timeoutMs, 80, 1500));
            try
            {
                await socket.ConnectAsync(new IPEndPoint(addr, port), cts.Token).ConfigureAwait(false);
                return true;
            }
            catch
            {
                return false;
            }
        }

        public static async Task<bool> IsMiniPcAwakeAsync(bool forceRefresh = false)
        {
            DateTime now = DateTime.UtcNow;
            if (!forceRefresh)
            {
                lock (s_miniPcCacheLock)
                {
                    if ((now - s_cachedMiniPcCheckedAtUtc).TotalSeconds < MiniPcProbeCacheSeconds)
                        return s_cachedMiniPcOnline;
                }
            }

            bool awake = TestMiniPcProbeOverride != null
                ? await TestMiniPcProbeOverride().ConfigureAwait(false)
                : await ProbeTcpPortFastAsync(MiniPcHost, MiniPcSshPort, MiniPcProbeTimeoutMs).ConfigureAwait(false);

            lock (s_miniPcCacheLock)
            {
                s_cachedMiniPcOnline = awake;
                s_cachedMiniPcCheckedAtUtc = DateTime.UtcNow;
            }
            return awake;
        }

        public static async Task<bool> ProbeLaserReachableAsync(string ip = DefaultLaserIp, int port = DefaultV2Port, int timeoutMs = 400)
        {
            if (!IsValidPrivateLanIpv4(ip) || port <= 0 || port > 65535)
                return false;

            if (TestLaserPortProbeOverride != null)
                return await TestLaserPortProbeOverride(ip.Trim(), port, timeoutMs).ConfigureAwait(false);

            return await ProbeTcpPortFastAsync(ip.Trim(), port, timeoutMs).ConfigureAwait(false);
        }

        public static async Task<bool> ProbeAnyLaserPortAsync(string ip = DefaultLaserIp, int timeoutMs = 400)
        {
            if (!IsValidPrivateLanIpv4(ip))
                return false;

            if (await ProbeLaserReachableAsync(ip, DefaultV2Port, timeoutMs).ConfigureAwait(false))
                return true;

            if (await ProbeLaserReachableAsync(ip, 8080, Math.Min(timeoutMs, 260)).ConfigureAwait(false))
                return true;

            return false;
        }

        public static async Task<bool> GetCachedLaserOnlineAsync(bool forceRefresh = false)
        {
            DateTime now = DateTime.UtcNow;
            if (!forceRefresh)
            {
                lock (s_laserCacheLock)
                {
                    if ((now - s_cachedLaserCheckedAtUtc).TotalSeconds < 8.0)
                        return s_cachedLaserOnline;
                }
            }

            bool online = await ProbeAnyLaserPortAsync(DefaultLaserIp, 750).ConfigureAwait(false);
            lock (s_laserCacheLock)
            {
                s_cachedLaserOnline = online;
                s_cachedLaserCheckedAtUtc = DateTime.UtcNow;
            }
            return online;
        }

        // =====================================================================
        // 3. POLL JSON DESERIALIZATION (1:1 PARITY WITH StageLaserRelayDaemon)
        // =====================================================================
        public static LaserRelayJobDto? TryDeserializePollResponse(string? rawJson)
        {
            if (string.IsNullOrWhiteSpace(rawJson))
                return null;

            try
            {
                using var doc = JsonDocument.Parse(rawJson, new JsonDocumentOptions { MaxDepth = 16 });
                var root = doc.RootElement;
                if (root.ValueKind != JsonValueKind.Object)
                    return null;

                JsonElement jobEl = default;
                bool found = false;

                if (root.TryGetProperty("job", out var directJob))
                {
                    if (directJob.ValueKind is JsonValueKind.Null or JsonValueKind.Undefined)
                        return null;
                    if (directJob.ValueKind == JsonValueKind.Object)
                    {
                        jobEl = directJob;
                        found = true;
                    }
                }
                else if (root.TryGetProperty("data", out var dataEl) && dataEl.ValueKind == JsonValueKind.Object)
                {
                    if (dataEl.TryGetProperty("job", out var nestedJob))
                    {
                        if (nestedJob.ValueKind != JsonValueKind.Object)
                            return null;
                        jobEl = nestedJob;
                        found = true;
                    }
                    else if (dataEl.TryGetProperty("job_id", out _))
                    {
                        jobEl = dataEl;
                        found = true;
                    }
                }
                else if (root.TryGetProperty("job_id", out _))
                {
                    jobEl = root;
                    found = true;
                }

                if (!found)
                    return null;

                string jobId = ReadJsonString(jobEl, "job_id", "id");
                string action = ReadJsonString(jobEl, "action", "type").Trim().ToLowerInvariant();
                if (string.IsNullOrWhiteSpace(jobId) || string.IsNullOrWhiteSpace(action))
                    return null;

                string rawIp = ReadJsonString(jobEl, "laser_ip", "ip");
                string rawLightMode = NormalizeCameraLightMode(ReadJsonString(jobEl, "light_mode", "lightMode"));
                double? focusMm = ReadJsonNullableDouble(jobEl, "focus_mm", "focusMm");
                string? xfB64 = ReadJsonNullableString(jobEl, "xf_base64", "xfBase64");
                string? gcode = ReadJsonNullableString(jobEl, "gcode", "border_gcode");

                return new LaserRelayJobDto
                {
                    JobId = jobId.Trim(),
                    Action = action,
                    LaserIp = rawIp,
                    LightMode = rawLightMode,
                    FocusMm = focusMm,
                    XfBase64 = xfB64,
                    Gcode = gcode,
                    CenterXMm = ReadJsonDouble(jobEl, 100.0, "center_x_mm", "centerXMm"),
                    CenterYMm = ReadJsonDouble(jobEl, 100.0, "center_y_mm", "centerYMm"),
                    WidthMm = ReadJsonDouble(jobEl, 80.0, "width_mm", "widthMm"),
                    HeightMm = ReadJsonDouble(jobEl, 80.0, "height_mm", "heightMm"),
                    AngleDeg = ReadJsonDouble(jobEl, 0.0, "angle_deg", "angleDeg")
                };
            }
            catch
            {
                return null;
            }
        }

        public static string NormalizeCameraLightMode(string? raw)
        {
            string k = (raw ?? "").Trim().ToLowerInvariant();
            return k switch
            {
                "anti_glare" or "off" or "aus" or "led_off" => "anti_glare",
                "dim" or "dimmed" or "low" or "10%" => "dim",
                _ => "normal"
            };
        }

        private static double? ReadJsonNullableDouble(JsonElement obj, params string[] keys)
        {
            foreach (string k in keys)
            {
                if (obj.TryGetProperty(k, out var el))
                {
                    if (el.ValueKind == JsonValueKind.Number && el.TryGetDouble(out double dv) && double.IsFinite(dv))
                        return dv;
                    if (el.ValueKind == JsonValueKind.String &&
                        double.TryParse(el.GetString(), NumberStyles.Float, CultureInfo.InvariantCulture, out double sv) &&
                        double.IsFinite(sv))
                    {
                        return sv;
                    }
                }
            }
            return null;
        }

        private static string ReadJsonString(JsonElement obj, params string[] keys)
        {
            foreach (string k in keys)
            {
                if (obj.TryGetProperty(k, out var el))
                {
                    if (el.ValueKind == JsonValueKind.String)
                        return el.GetString() ?? "";
                    if (el.ValueKind is JsonValueKind.Number or JsonValueKind.True or JsonValueKind.False)
                        return el.GetRawText();
                }
            }
            return "";
        }

        private static string? ReadJsonNullableString(JsonElement obj, params string[] keys)
        {
            foreach (string k in keys)
            {
                if (obj.TryGetProperty(k, out var el) && el.ValueKind == JsonValueKind.String)
                {
                    string? s = el.GetString();
                    if (!string.IsNullOrEmpty(s))
                        return s;
                }
            }
            return null;
        }

        private static double ReadJsonDouble(JsonElement obj, double fallback, params string[] keys)
        {
            foreach (string k in keys)
            {
                if (obj.TryGetProperty(k, out var el))
                {
                    if (el.ValueKind == JsonValueKind.Number && el.TryGetDouble(out double dv) && double.IsFinite(dv))
                        return dv;
                    if (el.ValueKind == JsonValueKind.String &&
                        double.TryParse(el.GetString(), NumberStyles.Float, CultureInfo.InvariantCulture, out double sv) &&
                        double.IsFinite(sv))
                    {
                        return sv;
                    }
                }
            }
            return fallback;
        }

        // =====================================================================
        // 4. XTOOL F2 ULTRA UV NATIVE V2 BINARY PROTOCOL (0xBABE, WSS :28900)
        // =====================================================================
        public static ushort ComputeCrc16Arc(ReadOnlySpan<byte> data)
        {
            ushort crc = 0;
            for (int i = 0; i < data.Length; i++)
            {
                crc ^= data[i];
                for (int j = 0; j < 8; j++)
                {
                    crc = (crc & 1) != 0 ? (ushort)((crc >> 1) ^ 0xA001) : (ushort)(crc >> 1);
                }
            }
            return crc;
        }

        public static byte[] EncodeV2Frame(ReadOnlySpan<byte> payload, int protocolType = 4, bool calcCrc = true)
        {
            int len = payload.Length;
            ushort bodyCrc = calcCrc ? ComputeCrc16Arc(payload) : (ushort)0;
            Span<byte> hdr8 = stackalloc byte[8];
            hdr8[0] = 0xBA;
            hdr8[1] = 0xBE;
            hdr8[2] = (byte)((len >> 16) & 0xFF);
            hdr8[3] = (byte)((len >> 8) & 0xFF);
            hdr8[4] = (byte)(len & 0xFF);
            hdr8[5] = (byte)((protocolType & 0x7F) | (calcCrc ? 0x00 : 0x80));
            hdr8[6] = (byte)((bodyCrc >> 8) & 0xFF);
            hdr8[7] = (byte)(bodyCrc & 0xFF);
            ushort hdrCrc = ComputeCrc16Arc(hdr8);

            byte[] frame = new byte[10 + len];
            hdr8.CopyTo(frame);
            frame[8] = (byte)((hdrCrc >> 8) & 0xFF);
            frame[9] = (byte)(hdrCrc & 0xFF);
            if (len > 0)
            {
                payload.CopyTo(frame.AsSpan(10));
            }
            return frame;
        }

        public static List<(int ProtocolType, byte[] Body)> ExtractV2Frames(List<byte> streamBuffer)
        {
            var result = new List<(int ProtocolType, byte[] Body)>();
            int cursor = 0;
            int total = streamBuffer.Count;
            Span<byte> hdr8 = stackalloc byte[8];

            while (total - cursor >= 10)
            {
                if (streamBuffer[cursor] != 0xBA || streamBuffer[cursor + 1] != 0xBE)
                {
                    cursor++;
                    continue;
                }

                for (int i = 0; i < 8; i++) hdr8[i] = streamBuffer[cursor + i];
                ushort expectedHdrCrc = (ushort)((streamBuffer[cursor + 8] << 8) | streamBuffer[cursor + 9]);
                if (ComputeCrc16Arc(hdr8) != expectedHdrCrc)
                {
                    cursor++;
                    continue;
                }

                int pktLen = (hdr8[2] << 16) | (hdr8[3] << 8) | hdr8[4];
                if (pktLen < 0 || pktLen > 28 * 1024 * 1024)
                {
                    cursor++;
                    continue;
                }

                if (total - cursor < 10 + pktLen)
                {
                    break;
                }

                int proto = hdr8[5] & 0x7F;
                byte[] body = new byte[pktLen];
                if (pktLen > 0)
                {
                    streamBuffer.CopyTo(cursor + 10, body, 0, pktLen);
                }
                result.Add((proto, body));
                cursor += 10 + pktLen;
            }

            if (cursor > 0)
            {
                streamBuffer.RemoveRange(0, cursor);
            }
            return result;
        }

        public static byte[] BuildFileDataFrame(int channel, long offset, ReadOnlySpan<byte> chunk)
        {
            byte[] pkt = new byte[7 + chunk.Length];
            pkt[0] = 129;
            pkt[1] = (byte)(channel & 0xFF);
            pkt[2] = (byte)((offset >> 32) & 0xFF);
            pkt[3] = (byte)((offset >> 24) & 0xFF);
            pkt[4] = (byte)((offset >> 16) & 0xFF);
            pkt[5] = (byte)((offset >> 8) & 0xFF);
            pkt[6] = (byte)(offset & 0xFF);
            if (chunk.Length > 0)
            {
                chunk.CopyTo(pkt.AsSpan(7));
            }
            return EncodeV2Frame(pkt, 33, calcCrc: false);
        }

        public static byte[] BuildFileRequestFrame(int channel, long offset, int windowSize)
        {
            Span<byte> pkt = stackalloc byte[10];
            pkt[0] = 1;
            pkt[1] = (byte)(channel & 0xFF);
            pkt[2] = (byte)((offset >> 32) & 0xFF);
            pkt[3] = (byte)((offset >> 24) & 0xFF);
            pkt[4] = (byte)((offset >> 16) & 0xFF);
            pkt[5] = (byte)((offset >> 8) & 0xFF);
            pkt[6] = (byte)(offset & 0xFF);
            pkt[7] = (byte)((windowSize >> 16) & 0xFF);
            pkt[8] = (byte)((windowSize >> 8) & 0xFF);
            pkt[9] = (byte)(windowSize & 0xFF);
            return EncodeV2Frame(pkt, 33, calcCrc: false);
        }

        public static bool TryParseFileRequest(ReadOnlySpan<byte> body, int expectedChannel, out long offset, out int windowSize)
        {
            offset = 0;
            windowSize = 0;
            if (body.Length < 10 || body[0] != 1 || body[1] != (byte)(expectedChannel & 0xFF))
                return false;

            offset = ((long)body[2] << 32) | ((long)body[3] << 24) | ((long)body[4] << 16) | ((long)body[5] << 8) | body[6];
            windowSize = (body[7] << 16) | (body[8] << 8) | body[9];
            return windowSize > 0 && offset >= 0;
        }

        private sealed class RefInt
        {
            public int Value;
        }

        private static async Task<ClientWebSocket?> ConnectV2WsAsync(string ip, int port, string path, CancellationToken ct)
        {
            if (!IsValidPrivateLanIpv4(ip) || port != DefaultV2Port)
                return null;

            var ws = new ClientWebSocket();
            ws.Options.KeepAliveInterval = TimeSpan.FromSeconds(10);
            ws.Options.RemoteCertificateValidationCallback = (_, _, _, _) => true;
            try
            {
                using var connectCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
                connectCts.CancelAfter(TimeSpan.FromMilliseconds(1600));
                await ws.ConnectAsync(new Uri($"wss://{ip.Trim()}:{port}{path}"), connectCts.Token).ConfigureAwait(false);
                return ws;
            }
            catch
            {
                ws.Dispose();
                return null;
            }
        }

        private static async Task<bool> ReceiveIntoBufferAsync(ClientWebSocket ws, List<byte> streamBuf, int timeoutMs, CancellationToken ct)
        {
            byte[] temp = new byte[32768];
            using var readCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            readCts.CancelAfter(Math.Clamp(timeoutMs, 100, 10000));
            try
            {
                while (true)
                {
                    var res = await ws.ReceiveAsync(temp.AsMemory(), readCts.Token).ConfigureAwait(false);
                    if (res.MessageType == WebSocketMessageType.Close)
                        return false;
                    if (res.Count > 0)
                    {
                        for (int i = 0; i < res.Count; i++)
                            streamBuf.Add(temp[i]);
                    }
                    if (res.EndOfMessage)
                        return true;
                }
            }
            catch
            {
                return false;
            }
        }

        private static async Task<JsonObject?> SendV2JsonCmdAsync(
            ClientWebSocket ws,
            List<byte> recvBuf,
            RefInt txId,
            string sessionToken,
            string method,
            string url,
            JsonObject? prms,
            JsonObject? data,
            CancellationToken ct)
        {
            int curTx = ++txId.Value;
            var cmd = new JsonObject
            {
                ["type"] = "request",
                ["method"] = method,
                ["url"] = url,
                ["params"] = prms ?? new JsonObject(),
                ["data"] = data ?? new JsonObject(),
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
                ["transactionId"] = curTx
            };
            if (!string.IsNullOrEmpty(sessionToken))
            {
                cmd["sessionToken"] = sessionToken;
            }

            byte[] jsonBytes = Encoding.UTF8.GetBytes(cmd.ToJsonString());
            byte[] encoded = EncodeV2Frame(jsonBytes, 4, calcCrc: true);
            await ws.SendAsync(encoded.AsMemory(), WebSocketMessageType.Binary, endOfMessage: true, ct).ConfigureAwait(false);

            var sw = Stopwatch.StartNew();
            while (sw.ElapsedMilliseconds < 7500 && !ct.IsCancellationRequested)
            {
                var frames = ExtractV2Frames(recvBuf);
                foreach (var (proto, body) in frames)
                {
                    if (proto != 4 || body.Length == 0) continue;
                    try
                    {
                        if (JsonNode.Parse(Encoding.UTF8.GetString(body)) is JsonObject msg)
                        {
                            string mType = msg["type"]?.GetValue<string>() ?? "";
                            int mTx = msg["transactionId"]?.GetValue<int>() ?? -1;
                            if (string.Equals(mType, "response", StringComparison.OrdinalIgnoreCase) && mTx == curTx)
                                return msg;
                        }
                    }
                    catch { }
                }

                if (!await ReceiveIntoBufferAsync(ws, recvBuf, 2500, ct).ConfigureAwait(false))
                    break;
            }

            return null;
        }

        private static async Task<string> AuthenticateParityAsync(ClientWebSocket mainWs, List<byte> mainBuf, RefInt txId, CancellationToken ct)
        {
            string parityToken = Environment.GetEnvironmentVariable("XTOOL_PARITY_TOKEN") ?? DefaultParityToken;
            if (string.IsNullOrWhiteSpace(parityToken)) parityToken = DefaultParityToken;

            var parity = await SendV2JsonCmdAsync(
                mainWs,
                mainBuf,
                txId,
                "",
                "GET",
                "/v1/user/parity",
                new JsonObject(),
                new JsonObject
                {
                    ["userID"] = DefaultUserId,
                    ["userKey"] = DefaultUserKey,
                    ["timezone"] = "Europe/Berlin",
                    ["token"] = parityToken
                },
                ct).ConfigureAwait(false);

            return parity?["data"]?["sessionToken"]?.GetValue<string>() ?? "";
        }

        private static async Task<byte[]?> DownloadFileFromLaserV2Async(
            ClientWebSocket mainWs,
            List<byte> mainBuf,
            ClientWebSocket fileWs,
            List<byte> fileBuf,
            RefInt txId,
            string sessionToken,
            string remoteFilename,
            int fileType,
            int channel,
            CancellationToken ct)
        {
            var dlInit = await SendV2JsonCmdAsync(
                mainWs,
                mainBuf,
                txId,
                sessionToken,
                "PUT",
                "/v1/filetransfer/download",
                new JsonObject(),
                new JsonObject
                {
                    ["channel"] = channel,
                    ["digesttype"] = 1,
                    ["filename"] = remoteFilename,
                    ["filetype"] = fileType,
                    ["packetsize"] = 1048576
                },
                ct).ConfigureAwait(false);

            int fileSize = dlInit?["data"]?["filesize"]?.GetValue<int>() ?? 0;
            if (fileSize <= 0 || fileSize > 25 * 1024 * 1024)
                return null;

            byte[] reqFrame = BuildFileRequestFrame(channel, 0, fileSize);
            await fileWs.SendAsync(reqFrame.AsMemory(), WebSocketMessageType.Binary, endOfMessage: true, ct).ConfigureAwait(false);

            byte[] outBytes = new byte[fileSize];
            int received = 0;
            var sw = Stopwatch.StartNew();

            while (received < fileSize && sw.ElapsedMilliseconds < 8500 && !ct.IsCancellationRequested)
            {
                if (!await ReceiveIntoBufferAsync(fileWs, fileBuf, 2500, ct).ConfigureAwait(false))
                    break;

                var frames = ExtractV2Frames(fileBuf);
                foreach (var (_, body) in frames)
                {
                    if (body.Length > 7 && body[0] == 129 && body[1] == (byte)(channel & 0xFF))
                    {
                        long off = ((long)body[2] << 32) | ((long)body[3] << 24) | ((long)body[4] << 16) | ((long)body[5] << 8) | body[6];
                        int cLen = body.Length - 7;
                        if (off >= 0 && off < fileSize && cLen > 0)
                        {
                            int writeLen = (int)Math.Min(cLen, fileSize - off);
                            Buffer.BlockCopy(body, 7, outBytes, (int)off, writeLen);
                            received += writeLen;
                        }
                    }
                }
            }

            _ = await SendV2JsonCmdAsync(
                mainWs,
                mainBuf,
                txId,
                sessionToken,
                "PUT",
                "/v1/filetransfer/finish",
                new JsonObject(),
                new JsonObject
                {
                    ["channel"] = channel,
                    ["code"] = 0,
                    ["message"] = "file transfer finish"
                },
                ct).ConfigureAwait(false);

            return received >= fileSize ? outBytes : null;
        }

        // =====================================================================
        // 5. CAMERA CAPTURE + NATIVE WPF 21x21 PERSPECTIVE DEWARPING (2000x2000)
        // =====================================================================
        public static bool TryReadJpegDimensions(ReadOnlySpan<byte> jpeg, out int width, out int height)
        {
            width = 0;
            height = 0;
            if (jpeg.Length < 24 || jpeg[0] != 0xFF || jpeg[1] != 0xD8)
                return false;

            int i = 2;
            while (i + 8 < jpeg.Length)
            {
                if (jpeg[i] != 0xFF)
                {
                    i++;
                    continue;
                }
                while (i < jpeg.Length && jpeg[i] == 0xFF) i++;
                if (i >= jpeg.Length) break;

                byte marker = jpeg[i++];
                if (marker is 0xD8 or 0xD9 or 0x00 || (marker >= 0xD0 && marker <= 0xD7))
                    continue;

                if (i + 1 >= jpeg.Length) break;
                int segLen = (jpeg[i] << 8) | jpeg[i + 1];
                if (segLen < 2 || i + segLen > jpeg.Length) break;

                if (marker is 0xC0 or 0xC1 or 0xC2)
                {
                    if (segLen >= 7 && i + 6 < jpeg.Length)
                    {
                        height = (jpeg[i + 3] << 8) | jpeg[i + 4];
                        width = (jpeg[i + 5] << 8) | jpeg[i + 6];
                        return width > 0 && height > 0;
                    }
                    return false;
                }

                i += segLen;
            }
            return false;
        }

        public static bool TryParseGlobalCalibGrid21x21(string? calibJson, out float[] gridX, out float[] gridY)
        {
            gridX = Array.Empty<float>();
            gridY = Array.Empty<float>();
            if (string.IsNullOrWhiteSpace(calibJson))
                return false;

            try
            {
                using var doc = JsonDocument.Parse(calibJson, new JsonDocumentOptions { MaxDepth = 16 });
                if (!doc.RootElement.TryGetProperty("data0", out var d0) || d0.ValueKind != JsonValueKind.String)
                    return false;

                string raw = d0.GetString() ?? "";
                if (string.IsNullOrWhiteSpace(raw))
                    return false;

                var matches = CalibPairRegex.Matches(raw);
                if (matches.Count < 441)
                    return false;

                var gx = new float[441];
                var gy = new float[441];
                for (int i = 0; i < 441; i++)
                {
                    gx[i] = float.Parse(matches[i].Groups[1].Value, CultureInfo.InvariantCulture);
                    gy[i] = float.Parse(matches[i].Groups[2].Value, CultureInfo.InvariantCulture);
                }

                gridX = gx;
                gridY = gy;
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static readonly byte[] s_antiGlareToneLut = BuildAntiGlareToneLut();

        private static byte[] BuildAntiGlareToneLut()
        {
            var lut = new byte[256];
            for (int i = 0; i < 256; i++)
            {
                if (i <= 185)
                {
                    lut[i] = (byte)i;
                }
                else
                {
                    // Sanftes Highlight-Rolloff ab 185 -> max. 236, damit glänzende Silberränder nicht weiß ausfressen
                    double t = (i - 185.0) / 70.0;
                    int rolled = 185 + (int)Math.Round(51.0 * Math.Pow(t, 0.56));
                    lut[i] = (byte)Math.Clamp(rolled, 0, 236);
                }
            }
            return lut;
        }

        public static (byte[]? WarpedJpeg, bool UsedCalibGrid) TryWarpRawCameraJpeg2000x2000(
            byte[] rawJpegBytes,
            string? calibJson,
            bool antiGlareRolloff = false)
        {
            if (rawJpegBytes == null || rawJpegBytes.Length < 128)
                return (null, false);

            try
            {
                using var inMs = new MemoryStream(rawJpegBytes, writable: false);
                var decoder = new JpegBitmapDecoder(inMs, BitmapCreateOptions.PreservePixelFormat, BitmapCacheOption.OnLoad);
                if (decoder.Frames.Count == 0)
                    return (null, false);

                BitmapSource frame = decoder.Frames[0];
                int srcW = frame.PixelWidth;
                int srcH = frame.PixelHeight;
                if (srcW < 32 || srcH < 32)
                    return (null, false);

                BitmapSource bgraSrc = frame.Format == PixelFormats.Bgra32
                    ? frame
                    : new FormatConvertedBitmap(frame, PixelFormats.Bgra32, null, 0.0);

                int srcStride = srcW * 4;
                byte[] srcPixels = new byte[srcStride * srcH];
                bgraSrc.CopyPixels(srcPixels, srcStride, 0);

                const int outDim = 2000;
                byte[] dstPixels = new byte[outDim * outDim * 4];
                bool hasCalib = TryParseGlobalCalibGrid21x21(calibJson, out float[] gridX, out float[] gridY);

                float maxSrcX = srcW - 1.001f;
                float maxSrcY = srcH - 1.001f;

                if (hasCalib)
                {
                    int[] cellIdx = new int[outDim];
                    float[] cellFrac = new float[outDim];
                    for (int i = 0; i < outDim; i++)
                    {
                        float g = i * (20.0f / (outDim - 1));
                        int c = Math.Clamp((int)MathF.Floor(g), 0, 19);
                        cellIdx[i] = c;
                        cellFrac[i] = g - c;
                    }

                    for (int dy = 0; dy < outDim; dy++)
                    {
                        int r0 = cellIdx[dy];
                        float dv = cellFrac[dy];
                        float invDv = 1.0f - dv;
                        int rowBase0 = r0 * 21;
                        int rowBase1 = (r0 + 1) * 21;
                        int dstRowOffset = dy * outDim * 4;

                        for (int dx = 0; dx < outDim; dx++)
                        {
                            int c0 = cellIdx[dx];
                            float du = cellFrac[dx];
                            float invDu = 1.0f - du;

                            float w00 = invDu * invDv;
                            float w10 = du * invDv;
                            float w01 = invDu * dv;
                            float w11 = du * dv;

                            int i00 = rowBase0 + c0;
                            int i01 = rowBase1 + c0;

                            float sx = (w00 * gridX[i00]) + (w10 * gridX[i00 + 1]) + (w01 * gridX[i01]) + (w11 * gridX[i01 + 1]);
                            float sy = (w00 * gridY[i00]) + (w10 * gridY[i00 + 1]) + (w01 * gridY[i01]) + (w11 * gridY[i01 + 1]);

                            SampleBilinearBgra32(srcPixels, srcStride, sx, sy, maxSrcX, maxSrcY, dstPixels, dstRowOffset + (dx * 4), antiGlareRolloff);
                        }
                    }
                }
                else
                {
                    float cropSize = MathF.Min(srcW, srcH) * 0.78f;
                    float cropX = (srcW - cropSize) * 0.5f;
                    float cropY = (srcH - cropSize) * 0.5f;
                    float step = cropSize / (outDim - 1);

                    for (int dy = 0; dy < outDim; dy++)
                    {
                        float sy = cropY + (dy * step);
                        int dstRowOffset = dy * outDim * 4;
                        for (int dx = 0; dx < outDim; dx++)
                        {
                            float sx = cropX + (dx * step);
                            SampleBilinearBgra32(srcPixels, srcStride, sx, sy, maxSrcX, maxSrcY, dstPixels, dstRowOffset + (dx * 4), antiGlareRolloff);
                        }
                    }
                }

                BitmapSource outBmp = BitmapSource.Create(
                    outDim,
                    outDim,
                    96.0,
                    96.0,
                    PixelFormats.Bgra32,
                    null,
                    dstPixels,
                    outDim * 4);
                outBmp.Freeze();

                var encoder = new JpegBitmapEncoder { QualityLevel = 92 };
                encoder.Frames.Add(BitmapFrame.Create(outBmp));
                using var outMs = new MemoryStream(512 * 1024);
                encoder.Save(outMs);
                return (outMs.ToArray(), hasCalib);
            }
            catch
            {
                return (null, false);
            }
        }

        private static void SampleBilinearBgra32(
            byte[] srcPixels,
            int srcStride,
            float sx,
            float sy,
            float maxSrcX,
            float maxSrcY,
            byte[] dstPixels,
            int dstOffset,
            bool antiGlareRolloff = false)
        {
            float cx = Math.Clamp(sx, 0.0f, maxSrcX);
            float cy = Math.Clamp(sy, 0.0f, maxSrcY);
            int x0 = (int)cx;
            int y0 = (int)cy;
            float fx = cx - x0;
            float fy = cy - y0;
            float invFx = 1.0f - fx;
            float invFy = 1.0f - fy;

            float w00 = invFx * invFy;
            float w10 = fx * invFy;
            float w01 = invFx * fy;
            float w11 = fx * fy;

            int p00 = (y0 * srcStride) + (x0 * 4);
            int p10 = p00 + 4;
            int p01 = p00 + srcStride;
            int p11 = p01 + 4;

            byte b = (byte)Math.Clamp((int)((w00 * srcPixels[p00]) + (w10 * srcPixels[p10]) + (w01 * srcPixels[p01]) + (w11 * srcPixels[p11]) + 0.5f), 0, 255);
            byte g = (byte)Math.Clamp((int)((w00 * srcPixels[p00 + 1]) + (w10 * srcPixels[p10 + 1]) + (w01 * srcPixels[p01 + 1]) + (w11 * srcPixels[p11 + 1]) + 0.5f), 0, 255);
            byte r = (byte)Math.Clamp((int)((w00 * srcPixels[p00 + 2]) + (w10 * srcPixels[p10 + 2]) + (w01 * srcPixels[p01 + 2]) + (w11 * srcPixels[p11 + 2]) + 0.5f), 0, 255);

            if (antiGlareRolloff)
            {
                b = s_antiGlareToneLut[b];
                g = s_antiGlareToneLut[g];
                r = s_antiGlareToneLut[r];
            }

            dstPixels[dstOffset] = b;
            dstPixels[dstOffset + 1] = g;
            dstPixels[dstOffset + 2] = r;
            dstPixels[dstOffset + 3] = 255;
        }

        private static string LoadCachedCalibJson()
        {
            string[] candidates =
            {
                Path.Combine(McpSecurityGuard.McpDataDir, "xtool_global_calib.json"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "xTool Studio", "atomm", DeviceExtId, DeviceSnCode, "global_calib.json")
            };

            foreach (string path in candidates)
            {
                try
                {
                    if (!File.Exists(path) || McpSecurityGuard.HasReparsePointInAncestry(path))
                        continue;

                    string content = File.ReadAllText(path, Encoding.UTF8);
                    if (content.Contains("data0", StringComparison.Ordinal))
                        return content;
                }
                catch { }
            }

            return "";
        }

        private static void SaveCachedCalibJson(byte[] calibBytes)
        {
            if (calibBytes == null || calibBytes.Length < 500)
                return;

            try
            {
                string cachePath = Path.Combine(McpSecurityGuard.McpDataDir, "xtool_global_calib.json");
                string dir = McpSecurityGuard.McpDataDir;
                Directory.CreateDirectory(dir);
                McpSecurityGuard.LockDownDirectoryAcl(dir);
                if (McpSecurityGuard.HasReparsePointInAncestry(cachePath))
                    return;

                string tmp = Path.Combine(dir, $".xcalib_{Convert.ToHexString(RandomNumberGenerator.GetBytes(6)).ToLowerInvariant()}.tmp");
                File.WriteAllBytes(tmp, calibBytes);
                File.Move(tmp, cachePath, overwrite: true);
                McpSecurityGuard.LockDownFileAcl(cachePath);
            }
            catch { }
        }

        private static async Task<byte[]?> FetchHttpCameraSnapshotFallbackAsync(string safeIp)
        {
            if (!IsValidPrivateLanIpv4(safeIp))
                return null;

            (int Port, string Path)[] endpoints =
            {
                (8080, "/camera/snapshot"),
                (8080, "/snapshot.jpg"),
                (8329, "/camera/snapshot"),
                (8080, "/frame.jpg")
            };

            bool port8080Checked = false, port8080Open = false;
            bool port8329Checked = false, port8329Open = false;

            foreach (var (port, path) in endpoints)
            {
                if (port == 8080)
                {
                    if (!port8080Checked)
                    {
                        port8080Checked = true;
                        port8080Open = await ProbeLaserReachableAsync(safeIp, 8080, 260).ConfigureAwait(false);
                    }
                    if (!port8080Open) continue;
                }
                else if (port == 8329)
                {
                    if (!port8329Checked)
                    {
                        port8329Checked = true;
                        port8329Open = await ProbeLaserReachableAsync(safeIp, 8329, 260).ConfigureAwait(false);
                    }
                    if (!port8329Open) continue;
                }

                try
                {
                    using var cts = new CancellationTokenSource(TimeSpan.FromMilliseconds(2400));
                    using var resp = await LanCameraHttp.GetAsync($"http://{safeIp}:{port}{path}", cts.Token).ConfigureAwait(false);
                    if (!resp.IsSuccessStatusCode) continue;

                    byte[] bytes = await resp.Content.ReadAsByteArrayAsync(cts.Token).ConfigureAwait(false);
                    if (bytes.Length >= 8192 && bytes[0] == 0xFF && bytes[1] == 0xD8)
                        return bytes;
                }
                catch { }
            }

            return null;
        }

        private static async Task SendLegacyHttpFillLightFallbackAsync(string safeIp, int front, int back)
        {
            if (!IsValidPrivateLanIpv4(safeIp))
                return;

            try
            {
                string body = $"{{\"action\":\"set_bri\",\"front\":{Math.Clamp(front, 0, 255)},\"back\":{Math.Clamp(back, 0, 255)}}}";
                using var cts = new CancellationTokenSource(TimeSpan.FromMilliseconds(450));
                using var content = new StringContent(body, new UTF8Encoding(false), "application/json");
                using var _ = await LanCameraHttp.PostAsync($"http://{safeIp}:8080/peripheral/fill_light", content, cts.Token).ConfigureAwait(false);
            }
            catch { }
        }

        public static async Task<LaserCameraCaptureResult> CaptureLiveCameraDetailedAsync(
            string ip = DefaultLaserIp,
            string lightMode = "normal",
            double? focusMm = null)
        {
            if (!IsValidPrivateLanIpv4(ip))
            {
                return new LaserCameraCaptureResult
                {
                    Ok = false,
                    LaserOnline = false,
                    Error = "Ungültige Laser-IP blockiert (nur private RFC1918 LAN-IPv4 erlaubt)."
                };
            }

            string safeIp = ip.Trim();
            string resolvedLightMode = NormalizeCameraLightMode(lightMode);
            (int Front, int Back)? fillLightTargets = resolvedLightMode switch
            {
                "anti_glare" => (0, 0),
                "dim" => (0, 20),
                _ => null
            };

            bool v2Open = await ProbeLaserReachableAsync(safeIp, DefaultV2Port, 750).ConfigureAwait(false);
            byte[]? rawJpeg = null;
            string calibJson = LoadCachedCalibJson();
            string source = "";

            if (v2Open)
            {
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(18));
                string uuid = Guid.NewGuid().ToString("D");
                using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=instruction", cts.Token).ConfigureAwait(false);
                using var fileWs = mainWs != null
                    ? await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=file_stream", cts.Token).ConfigureAwait(false)
                    : null;

                if (mainWs != null && fileWs != null)
                {
                    var mainBuf = new List<byte>(4096);
                    var fileBuf = new List<byte>(65536);
                    var txId = new RefInt();
                    string sessionToken = "";
                    bool fillLightChanged = false;
                    bool peripheralsDimmed = false;
                    int restoreFrontBri = 255;
                    int restoreBackBri = 255;

                    try
                    {
                        sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, cts.Token).ConfigureAwait(false);
                        if (!string.IsNullOrWhiteSpace(sessionToken))
                        {
                            if (string.IsNullOrWhiteSpace(calibJson) || !calibJson.Contains("data0", StringComparison.Ordinal))
                            {
                                byte[]? calibBytes = await DownloadFileFromLaserV2Async(mainWs, mainBuf, fileWs, fileBuf, txId, sessionToken, "global_calib.json", 3, 2, cts.Token).ConfigureAwait(false);
                                if (calibBytes != null && calibBytes.Length > 500)
                                {
                                    string fetchedCalib = Encoding.UTF8.GetString(calibBytes);
                                    if (fetchedCalib.Contains("data0", StringComparison.Ordinal))
                                    {
                                        calibJson = fetchedCalib;
                                        SaveCachedCalibJson(calibBytes);
                                    }
                                }
                            }

                            if (fillLightTargets.HasValue)
                            {
                                try
                                {
                                    var devCfg = await SendV2JsonCmdAsync(
                                        mainWs,
                                        mainBuf,
                                        txId,
                                        sessionToken,
                                        "GET",
                                        "/v1/device/configs",
                                        new JsonObject(),
                                        new JsonObject
                                        {
                                            ["alias"] = "config",
                                            ["type"] = "user",
                                            ["kv"] = new JsonArray("fillLightBrightFront", "fillLightBrightBack")
                                        },
                                        cts.Token).ConfigureAwait(false);
                                    var cfgNode = devCfg?["data"]?["configs"] ?? devCfg?["data"];
                                    int? fVal = cfgNode?["fillLightBrightFront"]?.GetValue<int>();
                                    int? bVal = cfgNode?["fillLightBrightBack"]?.GetValue<int>();
                                    if (fVal.HasValue) restoreFrontBri = Math.Clamp(fVal.Value, 0, 255);
                                    if (bVal.HasValue) restoreBackBri = Math.Clamp(bVal.Value, 0, 255);
                                    if (restoreFrontBri == 0 && restoreBackBri == 0)
                                    {
                                        restoreFrontBri = 255;
                                        restoreBackBri = 255;
                                    }
                                }
                                catch { }
                            }

                            if (focusMm.HasValue && focusMm.Value > 0.0 && focusMm.Value <= 150.0)
                            {
                                try
                                {
                                    double targetZ = Math.Round(focusMm.Value + 6.0, 2);
                                    _ = await SendV2JsonCmdAsync(
                                        mainWs,
                                        mainBuf,
                                        txId,
                                        sessionToken,
                                        "POST",
                                        "/v1/laser-head/focus/control",
                                        new JsonObject(),
                                        new JsonObject { ["action"] = "goTo", ["stopFirst"] = 1, ["z"] = targetZ },
                                        cts.Token).ConfigureAwait(false);
                                    await Task.Delay(350, cts.Token).ConfigureAwait(false);
                                }
                                catch { }
                            }

                            peripheralsDimmed = true;
                            _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/peripheral/param", new JsonObject { ["type"] = "ir_led" }, new JsonObject { ["action"] = "off", ["index"] = 0 }, cts.Token).ConfigureAwait(false);
                            _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/laser-head/parameter", new JsonObject(), new JsonObject { ["action"] = "power", ["ctrl"] = "set", ["value"] = 0 }, cts.Token).ConfigureAwait(false);

                            if (fillLightTargets.HasValue)
                            {
                                var flResp = await SendV2JsonCmdAsync(
                                    mainWs,
                                    mainBuf,
                                    txId,
                                    sessionToken,
                                    "PUT",
                                    "/v1/peripheral/param",
                                    new JsonObject { ["type"] = "fill_light" },
                                    new JsonObject
                                    {
                                        ["action"] = "set_bri",
                                        ["front"] = fillLightTargets.Value.Front,
                                        ["back"] = fillLightTargets.Value.Back
                                    },
                                    cts.Token).ConfigureAwait(false);
                                fillLightChanged = true;
                                if (flResp == null)
                                {
                                    await SendLegacyHttpFillLightFallbackAsync(safeIp, fillLightTargets.Value.Front, fillLightTargets.Value.Back).ConfigureAwait(false);
                                }
                            }

                            _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "POST", "/v1/camera", new JsonObject { ["exp"] = 0, ["stream"] = "0" }, new JsonObject(), cts.Token).ConfigureAwait(false);

                            if (fillLightTargets.HasValue)
                            {
                                await Task.Delay(160, cts.Token).ConfigureAwait(false);
                                _ = await SendV2JsonCmdAsync(
                                    mainWs,
                                    mainBuf,
                                    txId,
                                    sessionToken,
                                    "PUT",
                                    "/v1/peripheral/param",
                                    new JsonObject { ["type"] = "fill_light" },
                                    new JsonObject
                                    {
                                        ["action"] = "set_bri",
                                        ["front"] = fillLightTargets.Value.Front,
                                        ["back"] = fillLightTargets.Value.Back
                                    },
                                    cts.Token).ConfigureAwait(false);
                                await Task.Delay(520, cts.Token).ConfigureAwait(false);
                            }

                            var snap = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "GET", "/v1/camera/snap", new JsonObject { ["name"] = "main", ["timeOut"] = 30000 }, new JsonObject(), cts.Token).ConfigureAwait(false);
                            string remoteFile = snap?["data"]?["filename"]?.GetValue<string>()
                                             ?? snap?["data"]?["params"]?["filename"]?.GetValue<string>()
                                             ?? "";

                            if (!string.IsNullOrWhiteSpace(remoteFile))
                            {
                                rawJpeg = await DownloadFileFromLaserV2Async(mainWs, mainBuf, fileWs, fileBuf, txId, sessionToken, remoteFile, 5, 9, cts.Token).ConfigureAwait(false);
                                if (rawJpeg != null && rawJpeg.Length >= 10000)
                                    source = "win_v2_wss_28900";
                            }
                        }
                    }
                    catch { }
                    finally
                    {
                        if (!string.IsNullOrWhiteSpace(sessionToken) && mainWs.State == WebSocketState.Open)
                        {
                            using var cleanupCts = new CancellationTokenSource(TimeSpan.FromSeconds(2));
                            if (fillLightChanged)
                            {
                                try
                                {
                                    var restResp = await SendV2JsonCmdAsync(
                                        mainWs,
                                        mainBuf,
                                        txId,
                                        sessionToken,
                                        "PUT",
                                        "/v1/peripheral/param",
                                        new JsonObject { ["type"] = "fill_light" },
                                        new JsonObject
                                        {
                                            ["action"] = "set_bri",
                                            ["front"] = restoreFrontBri,
                                            ["back"] = restoreBackBri
                                        },
                                        cleanupCts.Token).ConfigureAwait(false);
                                    if (restResp == null)
                                    {
                                        await SendLegacyHttpFillLightFallbackAsync(safeIp, restoreFrontBri, restoreBackBri).ConfigureAwait(false);
                                    }
                                }
                                catch { }
                            }

                            if (peripheralsDimmed)
                            {
                                try
                                {
                                    _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/peripheral/param", new JsonObject { ["type"] = "ir_led" }, new JsonObject { ["action"] = "on", ["index"] = 0 }, cleanupCts.Token).ConfigureAwait(false);
                                    _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/laser-head/parameter", new JsonObject(), new JsonObject { ["action"] = "power", ["ctrl"] = "set", ["value"] = 128 }, cleanupCts.Token).ConfigureAwait(false);
                                }
                                catch { }
                            }
                        }

                        try { if (fileWs.State == WebSocketState.Open) await fileWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
                        try { if (mainWs.State == WebSocketState.Open) await mainWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
                    }
                }
            }

            if (rawJpeg == null || rawJpeg.Length < 10000)
            {
                byte[]? httpSnap = await FetchHttpCameraSnapshotFallbackAsync(safeIp).ConfigureAwait(false);
                if (httpSnap != null && httpSnap.Length >= 8192)
                {
                    rawJpeg = httpSnap;
                    source = "win_http_snapshot";
                }
            }

            if (rawJpeg == null || rawJpeg.Length < 8192)
            {
                if (!v2Open)
                {
                    return new LaserCameraCaptureResult
                    {
                        Ok = false,
                        LaserOnline = false,
                        Error = $"xTool F2 Ultra UV ({safeIp}) im Werkstatt-LAN nicht erreichbar (bitte prüfen, ob der Laser eingeschaltet ist)."
                    };
                }

                return new LaserCameraCaptureResult
                {
                    Ok = false,
                    LaserOnline = true,
                    Error = $"xTool F2 Ultra UV ({safeIp}) ist verbunden, lieferte aber kein gültiges Kamerabild."
                };
            }

            if (TryReadJpegDimensions(rawJpeg, out int jw, out int jh) && jw == 2000 && jh == 2000)
            {
                return new LaserCameraCaptureResult
                {
                    Ok = true,
                    LaserOnline = true,
                    WarpedJpeg = rawJpeg,
                    RawJpeg = null,
                    Source = source,
                    CalibratedGridUsed = true
                };
            }

            var (warped, usedCalib) = TryWarpRawCameraJpeg2000x2000(rawJpeg, calibJson, resolvedLightMode != "normal");
            if (warped != null && warped.Length > 10000)
            {
                return new LaserCameraCaptureResult
                {
                    Ok = true,
                    LaserOnline = true,
                    WarpedJpeg = warped,
                    RawJpeg = null,
                    Source = source + (usedCalib ? "+calib21x21" : "+crop2000"),
                    CalibratedGridUsed = usedCalib
                };
            }

            return new LaserCameraCaptureResult
            {
                Ok = true,
                LaserOnline = true,
                WarpedJpeg = null,
                RawJpeg = rawJpeg,
                Source = source + "+raw",
                CalibratedGridUsed = false
            };
        }

        // =====================================================================
        // 6. UPLOAD .XF (autoStart=0) & FRAMING BORDER G-CODE OVER V2 WSS
        // =====================================================================
        public static async Task<(bool Ok, string TaskId, string Error)> UploadXfPackageLocalV2Async(
            byte[] xfBytes,
            string ip = DefaultLaserIp)
        {
            if (xfBytes == null || xfBytes.Length == 0)
                return (false, "", "Leeres Laser-Paket.");

            if (!IsValidPrivateLanIpv4(ip))
                return (false, "", "Ungültige Laser-IP blockiert (nur private RFC1918 LAN-IPv4 erlaubt).");

            string safeIp = ip.Trim();
            if (!await ProbeLaserReachableAsync(safeIp, DefaultV2Port, 350).ConfigureAwait(false))
                return (false, "", "Laser im WLAN nicht erreichbar (bitte einschalten).");

            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(18));
            string uuid = Guid.NewGuid().ToString("D");
            using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=instruction", cts.Token).ConfigureAwait(false);
            if (mainWs == null)
                return (false, "", "Steuerkanal zum Laser konnte nicht geöffnet werden.");

            using var fileWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=file_stream", cts.Token).ConfigureAwait(false);
            if (fileWs == null)
                return (false, "", "Dateikanal zum Laser konnte nicht geöffnet werden.");

            try
            {
                var mainBuf = new List<byte>(4096);
                var fileBuf = new List<byte>(8192);
                var txId = new RefInt();

                string sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, cts.Token).ConfigureAwait(false);
                if (string.IsNullOrWhiteSpace(sessionToken))
                    return (false, "", "Authentifizierung am Laser fehlgeschlagen.");

                const int channel = 62;
                string md5Hex = Convert.ToHexStringLower(MD5.HashData(xfBytes));
                var upInit = await SendV2JsonCmdAsync(
                    mainWs,
                    mainBuf,
                    txId,
                    sessionToken,
                    "PUT",
                    "/v1/filetransfer/upload",
                    new JsonObject(),
                    new JsonObject
                    {
                        ["channel"] = channel,
                        ["digestdata"] = md5Hex,
                        ["digesttype"] = 1,
                        ["filename"] = "tmp.xf",
                        ["filesize"] = xfBytes.Length,
                        ["filetype"] = 1,
                        ["packetsize"] = 1048576
                    },
                    cts.Token).ConfigureAwait(false);

                if (upInit == null || (upInit["code"]?.GetValue<int>() ?? -1) != 0)
                    return (false, "", "Dateiübertragung wurde vom Laser abgelehnt.");

                int negotiatedMax = Math.Clamp(upInit["data"]?["packetsize"]?.GetValue<int>() ?? 131072, 16384, 131072);
                long sentTotal = 0;
                bool uploadAcked = false;
                var sw = Stopwatch.StartNew();

                while (sw.ElapsedMilliseconds < 12500 && !uploadAcked && !cts.Token.IsCancellationRequested)
                {
                    if (!await ReceiveIntoBufferAsync(fileWs, fileBuf, 3000, cts.Token).ConfigureAwait(false))
                        break;

                    var frames = ExtractV2Frames(fileBuf);
                    foreach (var (proto, body) in frames)
                    {
                        if (proto == 33 && TryParseFileRequest(body, channel, out long offset, out int windowSize))
                        {
                            if (offset >= xfBytes.Length) continue;
                            long endOffset = Math.Min(xfBytes.Length, offset + windowSize);
                            long cursor = offset;
                            while (cursor < endOffset)
                            {
                                int chunkLen = (int)Math.Min(Math.Min(negotiatedMax, 65536), endOffset - cursor);
                                byte[] frameBytes = BuildFileDataFrame(channel, cursor, xfBytes.AsSpan((int)cursor, chunkLen));
                                await fileWs.SendAsync(frameBytes.AsMemory(), WebSocketMessageType.Binary, endOfMessage: true, cts.Token).ConfigureAwait(false);
                                cursor += chunkLen;
                                sentTotal += chunkLen;
                            }
                        }
                        else if (proto == 4 && body.Length > 0)
                        {
                            try
                            {
                                if (JsonNode.Parse(Encoding.UTF8.GetString(body)) is JsonObject ack &&
                                    (ack["channel"]?.GetValue<int>() ?? -1) == channel)
                                {
                                    uploadAcked = (ack["code"]?.GetValue<int>() ?? -1) == 0;
                                    if (!uploadAcked)
                                        return (false, "", "Laser meldete Übertragungsfehler auf Kanal 62.");
                                    break;
                                }
                            }
                            catch { }
                        }
                    }
                }

                if (!uploadAcked && sentTotal < xfBytes.Length)
                    return (false, "", "Dateiübertragung an den Laser unvollständig.");

                // Safety rule: autoStart remains strictly 0 (WORK_PREPARED -> physical button press required)
                string taskId = Guid.NewGuid().ToString("D");
                var cfgResp = await SendV2JsonCmdAsync(
                    mainWs,
                    mainBuf,
                    txId,
                    sessionToken,
                    "PUT",
                    "/v1/processing/upload/config",
                    new JsonObject
                    {
                        ["autoStart"] = 0,
                        ["taskId"] = taskId
                    },
                    new JsonObject
                    {
                        ["autoStart"] = 0,
                        ["fileType"] = "xf",
                        ["taskId"] = taskId
                    },
                    cts.Token).ConfigureAwait(false);

                int cfgCode = cfgResp?["code"]?.GetValue<int>() ?? -1;
                if (cfgResp == null || cfgCode != 0)
                    return (false, taskId, "Auftrags-Aktivierung am Laser fehlgeschlagen.");

                return (true, taskId, "");
            }
            catch (Exception ex)
            {
                return (false, "", McpSecurityGuard.ScrubSecrets(ex.Message));
            }
            finally
            {
                try { if (fileWs.State == WebSocketState.Open) await fileWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
                try { if (mainWs.State == WebSocketState.Open) await mainWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
            }
        }

        public static string GenerateBorderGcode(
            double centerXMm,
            double centerYMm,
            double widthMm,
            double heightMm,
            double angleDeg = 0.0)
        {
            double halfW = Math.Max(1.0, widthMm / 2.0);
            double halfH = Math.Max(1.0, heightMm / 2.0);
            double rad = angleDeg * (Math.PI / 180.0);
            double cos = Math.Cos(rad);
            double sin = Math.Sin(rad);

            (double Lx, double Ly)[] localCorners =
            {
                (-halfW, -halfH),
                ( halfW, -halfH),
                ( halfW,  halfH),
                (-halfW,  halfH)
            };

            var pts = new (string X, string Y)[4];
            for (int i = 0; i < 4; i++)
            {
                double rx = centerXMm + (localCorners[i].Lx * cos) - (localCorners[i].Ly * sin);
                double ry = centerYMm + (localCorners[i].Lx * sin) + (localCorners[i].Ly * cos);
                pts[i] = (
                    Math.Clamp(rx, 0.0, 200.0).ToString("0.000", CultureInfo.InvariantCulture),
                    Math.Clamp(ry, 0.0, 200.0).ToString("0.000", CultureInfo.InvariantCulture)
                );
            }

            return string.Join("\n", new[]
            {
                "# GS009-4 HEAD",
                "G0 F180000",
                "M4 S0",
                "G1 F180000",
                "M114 S1",
                "",
                "# GS009-4 VECTOR HEAD",
                "# motion_start",
                "G21",
                "G90",
                "# blockConfig={\"powerFactor\":0.133,\"isVector\":true,\"crossDot\":false}",
                $"G0X{pts[0].X}Y{pts[0].Y}F1440000",
                $"G1X{pts[1].X}Y{pts[1].Y}S133F1440000",
                $"G1X{pts[2].X}Y{pts[2].Y}",
                $"G1X{pts[3].X}Y{pts[3].Y}",
                $"G1X{pts[0].X}Y{pts[0].Y}",
                "# GS009-4 VECTOR TAIL",
                "# motion_end",
                "# END",
                "# GS009-4 TAIL",
                "G90",
                "G0 S0",
                "G0 F180000",
                "G1 F180000",
                "M536 U0",
                "M6 P1",
                ""
            });
        }

        public static byte[]? TryExtractTarEntry(ReadOnlySpan<byte> tarBytes, string targetEntryName)
        {
            int pos = 0;
            while (pos + 512 <= tarBytes.Length)
            {
                ReadOnlySpan<byte> hdr = tarBytes.Slice(pos, 512);
                bool allZero = true;
                for (int z = 0; z < 512; z++)
                {
                    if (hdr[z] != 0) { allZero = false; break; }
                }
                if (allZero) break;

                int nameLen = 0;
                while (nameLen < 100 && hdr[nameLen] != 0) nameLen++;
                string entryName = Encoding.ASCII.GetString(hdr.Slice(0, nameLen)).Trim();

                string sizeOctal = Encoding.ASCII.GetString(hdr.Slice(124, 12)).Trim('\0', ' ');
                long entrySize = 0;
                if (!string.IsNullOrEmpty(sizeOctal))
                {
                    try { entrySize = Convert.ToInt64(sizeOctal, 8); }
                    catch { return null; }
                }
                if (entrySize < 0 || pos + 512 + entrySize > tarBytes.Length)
                    return null;

                if (string.Equals(entryName, targetEntryName, StringComparison.OrdinalIgnoreCase) ||
                    entryName.EndsWith("/" + targetEntryName, StringComparison.OrdinalIgnoreCase))
                {
                    return tarBytes.Slice(pos + 512, (int)entrySize).ToArray();
                }

                long blocks = (entrySize + 511) / 512;
                pos += 512 + (int)(blocks * 512);
            }
            return null;
        }

        public static async Task<(bool Ok, bool LaserOnline, string TaskId, string Error)> UploadFramingLocalV2Async(
            byte[] payloadBytes,
            bool isXfArchive,
            string ip = DefaultLaserIp)
        {
            if (payloadBytes == null || payloadBytes.Length == 0)
                return (false, false, "", "Leere Rahmen-Vorschau.");

            if (!IsValidPrivateLanIpv4(ip))
                return (false, false, "", "Ungültige Laser-IP blockiert (nur private RFC1918 LAN-IPv4 erlaubt).");

            string safeIp = ip.Trim();
            if (!await ProbeLaserReachableAsync(safeIp, DefaultV2Port, 400).ConfigureAwait(false))
            {
                return (false, false, "", $"xTool F2 Ultra UV ({safeIp}) im Werkstatt-LAN nicht erreichbar (bitte prüfen, ob der Laser eingeschaltet ist).");
            }

            using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(16));
            string uuid = Guid.NewGuid().ToString("D");
            using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=instruction", cts.Token).ConfigureAwait(false);
            if (mainWs == null)
                return (false, false, "", $"V2-Steuerkanal zu {safeIp}:{DefaultV2Port} konnte nicht geöffnet werden.");

            using var fileWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=file_stream", cts.Token).ConfigureAwait(false);
            if (fileWs == null)
                return (false, true, "", "V2-Dateikanal zum Laser konnte nicht geöffnet werden.");

            try
            {
                var mainBuf = new List<byte>(4096);
                var fileBuf = new List<byte>(8192);
                var txId = new RefInt();

                string sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, cts.Token).ConfigureAwait(false);
                if (string.IsNullOrWhiteSpace(sessionToken))
                    return (false, true, "", "Authentifizierung am xTool F2 Ultra UV fehlgeschlagen.");

                int channel = isXfArchive ? 62 : 61;
                string remoteFilename = isXfArchive ? "tmp.xf" : "tmpFrame.gcode";
                string md5Hex = Convert.ToHexStringLower(MD5.HashData(payloadBytes));

                var upInit = await SendV2JsonCmdAsync(
                    mainWs,
                    mainBuf,
                    txId,
                    sessionToken,
                    "PUT",
                    "/v1/filetransfer/upload",
                    new JsonObject(),
                    new JsonObject
                    {
                        ["channel"] = channel,
                        ["digestdata"] = md5Hex,
                        ["digesttype"] = 1,
                        ["filename"] = remoteFilename,
                        ["filesize"] = payloadBytes.Length,
                        ["filetype"] = 1,
                        ["packetsize"] = 1048576
                    },
                    cts.Token).ConfigureAwait(false);

                if (upInit == null || (upInit["code"]?.GetValue<int>() ?? -1) != 0)
                    return (false, true, "", "Rahmen-Dateiübertragung wurde vom Laser abgelehnt.");

                int negotiatedMax = Math.Clamp(upInit["data"]?["packetsize"]?.GetValue<int>() ?? 131072, 16384, 131072);
                long sentTotal = 0;
                bool uploadAcked = false;
                var sw = Stopwatch.StartNew();

                while (sw.ElapsedMilliseconds < 10000 && !uploadAcked && !cts.Token.IsCancellationRequested)
                {
                    if (!await ReceiveIntoBufferAsync(fileWs, fileBuf, 2800, cts.Token).ConfigureAwait(false))
                        break;

                    var frames = ExtractV2Frames(fileBuf);
                    foreach (var (proto, body) in frames)
                    {
                        if (proto == 33 && TryParseFileRequest(body, channel, out long offset, out int windowSize))
                        {
                            if (offset >= payloadBytes.Length) continue;
                            long endOffset = Math.Min(payloadBytes.Length, offset + windowSize);
                            long cursor = offset;
                            while (cursor < endOffset)
                            {
                                int chunkLen = (int)Math.Min(Math.Min(negotiatedMax, 65536), endOffset - cursor);
                                byte[] frameBytes = BuildFileDataFrame(channel, cursor, payloadBytes.AsSpan((int)cursor, chunkLen));
                                await fileWs.SendAsync(frameBytes.AsMemory(), WebSocketMessageType.Binary, endOfMessage: true, cts.Token).ConfigureAwait(false);
                                cursor += chunkLen;
                                sentTotal += chunkLen;
                            }
                        }
                        else if (proto == 4 && body.Length > 0)
                        {
                            try
                            {
                                if (JsonNode.Parse(Encoding.UTF8.GetString(body)) is JsonObject ack &&
                                    (ack["channel"]?.GetValue<int>() ?? -1) == channel)
                                {
                                    uploadAcked = (ack["code"]?.GetValue<int>() ?? -1) == 0;
                                    if (!uploadAcked)
                                        return (false, true, "", $"Laser meldete Übertragungsfehler auf Kanal {channel}.");
                                    break;
                                }
                            }
                            catch { }
                        }
                    }
                }

                if (!uploadAcked && sentTotal < payloadBytes.Length)
                    return (false, true, "", "Rahmen-Übertragung an den Laser unvollständig.");

                string taskId = Guid.NewGuid().ToString("D");
                JsonObject cfgParams = isXfArchive
                    ? new JsonObject { ["autoStart"] = 1, ["taskId"] = taskId }
                    : new JsonObject { ["loopPrint"] = 1, ["gcodeType"] = "frame" };
                JsonObject cfgData = isXfArchive
                    ? new JsonObject { ["autoStart"] = 1, ["fileType"] = "xf", ["taskId"] = taskId }
                    : new JsonObject { ["fileType"] = "txt", ["autoStart"] = 1, ["loopPrint"] = 1, ["gcodeType"] = "frame" };

                var cfgResp = await SendV2JsonCmdAsync(
                    mainWs,
                    mainBuf,
                    txId,
                    sessionToken,
                    "PUT",
                    "/v1/processing/upload/config",
                    cfgParams,
                    cfgData,
                    cts.Token).ConfigureAwait(false);

                int cfgCode = cfgResp?["code"]?.GetValue<int>() ?? 0;
                if (cfgResp == null || cfgCode != 0)
                    return (false, true, taskId, "Rahmen-Aktivierung am Laser fehlgeschlagen.");

                return (true, true, taskId, "");
            }
            catch (Exception ex)
            {
                return (false, true, "", McpSecurityGuard.ScrubSecrets(ex.Message));
            }
            finally
            {
                try { if (fileWs.State == WebSocketState.Open) await fileWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
                try { if (mainWs.State == WebSocketState.Open) await mainWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None).ConfigureAwait(false); } catch { }
            }
        }

        // =====================================================================
        // 7. RELAY JOB EXECUTION (capture_camera | send_xf | frame_border)
        // =====================================================================
        public static async Task<LaserRelayCompletionPayload> ExecuteRelayJobAsync(LaserRelayJobDto job)
        {
            if (!TryValidateRelayLaserIp(job.LaserIp, out string safeIp, out string? ipErr))
            {
                return new LaserRelayCompletionPayload
                {
                    JobId = job.JobId,
                    Ok = false,
                    LaserOnline = false,
                    State = "OFFLINE",
                    Error = ipErr
                };
            }

            string offlineMsg = $"xTool F2 Ultra UV ({safeIp}) im Werkstatt-LAN nicht erreichbar (bitte prüfen, ob der Laser eingeschaltet ist).";

            if (string.Equals(job.Action, "capture_camera", StringComparison.OrdinalIgnoreCase))
            {
                var camRes = await CaptureLiveCameraDetailedAsync(safeIp, job.LightMode, job.FocusMm).ConfigureAwait(false);
                lock (s_laserCacheLock)
                {
                    s_cachedLaserOnline = camRes.LaserOnline;
                    s_cachedLaserCheckedAtUtc = DateTime.UtcNow;
                }

                if (!camRes.Ok)
                {
                    return new LaserRelayCompletionPayload
                    {
                        JobId = job.JobId,
                        Ok = false,
                        LaserOnline = camRes.LaserOnline,
                        State = "OFFLINE",
                        Error = string.IsNullOrWhiteSpace(camRes.Error) ? offlineMsg : camRes.Error
                    };
                }

                return new LaserRelayCompletionPayload
                {
                    JobId = job.JobId,
                    Ok = true,
                    LaserOnline = true,
                    State = "CAMERA_OK",
                    Error = null,
                    WarpedJpegBase64 = camRes.WarpedJpeg != null ? Convert.ToBase64String(camRes.WarpedJpeg) : null,
                    RawJpegBase64 = camRes.RawJpeg != null ? Convert.ToBase64String(camRes.RawJpeg) : null
                };
            }

            if (string.Equals(job.Action, "send_xf", StringComparison.OrdinalIgnoreCase))
            {
                string rawB64 = job.XfBase64 ?? "";
                if (rawB64.Contains(',')) rawB64 = rawB64.Substring(rawB64.IndexOf(',') + 1);
                if (string.IsNullOrWhiteSpace(rawB64))
                {
                    return new LaserRelayCompletionPayload
                    {
                        JobId = job.JobId,
                        Ok = false,
                        LaserOnline = await ProbeAnyLaserPortAsync(safeIp, 400).ConfigureAwait(false),
                        State = "OFFLINE",
                        Error = "Kein .xf Laser-Paket (xf_base64) im Auftrag enthalten."
                    };
                }

                byte[] xfBytes;
                try { xfBytes = Convert.FromBase64String(rawB64.Trim()); }
                catch
                {
                    return new LaserRelayCompletionPayload
                    {
                        JobId = job.JobId,
                        Ok = false,
                        LaserOnline = await ProbeAnyLaserPortAsync(safeIp, 400).ConfigureAwait(false),
                        State = "OFFLINE",
                        Error = "Ungültiges Base64-Format für xf_base64."
                    };
                }

                if (!await ProbeLaserReachableAsync(safeIp, DefaultV2Port, 400).ConfigureAwait(false))
                {
                    lock (s_laserCacheLock) { s_cachedLaserOnline = false; s_cachedLaserCheckedAtUtc = DateTime.UtcNow; }
                    return new LaserRelayCompletionPayload
                    {
                        JobId = job.JobId,
                        Ok = false,
                        LaserOnline = false,
                        State = "OFFLINE",
                        Error = offlineMsg
                    };
                }

                var (upOk, _, upErr) = await UploadXfPackageLocalV2Async(xfBytes, safeIp).ConfigureAwait(false);
                lock (s_laserCacheLock) { s_cachedLaserOnline = true; s_cachedLaserCheckedAtUtc = DateTime.UtcNow; }

                return new LaserRelayCompletionPayload
                {
                    JobId = job.JobId,
                    Ok = upOk,
                    LaserOnline = true,
                    State = upOk ? "WORK_PREPARED" : "OFFLINE",
                    Error = upOk ? null : (string.IsNullOrWhiteSpace(upErr) ? "Übertragung an den Laser fehlgeschlagen." : upErr)
                };
            }

            if (string.Equals(job.Action, "frame_border", StringComparison.OrdinalIgnoreCase))
            {
                byte[] framingPayload;
                bool sendAsXfArchive = false;

                if (!string.IsNullOrWhiteSpace(job.Gcode))
                {
                    framingPayload = Encoding.UTF8.GetBytes(job.Gcode);
                    sendAsXfArchive = false;
                }
                else if (!string.IsNullOrWhiteSpace(job.XfBase64))
                {
                    string cleanB64 = job.XfBase64;
                    if (cleanB64.Contains(',')) cleanB64 = cleanB64.Substring(cleanB64.IndexOf(',') + 1);
                    try
                    {
                        byte[] decoded = Convert.FromBase64String(cleanB64.Trim());
                        byte[]? extractedBorder = TryExtractTarEntry(decoded, "border.gcode");
                        if (extractedBorder != null && extractedBorder.Length > 16)
                        {
                            framingPayload = extractedBorder;
                            sendAsXfArchive = false;
                        }
                        else if (decoded.Length > 512 && decoded[257] == (byte)'u' && decoded[258] == (byte)'s' && decoded[259] == (byte)'t')
                        {
                            framingPayload = decoded;
                            sendAsXfArchive = true;
                        }
                        else
                        {
                            framingPayload = decoded;
                            sendAsXfArchive = false;
                        }
                    }
                    catch
                    {
                        return new LaserRelayCompletionPayload
                        {
                            JobId = job.JobId,
                            Ok = false,
                            LaserOnline = await ProbeAnyLaserPortAsync(safeIp, 400).ConfigureAwait(false),
                            State = "OFFLINE",
                            Error = "Ungültige Rahmendaten im Relay-Auftrag."
                        };
                    }
                }
                else
                {
                    string synthesizedGcode = GenerateBorderGcode(
                        job.CenterXMm,
                        job.CenterYMm,
                        job.WidthMm,
                        job.HeightMm,
                        job.AngleDeg);
                    framingPayload = Encoding.UTF8.GetBytes(synthesizedGcode);
                    sendAsXfArchive = false;
                }

                var (frOk, frOnline, _, frErr) = await UploadFramingLocalV2Async(framingPayload, sendAsXfArchive, safeIp).ConfigureAwait(false);
                lock (s_laserCacheLock) { s_cachedLaserOnline = frOnline; s_cachedLaserCheckedAtUtc = DateTime.UtcNow; }

                return new LaserRelayCompletionPayload
                {
                    JobId = job.JobId,
                    Ok = frOk,
                    LaserOnline = frOnline,
                    State = frOk ? "FRAMING_STARTED" : "OFFLINE",
                    Error = frOk ? null : (string.IsNullOrWhiteSpace(frErr) ? offlineMsg : frErr)
                };
            }

            if (string.Equals(job.Action, "stop_laser", StringComparison.OrdinalIgnoreCase))
            {
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(6));
                string uuid = Guid.NewGuid().ToString("D");
                using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=instruction", cts.Token).ConfigureAwait(false);
                if (mainWs == null)
                {
                    return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = false, LaserOnline = false, State = "OFFLINE", Error = offlineMsg };
                }
                var mainBuf = new List<byte>(4096);
                var txId = new RefInt();
                string sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, cts.Token).ConfigureAwait(false);
                if (string.IsNullOrWhiteSpace(sessionToken))
                {
                    return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = false, LaserOnline = true, State = "AUTH_ERROR", Error = "Authentifizierung am Laser fehlgeschlagen." };
                }
                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/processing/state", new JsonObject { ["action"] = "stop" }, new JsonObject { ["action"] = "stop" }, cts.Token).ConfigureAwait(false);
                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "POST", "/v1/laser-head/focus/control", new JsonObject(), new JsonObject { ["action"] = "auto_stop" }, cts.Token).ConfigureAwait(false);
                return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = true, LaserOnline = true, State = "STOPPED" };
            }

            if (string.Equals(job.Action, "auto_focus", StringComparison.OrdinalIgnoreCase))
            {
                using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(24));
                string uuid = Guid.NewGuid().ToString("D");
                using var mainWs = await ConnectV2WsAsync(safeIp, DefaultV2Port, $"/websocket?id={uuid}&function=instruction", cts.Token).ConfigureAwait(false);
                if (mainWs == null)
                {
                    return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = false, LaserOnline = false, State = "OFFLINE", Error = offlineMsg };
                }
                var mainBuf = new List<byte>(4096);
                var txId = new RefInt();
                string sessionToken = await AuthenticateParityAsync(mainWs, mainBuf, txId, cts.Token).ConfigureAwait(false);
                if (string.IsNullOrWhiteSpace(sessionToken))
                {
                    return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = false, LaserOnline = true, State = "AUTH_ERROR", Error = "Authentifizierung am Laser fehlgeschlagen." };
                }

                _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "PUT", "/v1/device/mode", new JsonObject(), new JsonObject { ["mode"] = "P_AUTOFOCUS" }, cts.Token).ConfigureAwait(false);
                await Task.Delay(200, cts.Token).ConfigureAwait(false);
                var startNode = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "POST", "/v1/laser-head/focus/control", new JsonObject(), new JsonObject { ["action"] = "auto_start" }, cts.Token).ConfigureAwait(false);
                int startCode = startNode?["code"]?.GetValue<int>() ?? 0;
                if (startCode != 0)
                {
                    return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = false, LaserOnline = true, State = "FOCUS_ERROR", Error = "Auto-Fokus konnte vom Laser nicht gestartet werden." };
                }

                DateTime deadline = DateTime.UtcNow.AddSeconds(21);
                bool finished = false;
                while (DateTime.UtcNow < deadline && !cts.IsCancellationRequested)
                {
                    await Task.Delay(650, cts.Token).ConfigureAwait(false);
                    var rt = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "GET", "/v1/device/runtime-infos", new JsonObject(), new JsonObject(), cts.Token).ConfigureAwait(false);
                    string curMode = rt?["data"]?["curMode"]?["mode"]?.GetValue<string>() ?? "";
                    if (!string.IsNullOrEmpty(curMode) && !string.Equals(curMode, "P_AUTOFOCUS", StringComparison.Ordinal))
                    {
                        finished = true;
                        break;
                    }
                }

                if (!finished)
                {
                    _ = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "POST", "/v1/laser-head/focus/control", new JsonObject(), new JsonObject { ["action"] = "auto_stop" }, cts.Token).ConfigureAwait(false);
                    return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = false, LaserOnline = true, State = "TIMEOUT", Error = "Zeitüberschreitung beim automatischen Einmessen." };
                }

                await Task.Delay(180, cts.Token).ConfigureAwait(false);
                var coord = await SendV2JsonCmdAsync(mainWs, mainBuf, txId, sessionToken, "POST", "/v1/laser-head/focus/control", new JsonObject(), new JsonObject { ["action"] = "coord" }, cts.Token).ConfigureAwait(false);
                double rawZ = coord?["data"]?["value"]?.GetValue<double>() ?? 0.0;
                if (rawZ <= 0.0)
                {
                    return new LaserRelayCompletionPayload { JobId = job.JobId, Ok = false, LaserOnline = true, State = "FOCUS_ERROR", Error = "Z-Höhe konnte nicht ausgelesen werden." };
                }
                double focusMm = Math.Round(Math.Max(0.1, rawZ - 6.0), 1);
                return new LaserRelayCompletionPayload
                {
                    JobId = job.JobId,
                    Ok = true,
                    LaserOnline = true,
                    State = "FOCUS_OK",
                    FocusMm = focusMm,
                    RawZMm = Math.Round(rawZ, 2)
                };
            }

            return new LaserRelayCompletionPayload
            {
                JobId = job.JobId,
                Ok = false,
                LaserOnline = await GetCachedLaserOnlineAsync().ConfigureAwait(false),
                State = "OFFLINE",
                Error = $"Unbekannte Laser-Relay-Aktion '{McpSecurityGuard.ScrubSecrets(job.Action)}'."
            };
        }

        // =====================================================================
        // 8. STAGE REST CLIENT + ZERO-SECRET SCOPED TOKEN SELF-HEALING
        // =====================================================================
        private static async Task<(string Token, bool BootstrappedNow)> ResolveOrBootstrapStageBearerAsync(bool forceRefreshOnUnauthorized)
        {
            if (!string.IsNullOrWhiteSpace(TestStageBearerTokenOverride))
                return (TestStageBearerTokenOverride!, false);

            if (TestStageHttpClientOverride != null)
                return ("test-mock-stage-relay-bearer", false);

            if (!forceRefreshOnUnauthorized)
            {
                string existing = McpSecurityGuard.ResolveStageLaserRelayTokenFromVault();
                if (!string.IsNullOrWhiteSpace(existing))
                    return (existing, false);
            }

            lock (s_bootstrapLock)
            {
                if ((DateTime.UtcNow - s_lastTokenBootstrapAttemptUtc).TotalSeconds < 45.0)
                {
                    string cached = McpSecurityGuard.ResolveStageLaserRelayTokenFromVault();
                    return (cached, false);
                }
                s_lastTokenBootstrapAttemptUtc = DateTime.UtcNow;
            }

            try
            {
                string fresh = await McpSecurityGuard.BootstrapStageLaserRelayScopedTokenZeroSecretAsync().ConfigureAwait(false);
                return (fresh, !string.IsNullOrWhiteSpace(fresh));
            }
            catch
            {
                return ("", false);
            }
        }

        private static async Task<HttpResponseMessage> SendStageAuthenticatedAsync(
            HttpMethod method,
            string fullUrl,
            string bearer,
            string? jsonBody,
            int timeoutSeconds,
            CancellationToken ct)
        {
            if (!ValidatePinnedStageRelayUrl(fullUrl))
                throw new UnauthorizedAccessException("Nicht vertrauenswürdige Stage-Relay-URL blockiert.");

            HttpClient client = TestStageHttpClientOverride ?? RelayHttp;
            using var cts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            cts.CancelAfter(TimeSpan.FromSeconds(timeoutSeconds));

            using var req = new HttpRequestMessage(method, fullUrl);
            req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearer);
            req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            if (jsonBody != null)
            {
                req.Content = new StringContent(jsonBody, new UTF8Encoding(false), "application/json");
            }

            return await client.SendAsync(req, cts.Token).ConfigureAwait(false);
        }

        private static async Task<bool> PostCompletionAsync(string bearer, LaserRelayCompletionPayload result, CancellationToken ct)
        {
            string url = $"{PinnedStageApiBaseUrl}/shop/workshop-focus/laser-relay/complete";
            if (!ValidatePinnedStageRelayUrl(url))
                return false;

            string jsonBody = result.ToJsonObject().ToJsonString();
            int[] delaysMs = { 0, 650 };

            for (int attempt = 0; attempt < delaysMs.Length; attempt++)
            {
                if (delaysMs[attempt] > 0)
                    await Task.Delay(delaysMs[attempt], ct).ConfigureAwait(false);

                try
                {
                    using var resp = await SendStageAuthenticatedAsync(HttpMethod.Post, url, bearer, jsonBody, 16, ct).ConfigureAwait(false);
                    if (resp.IsSuccessStatusCode || (int)resp.StatusCode is 400 or 404 or 409 or 422)
                        return resp.IsSuccessStatusCode;
                }
                catch when (!ct.IsCancellationRequested)
                {
                }
            }
            return false;
        }

        // =====================================================================
        // 9. SINGLE FAILOVER CYCLE & 2.5s BACKGROUND DAEMON LOOP
        // =====================================================================
        public static async Task<FailoverCycleReportDto> RunSingleFailoverCycleAsync(
            bool sendExplicitHeartbeat = false,
            bool runDiagnosticCameraTest = false,
            bool forceRefreshProbes = false,
            CancellationToken ct = default,
            bool primaryNotebookMode = false)
        {
            var report = new FailoverCycleReportDto
            {
                TimestampUtc = DateTime.UtcNow.ToString("O")
            };

            if (!primaryNotebookMode || TestMiniPcProbeOverride != null)
            {
                // Legacy/Test failover guard: If Mini-PC (192.168.188.151:22) is awake, remain passive
                bool miniPcAwake = await IsMiniPcAwakeAsync(forceRefreshProbes).ConfigureAwait(false);
                report.MiniPcOnline = miniPcAwake;
                if (miniPcAwake)
                {
                    report.Status = "ok";
                    report.Mode = "passive_minipc_online";
                    report.PolledStage = false;
                    report.HeartbeatSent = false;
                    return report;
                }
            }

            report.Mode = primaryNotebookMode && TestMiniPcProbeOverride == null
                ? "primary_notebook_relay"
                : "active_windows_failover";

            try
            {
                // Prune deduplication map entries older than 2 minutes
                DateTime pruneBefore = DateTime.UtcNow.AddMinutes(-2);
                foreach (var kv in s_recentlyHandledJobs)
                {
                    if (kv.Value < pruneBefore)
                        s_recentlyHandledJobs.TryRemove(kv.Key, out _);
                }

                bool laserOnline = await GetCachedLaserOnlineAsync(forceRefreshProbes).ConfigureAwait(false);
                report.LaserOnline = laserOnline;

                var (bearer, bootstrappedInitial) = await ResolveOrBootstrapStageBearerAsync(forceRefreshOnUnauthorized: false).ConfigureAwait(false);
                report.TokenBootstrapped = bootstrappedInitial;

                if (string.IsNullOrWhiteSpace(bearer))
                {
                    report.Status = "error";
                    report.Error = "Kein gültiges Stage-Relay-Token verfügbar.";
                    return report;
                }

                if (sendExplicitHeartbeat)
                {
                    string hbUrl = $"{PinnedStageApiBaseUrl}/shop/workshop-focus/laser-relay/heartbeat";
                    string hbBody = new JsonObject
                    {
                        ["laser_online"] = laserOnline,
                        ["laser_ip"] = DefaultLaserIp
                    }.ToJsonString();

                    using var hbResp = await SendStageAuthenticatedAsync(HttpMethod.Post, hbUrl, bearer, hbBody, 8, ct).ConfigureAwait(false);
                    int hbCode = (int)hbResp.StatusCode;

                    if ((hbCode == 401 || hbCode == 403) && TestStageHttpClientOverride == null)
                    {
                        var (refreshedToken, reBootstrapped) = await ResolveOrBootstrapStageBearerAsync(forceRefreshOnUnauthorized: true).ConfigureAwait(false);
                        if (!string.IsNullOrWhiteSpace(refreshedToken))
                        {
                            bearer = refreshedToken;
                            report.TokenBootstrapped |= reBootstrapped;
                            using var hbRetry = await SendStageAuthenticatedAsync(HttpMethod.Post, hbUrl, bearer, hbBody, 8, ct).ConfigureAwait(false);
                            hbCode = (int)hbRetry.StatusCode;
                            report.HeartbeatSent = hbRetry.IsSuccessStatusCode;
                        }
                    }
                    else
                    {
                        report.HeartbeatSent = hbResp.IsSuccessStatusCode;
                    }
                    report.StageHttpStatus = hbCode;
                }

                int onlineFlag = laserOnline ? 1 : 0;
                // WICHTIG: Im 2,5s-Hintergrundloop NIEMALS AuthenticateParityAsync (/v1/user/parity) aufrufen,
                // da der xTool F2 Ultra UV bei jedem /v1/user/parity einen lauten Verbindungs-Piepton auslöst!
                string pollUrl = $"{PinnedStageApiBaseUrl}/shop/workshop-focus/laser-relay/poll?laser_online={onlineFlag}&laser_ip={Uri.EscapeDataString(DefaultLaserIp)}";

                HttpResponseMessage pollResp = await SendStageAuthenticatedAsync(HttpMethod.Get, pollUrl, bearer, null, 8, ct).ConfigureAwait(false);
                try
                {
                    int pollCode = (int)pollResp.StatusCode;
                    if ((pollCode == 401 || pollCode == 403) && TestStageHttpClientOverride == null)
                    {
                        pollResp.Dispose();
                        var (refreshedToken, reBootstrapped) = await ResolveOrBootstrapStageBearerAsync(forceRefreshOnUnauthorized: true).ConfigureAwait(false);
                        if (!string.IsNullOrWhiteSpace(refreshedToken))
                        {
                            bearer = refreshedToken;
                            report.TokenBootstrapped |= reBootstrapped;
                            pollResp = await SendStageAuthenticatedAsync(HttpMethod.Get, pollUrl, bearer, null, 8, ct).ConfigureAwait(false);
                            pollCode = (int)pollResp.StatusCode;
                        }
                        else
                        {
                            report.Status = "error";
                            report.StageHttpStatus = pollCode;
                            report.Error = $"Stage-Authentifizierung abgelehnt (HTTP {pollCode}).";
                            return report;
                        }
                    }

                    report.PolledStage = true;
                    report.StageHttpStatus = pollCode;

                    if (pollResp.IsSuccessStatusCode)
                    {
                        report.HeartbeatSent = true;
                        string rawBody = await pollResp.Content.ReadAsStringAsync(ct).ConfigureAwait(false);
                        var job = TryDeserializePollResponse(rawBody);

                        if (job != null && s_recentlyHandledJobs.TryAdd(job.JobId, DateTime.UtcNow))
                        {
                            report.ClaimedJobId = job.JobId;
                            report.ClaimedAction = job.Action;

                            var completion = TestJobExecutorOverride != null
                                ? await TestJobExecutorOverride(job).ConfigureAwait(false)
                                : await ExecuteRelayJobAsync(job).ConfigureAwait(false);

                            await PostCompletionAsync(bearer, completion, ct).ConfigureAwait(false);

                            report.LaserOnline = completion.LaserOnline;
                            report.JobCompletedOk = completion.Ok;
                            report.JobResultState = completion.State;
                            if (!completion.Ok && !string.IsNullOrWhiteSpace(completion.Error))
                            {
                                report.Error = McpSecurityGuard.ScrubSecrets(completion.Error);
                            }
                        }
                    }
                    else if (pollCode != 204 && pollCode != 404)
                    {
                        report.Status = "error";
                        report.Error = $"Stage-Poll antwortete mit HTTP {pollCode}.";
                    }
                }
                finally
                {
                    pollResp.Dispose();
                }

                if (runDiagnosticCameraTest)
                {
                    var camRes = await CaptureLiveCameraDetailedAsync(DefaultLaserIp).ConfigureAwait(false);
                    byte[]? chosenBytes = camRes.WarpedJpeg ?? camRes.RawJpeg;
                    int w = 0, h = 0;
                    if (chosenBytes != null)
                    {
                        TryReadJpegDimensions(chosenBytes, out w, out h);
                    }

                    report.LaserOnline = camRes.LaserOnline;
                    report.CameraTest = new CameraDiagnosticDto
                    {
                        Ok = camRes.Ok,
                        LaserOnline = camRes.LaserOnline,
                        Source = camRes.Source,
                        CalibratedGridUsed = camRes.CalibratedGridUsed,
                        WidthPx = w,
                        HeightPx = h,
                        JpegBytes = chosenBytes?.Length ?? 0,
                        Error = string.IsNullOrWhiteSpace(camRes.Error) ? null : McpSecurityGuard.ScrubSecrets(camRes.Error)
                    };
                }

                return report;
            }
            catch (OperationCanceledException) when (ct.IsCancellationRequested)
            {
                throw;
            }
            catch (Exception ex)
            {
                report.Status = "error";
                report.Error = McpSecurityGuard.ScrubSecrets(ex.Message);
                return report;
            }
        }

        public static async Task RunDaemonLoopAsync(CancellationToken ct, bool primaryNotebookMode = true)
        {
            int nextDelayMs = 400;

            while (!ct.IsCancellationRequested)
            {
                try
                {
                    await Task.Delay(nextDelayMs, ct).ConfigureAwait(false);

                    var cycle = await RunSingleFailoverCycleAsync(
                        sendExplicitHeartbeat: false,
                        runDiagnosticCameraTest: false,
                        forceRefreshProbes: false,
                        ct: ct,
                        primaryNotebookMode: primaryNotebookMode).ConfigureAwait(false);

                    if (cycle.MiniPcOnline)
                    {
                        nextDelayMs = 3000;
                    }
                    else if (!string.IsNullOrEmpty(cycle.ClaimedJobId))
                    {
                        nextDelayMs = 200;
                    }
                    else if (string.Equals(cycle.Status, "error", StringComparison.OrdinalIgnoreCase))
                    {
                        nextDelayMs = 5000;
                    }
                    else
                    {
                        nextDelayMs = DefaultDaemonIdleDelayMs;
                    }
                }
                catch (OperationCanceledException) when (ct.IsCancellationRequested)
                {
                    break;
                }
                catch
                {
                    nextDelayMs = 5000;
                }
            }
        }
    }
}
